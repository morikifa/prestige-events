<?php

function event_seats_taken(int $eventId): int
{
    return (int) db_val(
        "SELECT COUNT(*) FROM registrations
         WHERE event_id = ? AND status IN ('registered', 'confirmed', 'attended')",
        [$eventId],
        0
    );
}

function event_seats(array $event): array
{
    $taken = event_seats_taken((int) $event['id']);
    $capacity = $event['capacity'] === null ? null : (int) $event['capacity'];
    $free = $capacity === null ? null : max(0, $capacity - $taken);
    $percent = ($capacity === null || $capacity === 0) ? 0 : min(100, (int) round($taken / $capacity * 100));

    return [
        'taken' => $taken,
        'capacity' => $capacity,
        'free' => $free,
        'is_full' => $capacity !== null && $taken >= $capacity,
        'percent' => $percent,
    ];
}

function event_active_registration(int $eventId, int $userId): ?array
{
    return db_one(
        "SELECT * FROM registrations
         WHERE event_id = ? AND user_id = ? AND status <> 'cancelled'",
        [$eventId, $userId]
    );
}

function event_waitlist_entry(int $eventId, int $userId): ?array
{
    return db_one(
        "SELECT * FROM waitlist
         WHERE event_id = ? AND user_id = ? AND status IN ('waiting', 'invited')",
        [$eventId, $userId]
    );
}

function qr_token_generate(): string
{
    $salt = setting('qr_token_salt', 'PrestigeSalt');
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $token = substr(hash('sha256', bin2hex(random_bytes(16)) . $salt), 0, 32);
        $exists = db_val('SELECT COUNT(*) FROM registrations WHERE qr_token = ?', [$token], 0);
        if ((int) $exists === 0) {
            return $token;
        }
    }
    return bin2hex(random_bytes(16));
}

function event_registration_open(array $event): bool
{
    if ($event['status'] !== 'published') {
        return false;
    }
    $now = time();
    if (!empty($event['registration_opens_at']) && strtotime($event['registration_opens_at']) > $now) {
        return false;
    }
    if (!empty($event['registration_closes_at']) && strtotime($event['registration_closes_at']) < $now) {
        return false;
    }
    return strtotime($event['starts_at']) > $now;
}

function event_register(int $eventId, int $userId): array
{
    $event = db_one('SELECT * FROM events WHERE id = ?', [$eventId]);
    if (!$event) {
        return ['error' => 'Мероприятие не найдено.'];
    }
    if (!event_registration_open($event)) {
        return ['error' => 'Регистрация на это мероприятие закрыта.'];
    }
    if (event_active_registration($eventId, $userId)) {
        return ['error' => 'Вы уже записаны на это мероприятие.'];
    }

    $seats = event_seats($event);
    $maxWaitlist = (int) setting('max_waitlist_size', '50');

    if ($seats['is_full']) {
        $entry = event_waitlist_entry($eventId, $userId);
        if ($entry) {
            return ['error' => 'Вы уже находитесь в листе ожидания.'];
        }
        $waitlistCount = (int) db_val(
            "SELECT COUNT(*) FROM waitlist WHERE event_id = ? AND status IN ('waiting', 'invited')",
            [$eventId],
            0
        );
        if ($waitlistCount >= $maxWaitlist) {
            return ['error' => 'Свободных мест и листа ожидания нет.'];
        }
        $position = (int) db_val('SELECT COALESCE(MAX(position), 0) + 1 FROM waitlist WHERE event_id = ?', [$eventId], 1);
        $previousEntry = db_one('SELECT id FROM waitlist WHERE event_id = ? AND user_id = ?', [$eventId, $userId]);
        if ($previousEntry) {
            db_exec(
                'UPDATE waitlist SET position = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$position, 'waiting', (int) $previousEntry['id']]
            );
        } else {
            db_exec(
                'INSERT INTO waitlist (event_id, user_id, position, status) VALUES (?, ?, ?, ?)',
                [$eventId, $userId, $position, 'waiting']
            );
        }
        notify(
            $userId,
            $eventId,
            'waitlist_added',
            'Вы в листе ожидания',
            'Мероприятие «' . $event['title'] . '» заполнено. Ваш номер в листе ожидания: ' . $position . '.'
        );
        log_action('waitlist_join', 'event', $eventId, ['position' => $position]);
        return ['waitlist' => true, 'position' => $position];
    }

    $token = qr_token_generate();
    $previousRegistration = db_one(
        'SELECT id FROM registrations WHERE event_id = ? AND user_id = ?',
        [$eventId, $userId]
    );
    if ($previousRegistration) {
        db_exec(
            "UPDATE registrations SET status = 'confirmed', qr_token = ?, cancelled_at = NULL, registered_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$token, (int) $previousRegistration['id']]
        );
    } else {
        db_exec(
            'INSERT INTO registrations (event_id, user_id, status, qr_token) VALUES (?, ?, ?, ?)',
            [$eventId, $userId, 'confirmed', $token]
        );
    }
    db_exec('UPDATE waitlist SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE event_id = ? AND user_id = ?', ['registered', $eventId, $userId]);
    notify(
        $userId,
        $eventId,
        'registration_confirmed',
        'Запись подтверждена',
        'Вы записаны на мероприятие «' . $event['title'] . '» (' . dt($event['starts_at']) . '). Билет с QR-кодом доступен в личном кабинете.'
    );
    log_action('registration_created', 'event', $eventId);

    return ['token' => $token];
}

