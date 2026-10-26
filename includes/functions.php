<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function nowText()
{
    return date('Y-m-d H:i:s');
}

function formatDate($value)
{
    if (!$value) {
        return '';
    }
    return date('d.m.Y H:i', strtotime($value));
}

function formatDay($value)
{
    if (!$value) {
        return '';
    }
    return date('d.m.Y', strtotime($value));
}

function inputDate($value)
{
    if (!$value) {
        return '';
    }
    return date('Y-m-d\TH:i', strtotime($value));
}

function saveDate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    return $value;
}

function currentUser($pdo)
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT u.*, r.code AS role_code, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function requireLogin($user)
{
    if (!$user) {
        redirect('login.php');
    }
}

function requireRole($user, $roles)
{
    requireLogin($user);
    if (!in_array($user['role_code'], $roles, true)) {
        redirect('index.php');
    }
}

function csrfField()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function checkCsrf()
{
    if (empty($_POST['csrf']) || empty($_SESSION['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        die('Ошибка проверки формы. Вернитесь назад и попробуйте ещё раз.');
    }
}

function logAction($pdo, $userId, $action, $entity, $entityId)
{
    try {
        $stmt = $pdo->prepare('INSERT INTO action_log (user_id, action, entity, entity_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), nowText()]);
    } catch (PDOException $error) {
    }
}

function notify($pdo, $userId, $eventId, $type, $subject, $message)
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, event_id, type, channel, subject, message, status, is_read, created_at, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
    $stmt->execute([$userId, $eventId, $type, 'web', $subject, $message, 'sent', nowText(), nowText()]);
}

function getSetting($pdo, $key, $defaultValue)
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $defaultValue;
        }
        return $value;
    } catch (PDOException $error) {
        return $defaultValue;
    }
}

function registeredCount($pdo, $eventId)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE event_id = ? AND status IN ('registered', 'confirmed', 'attended')");
    $stmt->execute([$eventId]);
    return (int) $stmt->fetchColumn();
}

function freePlaces($pdo, $event)
{
    if ($event['capacity'] === null || $event['capacity'] === '') {
        return null;
    }
    $free = (int) $event['capacity'] - registeredCount($pdo, $event['id']);
    if ($free < 0) {
        return 0;
    }
    return $free;
}

function makeToken()
{
    return bin2hex(random_bytes(20));
}

function promoteWaitlist($pdo, $eventId)
{
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    if (!$event) {
        return;
    }
    $free = freePlaces($pdo, $event);
    if ($free !== null && $free <= 0) {
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM waitlist WHERE event_id = ? AND status = 'waiting' ORDER BY position ASC, id ASC");
    $stmt->execute([$eventId]);
    $first = $stmt->fetch();
    if (!$first) {
        return;
    }
    $token = makeToken();
    $check = $pdo->prepare('SELECT id FROM registrations WHERE event_id = ? AND user_id = ?');
    $check->execute([$eventId, $first['user_id']]);
    $old = $check->fetch();
    if ($old) {
        $upd = $pdo->prepare("UPDATE registrations SET status = 'registered', qr_token = ?, registered_at = ?, cancelled_at = NULL, attended_at = NULL WHERE id = ?");
        $upd->execute([$token, nowText(), $old['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO registrations (event_id, user_id, status, qr_token, registered_at) VALUES (?, ?, 'registered', ?, ?)");
        $ins->execute([$eventId, $first['user_id'], $token, nowText()]);
    }
    $upd = $pdo->prepare("UPDATE waitlist SET status = 'registered', updated_at = ? WHERE id = ?");
    $upd->execute([nowText(), $first['id']]);
    notify($pdo, $first['user_id'], $eventId, 'waitlist', 'Освободилось место', 'Вы записаны на мероприятие «' . $event['title'] . '». Токен билета: ' . $token);
}

function eventStatusName($status)
{
    $names = ['draft' => 'Черновик', 'moderation' => 'На модерации', 'published' => 'Опубликовано', 'cancelled' => 'Отменено', 'completed' => 'Завершено'];
    return $names[$status] ?? $status;
}

function regStatusName($status)
{
    $names = ['registered' => 'Записан', 'confirmed' => 'Подтверждена', 'attended' => 'Посетил', 'cancelled' => 'Отменена', 'waiting' => 'Ожидает', 'invited' => 'Приглашён', 'rejected' => 'Отклонён'];
    return $names[$status] ?? $status;
}

function userStatusName($status)
{
    $names = ['active' => 'Активен', 'blocked' => 'Заблокирован', 'pending' => 'Ожидает'];
    return $names[$status] ?? $status;
}

function fullName($user)
{
    $name = trim($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? ''));
    return $name;
}

function makeSlug($title)
{
    $map = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e', 'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya'];
    $slug = strtr($title, $map);
    $slug = mb_strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'event';
    }
    return mb_substr($slug, 0, 140);
}

function checkinUrl($token)
{
    $protocol = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $protocol = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $path = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME'] ?? ''), '', $_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return $protocol . '://' . $host . $path . '/checkin.php?token=' . urlencode($token);
}

function canEditEvent($user, $event)
{
    if (!$user) {
        return false;
    }
    if ($user['role_code'] === 'admin') {
        return true;
    }
    return $user['role_code'] === 'organizer' && (int) $event['organizer_id'] === (int) $user['id'];
}
