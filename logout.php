<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';
$user = currentUser($pdo);
if ($user) {
    logAction($pdo, $user['id'], 'logout', 'users', $user['id']);
}
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
