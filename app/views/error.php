<section class="error-page">
    <p class="label">Ошибка <?= (int) $code ?></p>
    <div class="error-code"><?= (int) $code ?></div>
    <p class="error-text"><?= e($message !== '' ? $message : 'Раздел недоступен.') ?></p>
    <div class="form-actions mt-40">
        <a class="btn" href="<?= e(url('')) ?>">На главную</a>
        <?php if (!current_user()): ?>
            <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Войти</a>
        <?php endif; ?>
    </div>
</section>
