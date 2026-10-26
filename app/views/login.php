<section class="auth">
    <p class="label">Личный кабинет</p>
    <h1 class="auth-title">Вход</h1>
    <p class="auth-hint">Войдите по адресу электронной почты и паролю. Интерфейс откроется в соответствии с вашей ролью.</p>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('login')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="email">Электронная почта</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label class="label" for="password">Пароль</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Войти</button>
            <a class="form-note" href="<?= e(url('register')) ?>">Создать учётную запись</a>
        </div>
    </form>

    <div class="auth-alt">
        <p class="label">Демонстрационные учётные записи</p>
        <p class="form-note">
            admin@prestige.ru / admin1234 — администратор<br>
            organizer@prestige.ru / org1234 — организатор<br>
            user@prestige.ru / user1234 — участник
        </p>
    </div>
</section>
