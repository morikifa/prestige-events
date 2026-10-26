<section class="auth">
    <p class="label">Новая учётная запись</p>
    <h1 class="auth-title">Регистрация</h1>
    <p class="auth-hint">Учётная запись создаётся с ролью «Участник». Роль изменяет только администратор платформы.</p>

    <?php if ($errors): ?>
        <div class="alert">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="form" method="post" action="<?= e(url('register')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label class="label" for="last_name">Фамилия</label>
            <input type="text" id="last_name" name="last_name" value="<?= e($values['last_name']) ?>" required>
        </div>
        <div class="field">
            <label class="label" for="first_name">Имя</label>
            <input type="text" id="first_name" name="first_name" value="<?= e($values['first_name']) ?>" required>
        </div>
        <div class="field">
            <label class="label" for="middle_name">Отчество</label>
            <input type="text" id="middle_name" name="middle_name" value="<?= e($values['middle_name']) ?>">
        </div>
        <div class="field">
            <label class="label" for="email">Электронная почта</label>
            <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" autocomplete="username" required>
        </div>
        <div class="field">
            <label class="label" for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" value="<?= e($values['phone']) ?>" placeholder="+7 900 000-00-00">
        </div>
        <div class="field">
            <label class="label" for="password">Пароль</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
            <span class="hint">Не менее 8 символов.</span>
        </div>
        <div class="field">
            <label class="label" for="password_repeat">Повторите пароль</label>
            <input type="password" id="password_repeat" name="password_repeat" autocomplete="new-password" required>
        </div>
        <div class="field-inline">
            <input type="checkbox" id="consent" name="consent" value="1" style="width: 16px; height: 16px;">
            <label class="form-note" for="consent">Согласен на обработку персональных данных (152-ФЗ)</label>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Создать кабинет</button>
            <a class="form-note" href="<?= e(url('login')) ?>">У меня уже есть учётная запись</a>
        </div>
    </form>
</section>
