<nav class="tabs" aria-label="Разделы администратора">
    <a class="<?= $active === 'users' ? 'is-active' : '' ?>" href="<?= e(url('admin/users')) ?>">Пользователи</a>
    <a class="<?= $active === 'catalogs' ? 'is-active' : '' ?>" href="<?= e(url('admin/catalogs')) ?>">Справочники</a>
    <a class="<?= $active === 'events' ? 'is-active' : '' ?>" href="<?= e(url('admin/events')) ?>">Мероприятия</a>
    <a class="<?= $active === 'reports' ? 'is-active' : '' ?>" href="<?= e(url('admin/reports')) ?>">Отчёты</a>
    <a class="<?= $active === 'settings' ? 'is-active' : '' ?>" href="<?= e(url('admin/settings')) ?>">Настройки</a>
    <a class="<?= $active === 'log' ? 'is-active' : '' ?>" href="<?= e(url('admin/log')) ?>">Журнал</a>
    <a class="<?= $active === 'backup' ? 'is-active' : '' ?>" href="<?= e(url('admin/backup')) ?>">Резервные копии</a>
</nav>
