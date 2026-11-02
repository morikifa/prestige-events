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
    return $value ? date('d.m.Y H:i', strtotime($value)) : '';
}

function inputDate($value)
{
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}

function saveDate($value)
{
    $value = str_replace('T', ' ', trim((string) $value));
    if ($value === '') {
        return null;
    }
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    return $value;
}

function insertedId($pdo)
{
    if (($GLOBALS['dbType'] ?? 'sqlite') === 'sqlsrv') {
        $value = $pdo->query('SELECT CAST(@@IDENTITY AS INT)')->fetchColumn();
        if ($value !== false && $value !== null) {
            return (int) $value;
        }
    }
    return (int) $pdo->lastInsertId();
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

function needLogin($user)
{
    if (!$user) {
        redirect('account.php?p=login');
    }
}

function needRole($user, $roles)
{
    needLogin($user);
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

function queueEmail($pdo, $userId, $eventId, $type, $subject, $message)
{
    $sender = getSetting($pdo, 'email_sender_name', 'Prestige Events');
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, event_id, type, channel, subject, message, status, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)');
    $stmt->execute([$userId, $eventId, $type, 'email', $subject, 'Отправитель: ' . $sender . "\r\n\r\n" . $message, 'pending', nowText()]);
}

function notify($pdo, $userId, $eventId, $type, $subject, $message, $withEmail = false)
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, event_id, type, channel, subject, message, status, is_read, created_at, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)');
    $stmt->execute([$userId, $eventId, $type, 'web', $subject, $message, 'sent', nowText(), nowText()]);
    if ($withEmail) {
        queueEmail($pdo, $userId, $eventId, $type, $subject, $message);
    }
}

function getSetting($pdo, $key, $defaultValue)
{
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $defaultValue : $value;
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
    return $free < 0 ? 0 : $free;
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
    notify($pdo, $first['user_id'], $eventId, 'waitlist', 'Освободилось место', 'Вы записаны на мероприятие «' . $event['title'] . '». Токен билета: ' . $token, true);
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
    return trim($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? ''));
}

function makeSlug($title)
{
    $map = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e', 'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya'];
    $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($title, $map)));
    $slug = trim($slug, '-');
    return mb_substr($slug === '' ? 'event' : $slug, 0, 140);
}

function checkinUrl($token)
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $path = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME'] ?? ''), '', $_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return $protocol . '://' . $host . $path . '/office.php?p=people&token=' . urlencode($token);
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

function eventById($pdo, $id)
{
    $stmt = $pdo->prepare('SELECT e.*, c.name AS category_name, v.name AS venue_name, v.address AS venue_address FROM events e INNER JOIN categories c ON c.id = e.category_id LEFT JOIN venues v ON v.id = e.venue_id WHERE e.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function showMessage($message, $error)
{
    if ($message) {
        echo '<div class="message">' . e($message) . '</div>';
    }
    if ($error) {
        echo '<div class="message error">' . e($error) . '</div>';
    }
}

function xlsxText($value)
{
    $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $value);
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsxCol($index)
{
    $letters = '';
    $number = $index + 1;
    while ($number > 0) {
        $number--;
        $letters = chr(65 + ($number % 26)) . $letters;
        $number = intdiv($number, 26);
    }
    return $letters;
}

function zipStore($files)
{
    $local = '';
    $central = '';
    $offset = 0;
    foreach ($files as $name => $data) {
        $crc = crc32($data);
        $size = strlen($data);
        $nameSize = strlen($name);
        $local .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, $nameSize, 0) . $name . $data;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $size, $size, $nameSize, 0, 0, 0, 0, 0, $offset) . $name;
        $offset += 30 + $nameSize + $size;
    }
    $count = count($files);
    return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $offset, 0);
}

function sendXlsx($filename, $sheetName, $headers, $rows)
{
    $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $files = [];
    $files['[Content_Types].xml'] = $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $files['_rels/.rels'] = $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $files['xl/workbook.xml'] = $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . xlsxText($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $files['xl/_rels/workbook.xml.rels'] = $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    $sheet = $head . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $lineNumber = 1;
    foreach (array_merge([$headers], $rows) as $line) {
        $sheet .= '<row r="' . $lineNumber . '">';
        $column = 0;
        foreach (array_values($line) as $value) {
            $sheet .= '<c r="' . xlsxCol($column) . $lineNumber . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxText($value) . '</t></is></c>';
            $column++;
        }
        $sheet .= '</row>';
        $lineNumber++;
    }
    $files['xl/worksheets/sheet1.xml'] = $sheet . '</sheetData></worksheet>';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo zipStore($files);
    exit;
}

function sendCsv($filename, $headers, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

function pageStart($title, $pdo, $user)
{
    $platform = getSetting($pdo, 'platform_title', 'Prestige Events');
    $unread = 0;
    if ($user) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$user['id']]);
        $unread = (int) $stmt->fetchColumn();
    }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e($platform) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
<div class="wrap top-grid">
<div class="meta"><span>Находится в</span><strong>Краснодар, ИМСИТ</strong></div>
<div class="meta"><span>Платформа</span><strong><?= e($platform) ?></strong></div>
<div class="meta"><span>Мероприятия</span><strong>Афиша и запись</strong></div>
<button class="menu-btn" id="menuBtn" type="button">Меню</button>
<nav class="nav" id="mainNav">
<a href="index.php">Афиша</a>
<?php if (!$user): ?>
<a href="account.php?p=login">Вход</a>
<a href="account.php?p=register">Регистрация</a>
<?php else: ?>
<a href="account.php?p=cabinet">Кабинет<span id="unreadCount"><?= $unread > 0 ? ' (' . $unread . ')' : '' ?></span></a>
<?php if ($user['role_code'] === 'organizer' || $user['role_code'] === 'admin'): ?>
<a href="office.php">Организатор</a>
<?php endif; ?>
<?php if ($user['role_code'] === 'admin'): ?>
<a href="admin.php">Админ</a>
<?php endif; ?>
<a href="account.php?p=logout">Выйти</a>
<?php endif; ?>
</nav>
</div>
<?php if ($user): ?>
<div class="wrap user-line"><?= e(fullName($user)) ?> · <?= e($user['role_name']) ?></div>
<?php endif; ?>
</header>
<main class="wrap">
<?php
}

function pageEnd()
{
?>
</main>
<footer class="footer">
<div class="wrap footer-grid">
<div class="footer-word">PRESTIGE</div>
<div>
<p>Prestige Events — платформа учёта мероприятий и участников.</p>
<p>ИМСИТ, Краснодар, <?= date('Y') ?></p>
</div>
<div>
<a href="index.php">Афиша</a>
<a href="account.php?p=login">Вход</a>
<a href="account.php?p=register">Регистрация</a>
</div>
</div>
</footer>
<script src="assets/app.js"></script>
</body>
</html>
<?php
}
