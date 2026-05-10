<?php
header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$input = json_decode(file_get_contents("php://input"), true) ?? [];

try {
   $db = new PDO("mysql:host=localhost;dbname=course;charset=utf8mb4", "root", "root");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $action = $_GET["action"] ?? null;
    $id = $_GET["id"] ?? null;
    $resourceId = $_GET["resource_id"] ?? null;
    $commentId = $_GET["comment_id"] ?? null;

    if ($method === "GET") {
        if ($action === "comments") {
            if (!$resourceId || !is_numeric($resourceId)) response(["success" => false, "message" => "Invalid resource id"], 400);

            $stmt = $db->prepare("SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC");
            $stmt->execute([$resourceId]);
            response(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        if ($id) {
            $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
            $stmt->execute([$id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) response(["success" => false, "message" => "Resource not found."], 404);
            response(["success" => true, "data" => $resource]);
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

        if (!in_array($sort, ["title", "created_at"])) $sort = "created_at";
        if (!in_array($order, ["asc", "desc"])) $order = "desc";

        $sql .= " ORDER BY $sort $order";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        response(["success" => true, "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === "POST") {
        if ($action === "comment") {
            if (empty($input["resource_id"]) || empty($input["author"]) || empty($input["text"])) {
                response(["success" => false, "message" => "Missing required fields."], 400);
            }

            $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
            $stmt->execute([$input["resource_id"]]);
            if (!$stmt->fetch()) response(["success" => false, "message" => "Resource not found."], 404);

            $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
            $stmt->execute([$input["resource_id"], trim($input["author"]), trim($input["text"])]);

            $newId = $db->lastInsertId();

            response([
                "success" => true,
                "id" => $newId,
                "data" => [
                    "id" => $newId,
                    "resource_id" => $input["resource_id"],
                    "author" => trim($input["author"]),
                    "text" => trim($input["text"])
                ]
            ], 201);
        }

        if (empty($input["title"]) || empty($input["link"])) {
            response(["success" => false, "message" => "Title and link are required."], 400);
        }

        if (!filter_var($input["link"], FILTER_VALIDATE_URL)) {
            response(["success" => false, "message" => "Invalid URL."], 400);
        }

        $description = $input["description"] ?? "";

        $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
        $stmt->execute([trim($input["title"]), trim($description), trim($input["link"])]);

        response([
            "success" => true,
            "id" => $db->lastInsertId()
        ], 201);
    }

    if ($method === "PUT") {
        if (empty($input["id"]) || !is_numeric($input["id"])) {
            response(["success" => false, "message" => "Invalid resource id."], 400);
        }

        $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
        $stmt->execute([$input["id"]]);
        if (!$stmt->fetch()) response(["success" => false, "message" => "Resource not found."], 404);

        if (isset($input["link"]) && !filter_var($input["link"], FILTER_VALIDATE_URL)) {
            response(["success" => false, "message" => "Invalid URL."], 400);
        }

        $title = $input["title"] ?? "";
        $description = $input["description"] ?? "";
        $link = $input["link"] ?? "";

        $stmt = $db->prepare("UPDATE resources SET title = ?, description = ?, link = ? WHERE id = ?");
        $stmt->execute([trim($title), trim($description), trim($link), $input["id"]]);

        response(["success" => true, "message" => "Resource updated successfully."]);
    }

    if ($method === "DELETE") {
        if ($action === "delete_comment") {
            if (!$commentId || !is_numeric($commentId)) {
                response(["success" => false, "message" => "Invalid comment id."], 400);
            }

            $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
            $stmt->execute([$commentId]);

            if ($stmt->rowCount() === 0) response(["success" => false, "message" => "Comment not found."], 404);
            response(["success" => true, "message" => "Comment deleted successfully."]);
        }

        if (!$id || !is_numeric($id)) {
            response(["success" => false, "message" => "Invalid resource id."], 400);
        }

        $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) response(["success" => false, "message" => "Resource not found."], 404);
        response(["success" => true, "message" => "Resource deleted successfully."]);
    }

    response(["success" => false, "message" => "Method not allowed."], 405);

} catch (Exception $e) {
    response(["success" => false, "message" => "Server error."], 500);
}

function response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
?>