<?php

$user = current_user();
$token = (string) input('token');
$result = null;

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'mark') {
        $result = event_mark_attended(mb_strtolower($token), $user);
        flash(isset($result['error']) ? 'error' : 'success', $result['error'] ?? 'Посещение отмечено.');
        if (!isset($result['error'])) {
            $token = '';
        }
    }
}

render('attend', [
    'title' => 'Отметка посещения',
    'navActive' => 'manage',
    'token' => $token,
    'result' => $result,
]);
