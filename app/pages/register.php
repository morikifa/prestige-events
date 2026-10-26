<?php

if (current_user()) {
    redirect('account');
}

$errors = [];
$values = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'email' => '',
    'phone' => '',
];

if (is_post()) {
    foreach ($values as $key => $default) {
        $values[$key] = (string) input($key);
    }

    $password = (string) ($_POST['password'] ?? '');
    $passwordRepeat = (string) ($_POST['password_repeat'] ?? '');
    $consent = isset($_POST['consent']);

    if ($values['last_name'] === '') {
        $errors[] = 'Укажите фамилию.';
    }
    if ($values['first_name'] === '') {
        $errors[] = 'Укажите имя.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный адрес электронной почты.';
    } else {
        $exists = db_val('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [mb_strtolower($values['email'], 'UTF-8')], 0);
        if ((int) $exists > 0) {
            $errors[] = 'Пользователь с такой почтой уже зарегистрирован.';
        }
    }
    if (mb_strlen($password, 'UTF-8') < 8) {
        $errors[] = 'Пароль должен содержать не менее 8 символов.';
    }
    if ($password !== $passwordRepeat) {
        $errors[] = 'Пароли не совпадают.';
    }
    if (!$consent) {
        $errors[] = 'Необходимо согласие на обработку персональных данных.';
    }

    if (!$errors) {
        $participantRole = db_one("SELECT id FROM roles WHERE code = 'participant'");
        db_exec(
            'INSERT INTO users (role_id, last_name, first_name, middle_name, email, phone, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $participantRole['id'],
                $values['last_name'],
                $values['first_name'],
                $values['middle_name'] !== '' ? $values['middle_name'] : null,
                mb_strtolower($values['email'], 'UTF-8'),
                $values['phone'] !== '' ? $values['phone'] : null,
                password_hash($password, PASSWORD_DEFAULT),
                'active',
            ]
        );

        $userId = db_last_id();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        current_user(true);
        notify($userId, null, 'welcome', 'Добро пожаловать', 'Личный кабинет создан. Выберите мероприятие в афише и запишитесь на него.');
        log_action('user_registered', 'user', $userId);

        flash('success', 'Учётная запись создана. Выберите мероприятие в афише.');
        redirect('account');
    }
}

render('register', [
    'title' => 'Регистрация',
    'navActive' => 'login',
    'errors' => $errors,
    'values' => $values,
]);
