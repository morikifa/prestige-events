<?php

function role_levels(): array
{
    return ['guest' => 0, 'participant' => 1, 'organizer' => 2, 'admin' => 3];
}

function role_level(?string $code): int
{
    $levels = role_levels();
    return $levels[$code] ?? 0;
}

function roles_map(): array
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db_all('SELECT id, code, name FROM roles ORDER BY id') as $role) {
            $map[$role['id']] = $role;
        }
    }
    return $map;
}

function current_user(bool $refresh = false): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded && !$refresh) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        $user = null;
        return null;
    }

    $row = db_one(
        'SELECT u.*, r.code AS role_code, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?',
        [(int) $_SESSION['user_id']]
    );

    if (!$row || $row['status'] !== 'active') {
        unset($_SESSION['user_id']);
        $user = null;
        return null;
    }

    $user = $row;
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_has_role(string $role): bool
{
    $user = current_user();
    if (!$user) {
        return $role === 'guest';
    }
    return role_level($user['role_code']) >= role_level($role);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Войдите в личный кабинет, чтобы продолжить.');
        redirect('login', ['next' => $_GET['r'] ?? '']);
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if (!user_has_role($role)) {
        abort(403, 'У вашей учётной записи нет прав на этот раздел.');
    }
    return $user;
}

function login_blocked_for(): int
{
    $state = $_SESSION['login_state'] ?? ['count' => 0, 'until' => 0];
    if ($state['count'] >= config('security.max_login_attempts') && $state['until'] > time()) {
        return $state['until'] - time();
    }
    return 0;
}

function login_attempt_register_failure(): void
{
    $state = $_SESSION['login_state'] ?? ['count' => 0, 'until' => 0];
    if ($state['until'] < time()) {
        $state = ['count' => 0, 'until' => 0];
    }
    $state['count']++;
    if ($state['count'] >= config('security.max_login_attempts')) {
        $state['until'] = time() + (int) config('security.lockout_minutes') * 60;
        $state['count'] = 0;
    }
    $_SESSION['login_state'] = $state;
}

function login_attempt_reset(): void
{
    unset($_SESSION['login_state']);
}

function authenticate(string $login, string $password): array
{
    $login = mb_strtolower(trim($login), 'UTF-8');

    if ($login === '' || $password === '') {
        return ['error' => 'Введите логин и пароль.'];
    }

    $wait = login_blocked_for();
    if ($wait > 0) {
        return ['error' => 'Слишком много попыток входа. Повторите через ' . ceil($wait / 60) . ' мин.'];
    }

    if (strpos($login, '@') === false) {
        $matches = db_all('SELECT LOWER(email) AS email FROM users WHERE LOWER(email) LIKE ?', [$login . '@%']);
        if (count($matches) === 1) {
            $login = $matches[0]['email'];
        }
    }

    $row = db_one(
        'SELECT u.*, r.code AS role_code
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE LOWER(u.email) = ?',
        [$login]
    );

    if (!$row || !password_verify($password, $row['password_hash'])) {
        login_attempt_register_failure();
        return ['error' => 'Неверный логин или пароль.'];
    }

    if ($row['status'] === 'blocked') {
        return ['error' => 'Учётная запись заблокирована администратором.'];
    }

    if ($row['status'] === 'pending') {
        return ['error' => 'Учётная запись ожидает подтверждения администратором.'];
    }

    login_attempt_reset();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
    current_user(true);

    log_action('login', 'user', (int) $row['id']);

    return ['user' => $row];
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        log_action('logout', 'user', (int) $user['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function user_full_name(array $user): string
{
    $parts = [$user['last_name'], $user['first_name']];
    if (!empty($user['middle_name'])) {
        $parts[] = $user['middle_name'];
    }
    return implode(' ', $parts);
}

function user_short_name(array $user): string
{
    $initials = mb_substr($user['first_name'], 0, 1, 'UTF-8');
    if (!empty($user['middle_name'])) {
        $initials .= mb_substr($user['middle_name'], 0, 1, 'UTF-8');
    }
    return $user['last_name'] . ' ' . $initials . '.';
}

function home_route_for(array $user): string
{
    if ($user['role_code'] === 'admin') {
        return 'admin/users';
    }
    if ($user['role_code'] === 'organizer') {
        return 'manage/events';
    }
    return 'account';
}
