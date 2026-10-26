<?php
$nextTheme = $theme === 'light' ? 'dark' : 'light';
?>
<header class="header">
    <div class="header-inner">
        <div class="meta-grid">
            <div class="meta">
                <span class="label">Проект</span>
                <a class="meta-value" href="<?= e(url('')) ?>">Prestige Events</a>
            </div>
            <div class="meta">
                <span class="label">Расположение</span>
                <span class="meta-value">Краснодар, Россия</span>
            </div>
            <div class="meta">
                <span class="label">Связаться</span>
                <a class="meta-value" href="mailto:org@imsit.ru">org@imsit.ru</a>
            </div>
            <div class="meta meta-theme">
                <span class="label">Тема</span>
                <form method="post" action="<?= e(url('theme')) ?>" class="theme-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="theme" value="<?= e($nextTheme) ?>">
                    <button class="link-button" type="submit"><?= $nextTheme === 'light' ? 'Светлая' : 'Тёмная' ?></button>
                </form>
            </div>
        </div>

        <nav class="nav" aria-label="Основная навигация">
            <?= nav_link('', 'Афиша', $navActive, 'home') ?>
            <?php if ($currentUser): ?>
                <?= nav_link('account', 'Кабинет', $navActive, 'account') ?>
                <?php if (user_has_role('organizer')): ?>
                    <?= nav_link('manage/events', 'Управление', $navActive, 'manage') ?>
                <?php endif; ?>
                <?php if (user_has_role('admin')): ?>
                    <?= nav_link('admin/users', 'Админ', $navActive, 'admin') ?>
                <?php endif; ?>
                <a href="<?= e(url('logout')) ?>" class="nav-exit">Выйти</a>
            <?php else: ?>
                <?= nav_link('login', 'Войти', $navActive, 'login') ?>
            <?php endif; ?>
        </nav>
    </div>

    <?php if ($currentUser): ?>
        <div class="header-user">
            <span class="label">Вы вошли как</span>
            <span class="header-user-name"><?= e(user_full_name($currentUser)) ?></span>
            <span class="tag"><?= e($currentUser['role_name']) ?></span>
        </div>
    <?php endif; ?>
</header>