function event_cancel(int $eventId, int $userId): array
{
    $registration = event_active_registration($eventId, $userId);
    if (!$registration) {
        return ['error' => 'Активная запись не найдена.'];
    }

    $event = db_one('SELECT * FROM events WHERE id = ?', [$eventId]);
    db_exec(
        "UPDATE registrations SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE id = ?",
        [(int) $registration['id']]
    );

    $waiting = db_one(
        "SELECT * FROM waitlist WHERE event_id = ? AND status = 'waiting' ORDER BY position LIMIT 1",
        [$eventId]
    );
    if ($waiting) {
        db_exec("UPDATE waitlist SET status = 'invited', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [(int) $waiting['id']]);
        notify(
            (int) $waiting['user_id'],
            $eventId,
            'waitlist_invited',
            'Появилось свободное место',
            'На мероприятии «' . ($event['title'] ?? '') . '» освободилось место. Запись снова открыта.'
        );
    }

    notify(
        $userId,
        $eventId,
        'registration_cancelled',
        'Запись отменена',
        'Ваша запись на мероприятие «' . ($event['title'] ?? '') . '» отменена.'
    );
    log_action('registration_cancelled', 'event', $eventId);

    return ['ok' => true];
}

function waitlist_leave(int $eventId, int $userId): array
{
    $entry = event_waitlist_entry($eventId, $userId);
    if (!$entry) {
        return ['error' => 'Запись в листе ожидания не найдена.'];
    }
    db_exec("UPDATE waitlist SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?", [(int) $entry['id']]);
    log_action('waitlist_leave', 'event', $eventId);
    return ['ok' => true];
}

function event_mark_attended(string $token, array $actor): array
{
    $registration = db_one(
        'SELECT r.*, e.title AS event_title, e.organizer_id
         FROM registrations r
         JOIN events e ON e.id = r.event_id
         WHERE r.qr_token = ?',
        [$token]
    );

    if (!$registration) {
        return ['error' => 'Токен не найден. Проверьте код билета.'];
    }
    if ((int) $registration['organizer_id'] !== (int) $actor['id'] && $actor['role_code'] !== 'admin') {
        return ['error' => 'Мероприятие принадлежит другому организатору.'];
    }
    if ($registration['status'] === 'attended') {
        return ['error' => 'Посещение уже отмечено (' . dt($registration['attended_at']) . ').'];
    }
    if ($registration['status'] === 'cancelled') {
        return ['error' => 'Запись отменена, отметить посещение нельзя.'];
    }

    db_exec(
        "UPDATE registrations SET status = 'attended', attended_at = CURRENT_TIMESTAMP WHERE id = ?",
        [(int) $registration['id']]
    );
    log_action('attendance_marked', 'registration', (int) $registration['id'], ['event_id' => (int) $registration['event_id']]);

    return ['registration' => $registration];
}

function event_program(int $eventId): array
{
    return db_all(
        'SELECT * FROM event_program WHERE event_id = ? ORDER BY sort_order, starts_at, id',
        [$eventId]
    );
}

function events_for_afisha(array $filters = []): array
{
    $sql = "SELECT e.*, c.name AS category_name, v.name AS venue_name, u.last_name AS organizer_last, u.first_name AS organizer_first,
                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('registered', 'confirmed', 'attended')) AS taken
            FROM events e
            JOIN categories c ON c.id = e.category_id
            LEFT JOIN venues v ON v.id = e.venue_id
            JOIN users u ON u.id = e.organizer_id
            WHERE e.status = 'published'";
    $params = [];

    if (!empty($filters['category_id'])) {
        $sql .= ' AND e.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['venue_id'])) {
        $sql .= ' AND e.venue_id = ?';
        $params[] = (int) $filters['venue_id'];
    }
    if (!empty($filters['date'])) {
        $sql .= ' AND substr(e.starts_at, 1, 10) = ?';
        $params[] = $filters['date'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (e.title LIKE ? OR e.description LIKE ?)';
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }

    $sql .= ' ORDER BY e.starts_at ASC';

    return db_all($sql, $params);
}

function events_for_manager(array $user, array $filters = []): array
{
    $sql = "SELECT e.*, c.name AS category_name, v.name AS venue_name,
                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('registered', 'confirmed', 'attended')) AS taken,
                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'attended') AS attended
            FROM events e
            JOIN categories c ON c.id = e.category_id
            LEFT JOIN venues v ON v.id = e.venue_id
            WHERE 1 = 1";
    $params = [];

    if ($user['role_code'] !== 'admin') {
        $sql .= ' AND e.organizer_id = ?';
        $params[] = (int) $user['id'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND e.status = ?';
        $params[] = $filters['status'];
    }

    $sql .= ' ORDER BY e.starts_at DESC';

    return db_all($sql, $params);
}

