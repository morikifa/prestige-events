<?php

logout_user();
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(24));
flash('success', 'Вы вышли из личного кабинета.');
redirect('');
