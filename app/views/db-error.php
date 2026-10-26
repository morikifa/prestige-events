<?php $message = $dbError ?? 'Неизвестная ошибка подключения'; ?>
<!DOCTYPE html>
<html lang="ru" class="theme-light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Нет подключения к базе данных — Prestige Events</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body>
<div class="page">
    <main class="main">
        <section class="db-error">
            <p class="label">Ошибка базы данных</p>
            <h1>Приложение не смогло подключиться к базе</h1>
            <p class="page-sub">Проверьте, что сервер MySQL запущен, база <span class="mono">prestige_events</span> создана, а логин и пароль в файле <span class="mono">app/config.php</span> верны.</p>

            <pre><?= e($message) ?></pre>

            <ol class="steps-list">
                <li>Запустите MySQL в панели OpenServer, XAMPP или через службы Windows.</li>
                <li>Импортируйте <span class="mono">database/schema.sql</span>, затем <span class="mono">database/seed.sql</span>.</li>
                <li>Проверьте параметры подключения в <span class="mono">app/config.php</span>.</li>
                <li>Обновите страницу.</li>
            </ol>

            <div class="form-actions mt-40">
                <a class="btn" href="">Обновить страницу</a>
            </div>
        </section>
    </main>
</div>
</body>
</html>
