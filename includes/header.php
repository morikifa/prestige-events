<?php
$headerTitle = $pageTitle ?? 'Prestige Events';
$headerUser = $user ?? null;
$unreadCount = 0;
if ($headerUser && isset($pdo)) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$headerUser['id']]);
    $unreadCount = (int) $stmt->fetchColumn();
}
$platformTitle = isset($pdo) ? getSetting($pdo, 'platform_title', 'Prestige Events') : 'Prestige Events';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($headerTitle) ?> — <?= e($platformTitle) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
<div class="wrap top-grid">
<div class="meta"><span>Находится в</span><strong>Краснодар, ИМСИТ</strong></div>
<div class="meta"><span>Платформа</span><strong><?= e($platformTitle) ?></strong></div>
<div class="meta"><span>Мероприятия</span><strong>Афиша и запись</strong></div>
<button class="menu-btn" id="menuBtn" type="button">Меню</button>
<nav class="nav" id="mainNav">
<a href="index.php">Афиша</a>
<?php if (!$headerUser): ?>
<a href="login.php">Вход</a>
<a href="register.php">Регистрация</a>
<?php else: ?>
<a href="cabinet.php">Кабинет<?php if ($unreadCount > 0): ?> (<?= $unreadCount ?>)<?php endif; ?></a>
<?php if ($headerUser['role_code'] === 'organizer' || $headerUser['role_code'] === 'admin'): ?>
<a href="organizer.php">Организатор</a>
<a href="reports.php">Отчёты</a>
<?php endif; ?>
<?php if ($headerUser['role_code'] === 'admin'): ?>
<a href="admin.php">Админ</a>
<?php endif; ?>
<a href="logout.php">Выйти</a>
<?php endif; ?>
</nav>
</div>
<?php if ($headerUser): ?>
<div class="wrap user-line"><?= e(fullName($headerUser)) ?> · <?= e($headerUser['role_name']) ?></div>
<?php endif; ?>
</header>
<main class="wrap">
