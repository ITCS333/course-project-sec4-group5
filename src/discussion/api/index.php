<?php

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
   http_response_code(200);
   exit;
}

// DB Connection
require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

// Request Data
$method  = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');

$data = json_decode($rawData, true);

if (!$data) {
   $data = $_POST;
}

$action  = $_GET['action'] ?? null;
$id      = $_GET['id'] ?? null;
$topicId = $_GET['topic_id'] ?? null;

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
   http_response_code($statusCode);

   echo json_encode($data, JSON_PRETTY_PRINT);

   exit;
}

function sanitizeInput(string $data): string
{
   return trim($data);
}

// ============================================================================
// TOPICS FUNCTIONS
// ============================================================================

function getAllTopics(PDO $db): void
{
   $query = "
       SELECT
t.id,
           t.subject,
           t.message,
           t.author,
           t.created_at,
           COUNT(r.id) AS replies_count
       FROM topics t
       LEFT JOIN replies r ON t.id = r.topic_id
   ";

   $params = [];

   if (!empty($_GET['search'])) {

       $query .= "
           WHERE t.subject LIKE :search
           OR t.message LIKE :search
           OR t.author LIKE :search
       ";

       $params[':search'] = '%' . $_GET['search'] . '%';
   }

   $query .= " GROUP BY t.id ";

   $sort = in_array($_GET['sort'] ?? '', [
       'subject',
       'author',
       'created_at'
   ]) ? $_GET['sort'] : 'created_at';

   $order = in_array(strtolower($_GET['order'] ?? ''), [
       'asc',
       'desc'
   ]) ? $_GET['order'] : 'desc';

   $query .= " ORDER BY t.$sort $order ";

   $stmt = $db->prepare($query);

   foreach ($params as $key => $value) {
       $stmt->bindValue($key, $value);
   }

   $stmt->execute();

   $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

   sendResponse([
       'success' => true,
       'data' => $topics
   ]);
}

