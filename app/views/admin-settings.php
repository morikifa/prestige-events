<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Настройки</h1>
        <p class="page-sub">Параметры платформы: название, лимит листа ожидания, соль для токенов QR, текст уведомления.</p>
    </div>
</header>

<?php partial('admin-tabs', ['active' => 'settings']); ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Параметры</h2>
        <span class="section-count"><?= count($settings) ?> <?= plural(count($settings), ['запись', 'записи', 'записей']) ?></span>
    </div>

    <form class="form" method="post" action="<?= e(url('admin/settings')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <?php foreach ($settings as $key => $setting): ?>
            <div class="field-row">
                <div class="field">
                    <label class="label" for="setting-<?= e($key) ?>"><?= e($key) ?></label>
                    <?php if (mb_strlen((string) $setting['setting_value'], 'UTF-8') > 80): ?>
                        <textarea id="setting-<?= e($key) ?>" name="settings[<?= e($key) ?>]" rows="3"><?= e($setting['setting_value']) ?></textarea>
                    <?php else: ?>
                        <input type="text" id="setting-<?= e($key) ?>" name="settings[<?= e($key) ?>]" value="<?= e($setting['setting_value']) ?>">
                    <?php endif; ?>
                    <span class="hint"><?= e($setting['description'] ?? '') ?></span>
                </div>
                <div class="field" style="justify-content: flex-end;">
                    <span class="hint">Изменён: <?= e(dt($setting['updated_at'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button class="btn" type="submit">Сохранить параметры</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Новый параметр</h2>
    </div>

    <form class="form" method="post" action="<?= e(url('admin/settings')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="field">
                <label class="label" for="setting_key">Ключ</label>
                <input type="text" id="setting_key" name="setting_key" placeholder="contact_email" required>
            </div>
            <div class="field">
                <label class="label" for="description">Описание</label>
                <input type="text" id="description" name="description" placeholder="Почта для связи">
            </div>
            <div class="field span-2">
                <label class="label" for="setting_value">Значение</label>
                <input type="text" id="setting_value" name="setting_value" required>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-ghost" type="submit">Добавить параметр</button>
        </div>
    </form>
</section>
