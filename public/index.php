<?php

require dirname(__DIR__) . '/app/bootstrap.php';

$routes = [
    '' => ['pages/home.php', 'guest'],
    'event' => ['pages/event.php', 'guest'],
    'login' => ['pages/login.php', 'guest'],
    'register' => ['pages/register.php', 'guest'],
    'logout' => ['pages/logout.php', 'guest'],
    'theme' => ['pages/theme.php', 'guest'],
    'attend' => ['pages/attend.php', 'organizer'],
    'account' => ['pages/account.php', 'participant'],
    'account/events' => ['pages/account-events.php', 'participant'],
    'account/notifications' => ['pages/account-notifications.php', 'participant'],
    'manage/events' => ['pages/manage-events.php', 'organizer'],
    'manage/event' => ['pages/manage-event-edit.php', 'organizer'],
    'manage/program' => ['pages/manage-program.php', 'organizer'],
    'manage/participants' => ['pages/manage-participants.php', 'organizer'],
    'manage/export' => ['pages/manage-export.php', 'organizer'],
    'manage/reports' => ['pages/manage-reports.php', 'organizer'],
    'admin/users' => ['pages/admin-users.php', 'admin'],
    'admin/catalogs' => ['pages/admin-catalogs.php', 'admin'],
    'admin/events' => ['pages/admin-events.php', 'admin'],
    'admin/settings' => ['pages/admin-settings.php', 'admin'],
    'admin/log' => ['pages/admin-log.php', 'admin'],
    'admin/reports' => ['pages/admin-reports.php', 'admin'],
    'admin/backup' => ['pages/admin-backup.php', 'admin'],
];

$route = isset($_GET['r']) ? trim((string) $_GET['r'], '/') : '';

try {
    if (!isset($routes[$route])) {
        abort(404);
    }

    [$page, $requiredRole] = $routes[$route];

    if (is_post()) {
        csrf_verify();
    }

    if ($requiredRole !== 'guest') {
        require_role($requiredRole);
    }

    require PRESTIGE_APP . '/' . $page;
} catch (PDOException $exception) {
    http_response_code(500);
    $dbError = $exception->getMessage();
    include PRESTIGE_APP . '/views/db-error.php';
}
