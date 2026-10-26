<header class="page-head">
    <div>
        <p class="label">Личный кабинет</p>
        <h1 class="page-title"><?= e(user_full_name($user)) ?></h1>
        <p class="page-sub"><?= e($user['email']) ?> · <?= e($user['role_name']) ?></p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('account/events')) ?>">Мои мероприятия</a>
</header>

<?php partial('account-tabs', ['active' => 'account']); ?>

<div class="stat-grid">
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['registrations'] ?></span>
        <span class="stat-label">активных записей</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['attended'] ?></span>
        <span class="stat-label">посещено мероприятий</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['unread'] ?></span>
        <span class="stat-label">непрочитанных уведомлений</span>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Профиль</h2>
        <span class="section-count">Данные для связи</span>
    </div>

    <form class="form" method="post" action="<?= e(url('account')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="form-grid">
            <div class="field">
                <label class="label" for="last_name">Фамилия</label>
                <input type="text" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="first_name">Имя</label>
                <input type="text" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="middle_name">Отчество</label>
                <input type="text" id="middle_name" name="middle_name" value="<?= e($user['middle_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="label" for="phone">Телефон</label>
                <input type="text" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="field span-2">
                <label class="label" for="email">Электронная почта (логин)</label>
                <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
                <span class="hint">Используется для входа в личный кабинет.</span>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Сохранить</button>
            <span class="form-note">Роль «<?= e($user['role_name']) ?>» назначается администратором.</span>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Смена пароля</h2>
    </div>

    <form class="form" method="post" action="<?= e(url('account')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="form-grid">
            <div class="field span-2">
                <label class="label" for="current_password">Текущий пароль</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label class="label" for="new_password">Новый пароль</label>
                <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
            </div>
            <div class="field">
                <label class="label" for="repeat_password">Повторите пароль</label>
                <input type="password" id="repeat_password" name="repeat_password" autocomplete="new-password" required>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-ghost" type="submit">Обновить пароль</button>
        </div>
    </form>
</section>
