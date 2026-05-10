<?php
header("Content-Type: application/json");

require_once __DIR__ . "/db.php";
$db = getDBConnection();

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$input = json_decode(file_get_contents("php://input"), true) ?? [];

function sendResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

try {
    $action = $_GET["action"] ?? null;
    $id = $_GET["id"] ?? null;
    $resourceId = $_GET["resource_id"] ?? null;
    $commentId = $_GET["comment_id"] ?? null;

    if ($method === "GET") {
        if ($action === "comments") {
            if (!$resourceId || !is_numeric($resourceId)) {
                sendResponse(["success" => false, "message" => "Invalid resource id."], 400);
            }

            $stmt = $db->prepare("
                SELECT id, resource_id, author, text, created_at
                FROM comments_resource
                WHERE resource_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$resourceId]);

            sendResponse([
                "success" => true,
                "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
        }

        if ($id !== null) {
            if (!is_numeric($id)) {
                sendResponse(["success" => false, "message" => "Invalid resource id."], 400);
            }

            $stmt = $db->prepare("
                SELECT id, title, description, link, created_at
                FROM resources
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) {
                sendResponse(["success" => false, "message" => "Resource not found."], 404);
            }

            sendResponse(["success" => true, "data" => $resource]);
        }

        $sql = "SELECT id, title, description, link, created_at FROM resources";
        $params = [];

        if (!empty($_GET["search"])) {
            $sql .= " WHERE title LIKE ? OR description LIKE ?";
            $search = "%" . $_GET["search"] . "%";
            $params = [$search, $search];
        }

        $sort = $_GET["sort"] ?? "created_at";
        $order = strtolower($_GET["order"] ?? "desc");

        if (!in_array($sort, ["title", "created_at"], true)) {
            $sort = "created_at";
        }

        if (!in_array($order, ["asc", "desc"], true)) {
            $order = "desc";
        }

        $sql .= " ORDER BY {$sort} {$order}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        sendResponse([
            "success" => true,
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    if ($method === "POST") {
        if ($action === "comment") {
            if (
                empty($input["resource_id"]) ||
                empty($input["author"]) ||
                empty($input["text"])
            ) {
                sendResponse(["success" => false, "message" => "Missing required fields."], 400);
            }

            if (!is_numeric($input["resource_id"])) {
                sendResponse(["success" => false, "message" => "Invalid resource id."], 400);
            }

            $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
            $stmt->execute([$input["resource_id"]]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                sendResponse(["success" => false, "message" => "Resource not found."], 404);
            }

            $author = trim((string)$input["author"]);
            $text = trim((string)$input["text"]);

            $stmt = $db->prepare("
                INSERT INTO comments_resource (resource_id, author, text)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$input["resource_id"], $author, $text]);

            sendResponse([
                "success" => true,
                "id" => $db->lastInsertId()
            ], 201);
        }

        if (empty($input["title"]) || empty($input["link"])) {
            sendResponse(["success" => false, "message" => "Title and link are required."], 400);
        }

        $title = trim((string)$input["title"]);
        $description = trim((string)($input["description"] ?? ""));
        $link = trim((string)$input["link"]);

        if (!validUrl($link)) {
            sendResponse(["success" => false, "message" => "Invalid URL."], 400);
        }

        $stmt = $db->prepare("
            INSERT INTO resources (title, description, link)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$title, $description, $link]);

        sendResponse([
            "success" => true,
            "id" => $db->lastInsertId()
        ], 201);
    }

    if ($method === "PUT") {
        if (empty($input["id"]) || !is_numeric($input["id"])) {
            sendResponse(["success" => false, "message" => "Invalid resource id."], 400);
        }

        $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
        $stmt->execute([$input["id"]]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            sendResponse(["success" => false, "message" => "Resource not found."], 404);
        }

        $title = array_key_exists("title", $input)
            ? trim((string)$input["title"])
            : $existing["title"];

        $description = array_key_exists("description", $input)
            ? trim((string)$input["description"])
            : $existing["description"];

        $link = array_key_exists("link", $input)
            ? trim((string)$input["link"])
            : $existing["link"];

        if (array_key_exists("link", $input) && !validUrl($link)) {
            sendResponse(["success" => false, "message" => "Invalid URL."], 400);
        }

        $stmt = $db->prepare("
            UPDATE resources
            SET title = ?, description = ?, link = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $link, $input["id"]]);

        sendResponse([
            "success" => true,
            "message" => "Resource updated successfully."
        ]);
    }

    if ($method === "DELETE") {
        if ($action === "delete_comment") {
            if (!$commentId || !is_numeric($commentId)) {
                sendResponse(["success" => false, "message" => "Invalid comment id."], 400);
            }

            $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
            $stmt->execute([$commentId]);

            if ($stmt->rowCount() === 0) {
                sendResponse(["success" => false, "message" => "Comment not found."], 404);
            }

            sendResponse([
                "success" => true,
                "message" => "Comment deleted successfully."
            ]);
        }

        if (!$id || !is_numeric($id)) {
            sendResponse(["success" => false, "message" => "Invalid resource id."], 400);
        }

        $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            sendResponse(["success" => false, "message" => "Resource not found."], 404);
        }

        sendResponse([
            "success" => true,
            "message" => "Resource deleted successfully."
        ]);
    }

    sendResponse(["success" => false, "message" => "Method not allowed."], 405);

} catch (Throwable $e) {
    sendResponse(["success" => false, "message" => "Server error."], 500);
}
?>