<section class="auth">
    <p class="label">Личный кабинет</p>
    <h1 class="auth-title">Вход</h1>
    <p class="auth-hint">Войдите по адресу электронной почты и паролю. Можно указать короткий логин — часть адреса до знака @.</p>

    <?php if (!empty($error)): ?>
        <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('login')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="email">Электронная почта или логин</label>
            <input type="text" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
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
            admin / admin1234 — администратор<br>
            organizer / org1234 — организатор<br>
            user / user1234 — участник
        </p>
    </div>
</section>