function event_can_manage(array $event, array $user): bool
{
    return $user['role_code'] === 'admin' || (int) $event['organizer_id'] === (int) $user['id'];
}

function event_organizer_options(): array
{
    return db_all(
        "SELECT u.id, u.last_name, u.first_name, r.code AS role_code
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.code IN ('organizer', 'admin') AND u.status = 'active'
         ORDER BY u.last_name, u.first_name"
    );
}

function event_stats(array $user): array
{
    $scopeSql = '';
    $params = [];
    if ($user['role_code'] !== 'admin') {
        $scopeSql = ' AND e.organizer_id = ?';
        $params[] = (int) $user['id'];
    }

    $events = (int) db_val('SELECT COUNT(*) FROM events e WHERE 1 = 1' . $scopeSql, $params, 0);
    $published = (int) db_val("SELECT COUNT(*) FROM events e WHERE e.status = 'published'" . $scopeSql, $params, 0);
    $registrations = (int) db_val(
        'SELECT COUNT(*) FROM registrations r JOIN events e ON e.id = r.event_id
         WHERE r.status IN (?, ?, ?)' . $scopeSql,
        array_merge(['registered', 'confirmed', 'attended'], $params),
        0
    );
    $attended = (int) db_val(
        'SELECT COUNT(*) FROM registrations r JOIN events e ON e.id = r.event_id
         WHERE r.status = ?' . $scopeSql,
        array_merge(['attended'], $params),
        0
    );

    return [
        'events' => $events,
        'published' => $published,
        'registrations' => $registrations,
        'attended' => $attended,
        'attendance_rate' => $registrations > 0 ? (int) round($attended / $registrations * 100) : 0,
    ];
}

function events_report_rows(array $user): array
{
    $sql = "SELECT e.id, e.title, e.starts_at, e.status, e.capacity,
                   c.name AS category_name,
                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status IN ('registered', 'confirmed')) AS registered,
                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'attended') AS attended,
                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'cancelled') AS cancelled,
                   (SELECT COUNT(*) FROM waitlist w WHERE w.event_id = e.id AND w.status IN ('waiting', 'invited')) AS waiting
            FROM events e
            JOIN categories c ON c.id = e.category_id
            WHERE 1 = 1";
    $params = [];
    if ($user['role_code'] !== 'admin') {
        $sql .= ' AND e.organizer_id = ?';
        $params[] = (int) $user['id'];
    }
    $sql .= ' ORDER BY e.starts_at DESC';
    return db_all($sql, $params);
}

function events_by_category_report(): array
{
    return db_all(
        "SELECT c.name AS category_name, COUNT(e.id) AS events_count,
                (SELECT COUNT(*) FROM registrations r JOIN events e2 ON e2.id = r.event_id
                 WHERE e2.category_id = c.id AND r.status IN ('registered', 'confirmed', 'attended')) AS registrations
         FROM categories c
         LEFT JOIN events e ON e.category_id = c.id
         GROUP BY c.id, c.name
         ORDER BY events_count DESC, c.name"
    );
}

function event_unique_slug(string $source, ?int $excludeId = null): string
{
    $base = slugify($source);
    $slug = $base;
    $index = 2;
    while (true) {
        $params = [$slug];
        $sql = 'SELECT COUNT(*) FROM events WHERE slug = ?';
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $exists = (int) db_val($sql, $params, 0);
        if ($exists === 0) {
            return $slug;
        }
        $slug = $base . '-' . $index;
        $index++;
    }
}

function event_load(int $eventId): ?array
{
    return db_one(
        'SELECT e.*, c.name AS category_name, v.name AS venue_name
         FROM events e
         JOIN categories c ON c.id = e.category_id
         LEFT JOIN venues v ON v.id = e.venue_id
         WHERE e.id = ?',
        [$eventId]
    );
}

function normalize_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime(str_replace('T', ' ', $value));
    return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
}
