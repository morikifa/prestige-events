<?php

$existing = current_user();
if ($existing && !is_post()) {
    redirect('account');
}

$error = null;
$email = '';

if (is_post()) {
    $email = (string) input('email');
    $result = authenticate($email, (string) ($_POST['password'] ?? ''));

    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $user = current_user(true);
        flash('success', 'Вы вошли как ' . user_full_name($user) . '.');
        redirect(home_route_for($user));
    }
}

render('login', [
    'title' => 'Вход в личный кабинет',
    'navActive' => 'login',
    'error' => $error,
    'email' => $email,
]);