function getTopicById(PDO $db, $id): void
{
   if (!is_numeric($id)) {

       sendResponse([
           'success' => false,
           'message' => 'Invalid topic id'
       ], 400);
   }

   $stmt = $db->prepare("
       SELECT id, subject, message, author, created_at
       FROM topics
       WHERE id = ?
   ");

   $stmt->execute([$id]);

   $topic = $stmt->fetch(PDO::FETCH_ASSOC);

   if ($topic) {

       sendResponse([
           'success' => true,
           'data' => $topic
       ]);
   }

   sendResponse([
       'success' => false,
       'message' => 'Topic not found'
   ], 404);
}

function createTopic(PDO $db, array $data): void
{
   if (
       empty($data['subject']) ||
       empty($data['message']) ||
       empty($data['author'])
   ) {

       sendResponse([
           'success' => false,
           'message' => 'Missing fields'
       ], 400);
   }

   $subject = sanitizeInput($data['subject']);
   $message = sanitizeInput($data['message']);
   $author  = sanitizeInput($data['author']);

   $stmt = $db->prepare("
       INSERT INTO topics (subject, message, author)
       VALUES (?, ?, ?)
   ");

   if ($stmt->execute([$subject, $message, $author])) {

       sendResponse([
           'success' => true,
           'message' => 'Topic created',
           'id' => (int)$db->lastInsertId()
       ], 201);
   }

   sendResponse([
       'success' => false,
       'message' => 'Failed to create topic'
   ], 500);
}

function updateTopic(PDO $db, array $data): void
{
   $id = $_GET['id'] ?? $data['id'] ?? null;

   if (!$id) {

       sendResponse([
           'success' => false,
           'message' => 'Topic id required'
       ], 400);
   }

   $id = (int)$id;

   $check = $db->prepare("
       SELECT id
       FROM topics
       WHERE id = ?
   ");

   $check->execute([$id]);

   if (!$check->fetch()) {

       sendResponse([
           'success' => false,
           'message' => 'Topic not found'
       ], 404);
   }

   $fields = [];
   $params = [':id' => $id];

   if (!empty($data['subject'])) {

       $fields[] = 'subject = :subject';

       $params[':subject'] = sanitizeInput($data['subject']);
   }

   if (!empty($data['message'])) {

       $fields[] = 'message = :message';

       $params[':message'] = sanitizeInput($data['message']);
   }

   if (empty($fields)) {

       sendResponse([
           'success' => false,
           'message' => 'No fields to update'
       ], 400);
   }

   $query = "
       UPDATE topics
       SET " . implode(',', $fields) . "
       WHERE id = :id
   ";

   $stmt = $db->prepare($query);

   if ($stmt->execute($params)) {

       sendResponse([
           'success' => true,
           'message' => 'Topic updated'
       ]);
   }

   sendResponse([
       'success' => false,
       'message' => 'Failed to update topic'
   ], 500);
}

function deleteTopic(PDO $db, $id): void
{
   if (!is_numeric($id)) {

       sendResponse([
           'success' => false,
           'message' => 'Invalid topic id'
       ], 400);
   }

   $check = $db->prepare("
       SELECT id
       FROM topics
       WHERE id = ?
   ");

   $check->execute([$id]);

   if (!$check->fetch()) {

       sendResponse([
           'success' => false,
           'message' => 'Topic not found'
       ], 404);
   }

   $stmt = $db->prepare("
       DELETE FROM topics
       WHERE id = ?
   ");

   if ($stmt->execute([$id])) {

       sendResponse([
           'success' => true,
           'message' => 'Topic deleted'
       ]);
   }

   sendResponse([
       'success' => false,
       'message' => 'Failed to delete topic'
   ], 500);
}

// ============================================================================
// REPLIES FUNCTIONS
// ============================================================================

function getRepliesByTopicId(PDO $db, $topicId): void
{
   if (!is_numeric($topicId)) {

       sendResponse([
           'success' => false,
           'message' => 'Invalid topic id'
       ], 400);
   }

   $stmt = $db->prepare("
       SELECT id, topic_id, text, author, created_at
       FROM replies
       WHERE topic_id = ?
       ORDER BY created_at DESC
   ");

   $stmt->execute([$topicId]);

   $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

   sendResponse([
       'success' => true,
       'data' => $replies
   ]);
}

function createReply(PDO $db, array $data): void
{
   if (
       empty($data['topic_id']) ||
       empty($data['text']) ||
       empty($data['author'])
   ) {

       sendResponse([
           'success' => false,
           'message' => 'Missing fields'
       ], 400);
   }

   $topic_id = (int)$data['topic_id'];

   $text = sanitizeInput($data['text']);

   $author = sanitizeInput($data['author']);

   $check = $db->prepare("
       SELECT id
       FROM topics
       WHERE id = ?
   ");

   $check->execute([$topic_id]);

   if (!$check->fetch()) {

       sendResponse([
           'success' => false,
           'message' => 'Topic not found'
       ], 404);
   }

   $stmt = $db->prepare("
       INSERT INTO replies (topic_id, text, author)
       VALUES (?, ?, ?)
   ");

   if ($stmt->execute([$topic_id, $text, $author])) {

       $id = (int)$db->lastInsertId();

       $stmt2 = $db->prepare("
           SELECT id, topic_id, text, author, created_at
           FROM replies
           WHERE id = ?
       ");

       $stmt2->execute([$id]);

       $reply = $stmt2->fetch(PDO::FETCH_ASSOC);

       sendResponse([
           'success' => true,
           'message' => 'Reply created',
           'id' => $id,
           'data' => $reply
       ], 201);
   }

   sendResponse([
       'success' => false,
       'message' => 'Failed to create reply'
   ], 500);
}

function deleteReply(PDO $db, $id): void
{
   if (!is_numeric($id)) {

       sendResponse([
           'success' => false,
           'message' => 'Invalid reply id'
       ], 400);
   }

   $check = $db->prepare("
       SELECT id
       FROM replies
       WHERE id = ?
   ");

   $check->execute([$id]);

   if (!$check->fetch()) {

       sendResponse([
           'success' => false,
           'message' => 'Reply not found'
       ], 404);
   }

   $stmt = $db->prepare("
       DELETE FROM replies
       WHERE id = ?
   ");

   if ($stmt->execute([$id])) {

       sendResponse([
           'success' => true,
           'message' => 'Reply deleted'
       ]);
   }

   sendResponse([
       'success' => false,
       'message' => 'Failed to delete reply'
   ], 500);
}

// ============================================================================
// MAIN ROUTER
// ============================================================================

try {

   if ($method === 'GET') {

       if ($action === 'replies' && $topicId) {

           getRepliesByTopicId($db, $topicId);

       } elseif ($id) {

           getTopicById($db, $id);

       } else {

           getAllTopics($db);
       }

   } elseif ($method === 'POST') {

       if ($action === 'reply') {

           createReply($db, $data);

       } else {

           createTopic($db, $data);
       }

   } elseif ($method === 'PUT') {

       updateTopic($db, $data);

   } elseif ($method === 'DELETE') {

       if ($action === 'reply') {

           deleteReply($db, $id);

       } else {

           deleteTopic($db, $id);
       }

   } else {

       sendResponse([
           'success' => false,
           'message' => 'Method Not Allowed'
       ], 405);
   }

} catch (Throwable $e) {

   error_log($e->getMessage());

   sendResponse([
       'success' => false,
       'message' => 'Internal Server Error'
   ], 500);
}
?>
