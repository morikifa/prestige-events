<?php

$user = current_user();

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'profile') {
        $lastName = (string) input('last_name');
        $firstName = (string) input('first_name');
        $middleName = (string) input('middle_name');
        $phone = (string) input('phone');
        $email = mb_strtolower((string) input('email'), 'UTF-8');

        if ($lastName === '' || $firstName === '') {
            flash('error', 'Фамилия и имя обязательны.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Укажите корректный адрес электронной почты.');
        } else {
            $busy = db_val('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id <> ?', [$email, (int) $user['id']], 0);
            if ((int) $busy > 0) {
                flash('error', 'Этот адрес уже занят другим пользователем.');
            } else {
                db_exec(
                    'UPDATE users SET last_name = ?, first_name = ?, middle_name = ?, phone = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [
                        $lastName,
                        $firstName,
                        $middleName !== '' ? $middleName : null,
                        $phone !== '' ? $phone : null,
                        $email,
                        (int) $user['id'],
                    ]
                );
                log_action('profile_updated', 'user', (int) $user['id']);
                flash('success', 'Данные профиля сохранены.');
            }
        }
        redirect('account');
    }

    if ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $repeatPassword = (string) ($_POST['repeat_password'] ?? '');

        if (!password_verify($currentPassword, $user['password_hash'])) {
            flash('error', 'Текущий пароль указан неверно.');
        } elseif (mb_strlen($newPassword, 'UTF-8') < 8) {
            flash('error', 'Новый пароль должен содержать не менее 8 символов.');
        } elseif ($newPassword !== $repeatPassword) {
            flash('error', 'Новые пароли не совпадают.');
        } else {
            db_exec('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [
                password_hash($newPassword, PASSWORD_DEFAULT),
                (int) $user['id'],
            ]);
            log_action('password_changed', 'user', (int) $user['id']);
            flash('success', 'Пароль обновлён.');
        }
        redirect('account');
    }
}

$user = current_user(true);

$stats = [
    'registrations' => (int) db_val(
        "SELECT COUNT(*) FROM registrations WHERE user_id = ? AND status IN ('registered', 'confirmed', 'attended')",
        [(int) $user['id']],
        0
    ),
    'attended' => (int) db_val("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND status = 'attended'", [(int) $user['id']], 0),
    'unread' => (int) db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [(int) $user['id']], 0),
];

render('account', [
    'title' => 'Личный кабинет',
    'navActive' => 'account',
    'user' => $user,
    'stats' => $stats,
]);
