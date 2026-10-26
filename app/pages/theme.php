<?php

$theme = input('theme') === 'light' ? 'light' : 'dark';
$_SESSION['theme'] = $theme;

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

if ($referer !== '' && $host !== '' && strpos($referer, '//' . $host) !== false) {
    header('Location: ' . $referer);
    exit;
}

redirect('');
