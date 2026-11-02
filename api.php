<?php
require __DIR__ . '/db.php';
require __DIR__ . '/core.php';

header('Content-Type: application/json; charset=utf-8');
$user = currentUser($pdo);
$action = $_GET['action'] ?? '';

if ($action === 'unread') {
    $count = 0;
    if ($user) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$user['id']]);
        $count = (int) $stmt->fetchColumn();
    }
    echo json_encode(['count' => $count]);
    exit;
}

if ($action === 'free') {
    $id = (int) ($_GET['event_id'] ?? 0);
    $event = eventById($pdo, $id);
    if (!$event) {
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    if ($event['status'] !== 'published' && !canEditEvent($user, $event)) {
        echo json_encode(['error' => 'hidden']);
        exit;
    }
    echo json_encode(['event_id' => $id, 'capacity' => $event['capacity'] === null ? null : (int) $event['capacity'], 'registered' => registeredCount($pdo, $id), 'free' => freePlaces($pdo, $event)]);
    exit;
}

echo json_encode(['error' => 'unknown_action']);
