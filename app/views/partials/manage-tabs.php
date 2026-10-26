<nav class="tabs" aria-label="Разделы организатора">
    <a class="<?= $active === 'events' ? 'is-active' : '' ?>" href="<?= e(url('manage/events')) ?>">Мероприятия</a>
    <a class="<?= $active === 'reports' ? 'is-active' : '' ?>" href="<?= e(url('manage/reports')) ?>">Отчёты</a>
    <a class="<?= $active === 'attend' ? 'is-active' : '' ?>" href="<?= e(url('attend')) ?>">Отметка посещения</a>
    <?php if (user_has_role('admin')): ?>
        <a href="<?= e(url('admin/users')) ?>">Администрирование</a>
    <?php endif; ?>
</nav>
