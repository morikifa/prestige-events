<?php

$editType = input('edit_type') === 'venue' ? 'venue' : (input('edit_type') === 'category' ? 'category' : '');
$editId = (int) input('edit_id', 0);

$category = ['id' => 0, 'name' => '', 'description' => ''];
$venue = ['id' => 0, 'name' => '', 'address' => '', 'capacity' => ''];

if ($editType === 'category' && $editId > 0) {
    $row = db_one('SELECT * FROM categories WHERE id = ?', [$editId]);
    if ($row) {
        $category = $row;
    }
}
if ($editType === 'venue' && $editId > 0) {
    $row = db_one('SELECT * FROM venues WHERE id = ?', [$editId]);
    if ($row) {
        $venue = $row;
    }
}

if (is_post()) {
    $entity = (string) input('entity');
    $action = (string) input('action');
    $id = (int) input('id', 0);

    if ($entity === 'category') {
        if ($action === 'delete') {
            $used = (int) db_val('SELECT COUNT(*) FROM events WHERE category_id = ?', [$id], 0);
            if ($used > 0) {
                flash('error', 'Категория используется в ' . $used . ' мероприятиях. Сначала измените их категорию.');
            } else {
                db_exec('DELETE FROM categories WHERE id = ?', [$id]);
                log_action('category_deleted', 'category', $id);
                flash('success', 'Категория удалена.');
            }
            redirect('admin/catalogs');
        }

        $name = trim((string) input('name'));
        $description = trim((string) input('description'));

        if ($name === '') {
            flash('error', 'Название категории обязательно.');
            redirect('admin/catalogs');
        }

        $sql = 'SELECT COUNT(*) FROM categories WHERE name = ?';
        $params = [$name];
        if ($id > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $id;
        }
        if ((int) db_val($sql, $params, 0) > 0) {
            flash('error', 'Категория с таким названием уже есть.');
            redirect('admin/catalogs');
        }

        if ($id > 0) {
            db_exec('UPDATE categories SET name = ?, description = ? WHERE id = ?', [$name, $description !== '' ? $description : null, $id]);
            log_action('category_updated', 'category', $id);
            flash('success', 'Категория обновлена.');
        } else {
            db_exec('INSERT INTO categories (name, description) VALUES (?, ?)', [$name, $description !== '' ? $description : null]);
            log_action('category_created', 'category', db_last_id());
            flash('success', 'Категория добавлена.');
        }
        redirect('admin/catalogs');
    }

    if ($entity === 'venue') {
        if ($action === 'delete') {
            db_exec('DELETE FROM venues WHERE id = ?', [$id]);
            log_action('venue_deleted', 'venue', $id);
            flash('success', 'Площадка удалена. В мероприятиях площадка сброшена.');
            redirect('admin/catalogs');
        }

        $name = trim((string) input('name'));
        $address = trim((string) input('address'));
        $capacity = trim((string) input('capacity'));

        if ($name === '' || $address === '') {
            flash('error', 'Название и адрес площадки обязательны.');
            redirect('admin/catalogs');
        }
        if ($capacity !== '' && (int) $capacity <= 0) {
            flash('error', 'Вместимость должна быть положительным числом.');
            redirect('admin/catalogs');
        }

        $capacityValue = $capacity !== '' ? (int) $capacity : null;

        if ($id > 0) {
            db_exec('UPDATE venues SET name = ?, address = ?, capacity = ? WHERE id = ?', [$name, $address, $capacityValue, $id]);
            log_action('venue_updated', 'venue', $id);
            flash('success', 'Площадка обновлена.');
        } else {
            db_exec('INSERT INTO venues (name, address, capacity) VALUES (?, ?, ?)', [$name, $address, $capacityValue]);
            log_action('venue_created', 'venue', db_last_id());
            flash('success', 'Площадка добавлена.');
        }
        redirect('admin/catalogs');
    }

    redirect('admin/catalogs');
}

$categories = db_all(
    'SELECT c.*, (SELECT COUNT(*) FROM events e WHERE e.category_id = c.id) AS events_count
     FROM categories c ORDER BY c.name'
);
$venues = db_all(
    'SELECT v.*, (SELECT COUNT(*) FROM events e WHERE e.venue_id = v.id) AS events_count
     FROM venues v ORDER BY v.name'
);

render('admin-catalogs', [
    'title' => 'Справочники',
    'navActive' => 'admin',
    'categories' => $categories,
    'venues' => $venues,
    'category' => $category,
    'venue' => $venue,
    'editType' => $editType,
]);
