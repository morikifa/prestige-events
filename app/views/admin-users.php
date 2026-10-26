<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Пользователи</h1>
        <p class="page-sub">Роли и статусы изменяет только администратор. Самостоятельно сменить роль в кабинете нельзя.</p>
    </div>
    <a class="btn" href="<?= e(url('admin/users')) ?>">Обновить список</a>
</header>

<?php partial('admin-tabs', ['active' => 'users']); ?>

<?php if ($errors): ?>
    <div class="alert mt-24">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= $editUser ? 'Изменение пользователя' : 'Новый пользователь' ?></h2>
        <?php if ($editUser): ?>
            <a class="link-quiet" href="<?= e(url('admin/users')) ?>">Отменить редактирование</a>
        <?php endif; ?>
    </div>

    <form class="form" method="post" action="<?= e(url('admin/users', $editUser ? ['edit' => $editUser['id']] : [])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="user_id" value="<?= $editUser ? (int) $editUser['id'] : 0 ?>">

        <div class="form-grid">
            <div class="field">
                <label class="label" for="last_name">Фамилия</label>
                <input type="text" id="last_name" name="last_name" value="<?= e($values['last_name']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="first_name">Имя</label>
                <input type="text" id="first_name" name="first_name" value="<?= e($values['first_name']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="middle_name">Отчество</label>
                <input type="text" id="middle_name" name="middle_name" value="<?= e($values['middle_name']) ?>">
            </div>
            <div class="field">
                <label class="label" for="phone">Телефон</label>
                <input type="text" id="phone" name="phone" value="<?= e($values['phone']) ?>">
            </div>
            <div class="field span-2">
                <label class="label" for="email">Электронная почта (логин)</label>
                <input type="email" id="email" name="email" value="<?= e($values['email']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="role_id">Роль</label>
                <select id="role_id" name="role_id" required>
                    <option value="">Выберите роль</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (int) $values['role_id'] === (int) $role['id'] ? 'selected' : '' ?>>
                            <?= e($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="status">Статус</label>
                <select id="status" name="status">
                    <?php foreach (user_status_labels() as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $values['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field span-2">
                <label class="label" for="password"><?= $editUser ? 'Новый пароль (если нужно сменить)' : 'Пароль' ?></label>
                <input type="password" id="password" name="password" <?= $editUser ? '' : 'required' ?> autocomplete="new-password">
                <span class="hint">Не менее 8 символов. Пароль хранится в виде хеша.</span>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $editUser ? 'Сохранить' : 'Создать пользователя' ?></button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Список</h2>
        <span class="section-count"><?= count($users) ?> <?= plural(count($users), ['запись', 'записи', 'записей']) ?></span>
    </div>

    <form class="filters" method="get" action="<?= e(url('admin/users')) ?>">
        <div class="field">
            <label class="label" for="search">Поиск</label>
            <input type="search" id="search" name="search" value="<?= e($search) ?>" placeholder="Фамилия, имя или почта">
        </div>
        <div class="field">
            <label class="label" for="role">Роль</label>
            <select id="role" name="role" data-autosubmit>
                <option value="">Все роли</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>" <?= $roleFilter === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="status-filter">Статус</label>
            <select id="status-filter" name="status" data-autosubmit>
                <option value="">Все статусы</option>
                <?php foreach (user_status_labels() as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $statusFilter === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <button class="btn btn-ghost" type="submit">Найти</button>
        </div>
        <div></div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Контакты</th>
                    <th>Роль</th>
                    <th>Статус</th>
                    <th>Создан</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td class="cell-strong">
                            <?= e(trim($row['last_name'] . ' ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? ''))) ?>
                            <?php if ((int) $row['id'] === (int) $currentUser['id']): ?>
                                <div class="hint">это вы</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($row['email']) ?>
                            <?php if ($row['phone']): ?>
                                <div class="hint"><?= e($row['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['role_name']) ?></td>
                        <td><span class="status status-<?= e($row['status']) ?>"><?= e(user_status_label($row['status'])) ?></span></td>
                        <td class="cell-num"><?= e(dt_full($row['created_at'])) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="link-quiet" href="<?= e(url('admin/users', ['edit' => $row['id']])) ?>">Изменить</a>
                                <?php if ((int) $row['id'] !== (int) $currentUser['id']): ?>
                                    <form method="post" action="<?= e(url('admin/users')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $row['status'] === 'active' ? 'blocked' : 'active' ?>">
                                        <button class="link-quiet" type="submit"><?= $row['status'] === 'active' ? 'Заблокировать' : 'Активировать' ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('admin/users')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <button class="link-quiet is-danger" type="submit" data-confirm="Удалить пользователя и все его записи?">Удалить</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
