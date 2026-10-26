<?php
$title = $title ?? config('app.name');
$currentUser = $currentUser ?? current_user();
$theme = $theme ?? 'dark';
$flashes = $flashes ?? [];
$bodyClass = 'theme-' . ($theme === 'light' ? 'light' : 'dark');
?>
<!DOCTYPE html>
<html lang="ru" class="<?= e($bodyClass) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e(config('app.name')) ?></title>
    <meta name="description" content="Prestige Events — платформа учёта мероприятий и участников колледжа ИМСИТ.">
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%23141414'/%3E%3Ctext x='16' y='23' font-family='Helvetica, Arial' font-size='18' fill='%23EDEBE7' text-anchor='middle'%3EP%3C/text%3E%3C/svg%3E">
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержанию</a>
<div class="page">
    <?php partial('header', ['currentUser' => $currentUser, 'theme' => $theme, 'navActive' => $navActive]); ?>

    <main id="main" class="main">
        <?php if ($flashes): ?>
            <div class="flash-stack">
                <?php foreach ($flashes as $flash): ?>
                    <div class="flash flash-<?= e($flash['type']) ?>">
                        <span class="flash-dot" aria-hidden="true"></span>
                        <span><?= e($flash['message']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>

    <?php partial('footer'); ?>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
