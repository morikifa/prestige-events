<?php

function config(string $key, $default = null)
{
    $value = $GLOBALS['prestige_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = '', array $params = []): string
{
    $query = [];
    if ($route !== '') {
        $query['r'] = $route;
    }
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $query[$key] = $value;
        }
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    return $script . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $path): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $route = '', array $params = []): void
{
    header('Location: ' . url($route, $params));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function csrf_token(): string
{
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        flash('error', 'Сессия устарела. Повторите действие.');
        redirect('login');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_pull(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value, description, updated_at FROM settings') as $row) {
            $cache[$row['setting_key']] = $row;
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) ? (string) $all[$key]['setting_value'] : $default;
}

function save_setting(string $key, string $value): void
{
    $exists = db_val('SELECT COUNT(*) FROM settings WHERE setting_key = ?', [$key]);
    if ($exists) {
        db_exec('UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?', [$value, $key]);
    } else {
        db_exec('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
    }
    settings_all(true);
}

function months_ru(): array
{
    return [
        1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
    ];
}

function dt($value, bool $withTime = true): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '—';
    }
    $months = months_ru();
    $text = date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
    if ($withTime) {
        $text .= ', ' . date('H:i', $timestamp);
    }
    return $text;
}

function dt_short($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $timestamp = strtotime((string) $value);
    $months = months_ru();
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' · ' . date('H:i', $timestamp);
}

function dt_time($value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('H:i', $timestamp) : '—';
}

function dt_day($value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('j', $timestamp) : '—';
}

function dt_month_short($value): string
{
    $months = [1 => 'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    $timestamp = strtotime((string) $value);
    return $timestamp ? $months[(int) date('n', $timestamp)] : '—';
}

function dt_full($value): string
{
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '—';
    }
    $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function seat_label(array $event): string
{
    if ($event['capacity'] === null) {
        return 'Свободный вход';
    }
    $taken = isset($event['taken']) ? (int) $event['taken'] : event_seats_taken((int) $event['id']);
    $free = max(0, (int) $event['capacity'] - $taken);
    if ($free === 0) {
        return 'Мест нет';
    }
    return 'Свободно ' . $free . ' ' . plural($free, ['место', 'места', 'мест']);
}

function seat_percent(array $event): int
{
    if ($event['capacity'] === null) {
        return 0;
    }
    $taken = isset($event['taken']) ? (int) $event['taken'] : event_seats_taken((int) $event['id']);
    return min(100, (int) round($taken / max(1, (int) $event['capacity']) * 100));
}

function plural(int $count, array $forms): string
{
    $abs = abs($count) % 100;
    $last = $abs % 10;
    if ($abs > 10 && $abs < 20) {
        return $forms[2];
    }
    if ($last > 1 && $last < 5) {
        return $forms[1];
    }
    if ($last === 1) {
        return $forms[0];
    }
    return $forms[2];
}

function event_status_labels(): array
{
    return [
        'draft' => 'Черновик',
        'moderation' => 'На модерации',
        'published' => 'Опубликовано',
        'cancelled' => 'Отменено',
        'completed' => 'Завершено',
    ];
}

function event_status_label(string $status): string
{
    $labels = event_status_labels();
    return $labels[$status] ?? $status;
}

function registration_status_labels(): array
{
    return [
        'registered' => 'Записан',
        'confirmed' => 'Подтверждён',
        'attended' => 'Посетил',
        'cancelled' => 'Отменён',
    ];
}

function registration_status_label(string $status): string
{
    $labels = registration_status_labels();
    return $labels[$status] ?? $status;
}

function waitlist_status_labels(): array
{
    return [
        'waiting' => 'Ожидает',
        'invited' => 'Приглашён',
        'registered' => 'Записан',
        'rejected' => 'Отклонён',
    ];
}

function waitlist_status_label(string $status): string
{
    $labels = waitlist_status_labels();
    return $labels[$status] ?? $status;
}

function user_status_labels(): array
{
    return [
        'active' => 'Активен',
        'blocked' => 'Заблокирован',
        'pending' => 'Ожидает подтверждения',
    ];
}

function user_status_label(string $status): string
{
    $labels = user_status_labels();
    return $labels[$status] ?? $status;
}

function slugify(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'event-' . date('YmdHis');
    }
    return mb_substr($text, 0, 140, 'UTF-8');
}

function log_action(string $action, string $entity, ?int $entityId = null, array $details = []): void
{
    $user = current_user();
    db_exec(
        'INSERT INTO action_log (user_id, action, entity, entity_id, details, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $user['id'] ?? null,
            $action,
            $entity,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]
    );
}

function notify(int $userId, ?int $eventId, string $type, string $subject, string $message): void
{
    db_exec(
        'INSERT INTO notifications (user_id, event_id, type, channel, subject, message, status, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
        [$userId, $eventId, $type, 'web', $subject, $message, 'sent']
    );
}

function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    $texts = [
        403 => 'Доступ запрещён',
        404 => 'Страница не найдена',
    ];
    render('error', [
        'title' => $texts[$code] ?? 'Ошибка',
        'code' => $code,
        'message' => $message,
    ]);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function dt_input($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}
