<?php

if (is_post()) {
    $action = (string) input('action');

    if ($action === 'save') {
        $submitted = $_POST['settings'] ?? [];
        $existingKeys = array_keys(settings_all());
        $updated = 0;

        foreach ($submitted as $key => $value) {
            if (in_array($key, $existingKeys, true) && is_string($value)) {
                save_setting($key, $value);
                $updated++;
            }
        }

        log_action('settings_updated', 'settings', null, ['count' => $updated]);
        flash('success', 'Параметры платформы сохранены.');
        redirect('admin/settings');
    }

    if ($action === 'create') {
        $key = strtolower(trim((string) input('setting_key')));
        $value = (string) input('setting_value');
        $description = trim((string) input('description'));

        if (!preg_match('/^[a-z][a-z0-9_]{2,63}$/', $key)) {
            flash('error', 'Ключ: латиница, цифры и подчёркивание, от 3 до 64 символов.');
        } elseif (isset(settings_all()[$key])) {
            flash('error', 'Такой параметр уже существует.');
        } else {
            db_exec('INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)', [
                $key,
                $value,
                $description !== '' ? $description : null,
            ]);
            settings_all(true);
            log_action('setting_created', 'settings', null, ['key' => $key]);
            flash('success', 'Параметр добавлен.');
        }
        redirect('admin/settings');
    }

    redirect('admin/settings');
}

render('admin-settings', [
    'title' => 'Настройки платформы',
    'navActive' => 'admin',
    'settings' => settings_all(true),
]);
