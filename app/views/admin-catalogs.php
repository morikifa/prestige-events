<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Справочники</h1>
        <p class="page-sub">Категории мероприятий и площадки проведения. Изменения сразу видны в афише.</p>
    </div>
</header>

<?php partial('admin-tabs', ['active' => 'catalogs']); ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= $category['id'] ? 'Изменить категорию' : 'Новая категория' ?></h2>
        <?php if ($category['id']): ?>
            <a class="link-quiet" href="<?= e(url('admin/catalogs')) ?>">Отменить редактирование</a>
        <?php endif; ?>
    </div>

    <form class="form" method="post" action="<?= e(url('admin/catalogs')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="category">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
        <div class="form-grid">
            <div class="field">
                <label class="label" for="category-name">Название</label>
                <input type="text" id="category-name" name="name" value="<?= e($category['name']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="category-description">Описание</label>
                <input type="text" id="category-description" name="description" value="<?= e($category['description'] ?? '') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $category['id'] ? 'Сохранить' : 'Добавить категорию' ?></button>
        </div>
    </form>

    <div class="table-wrap mt-24">
        <table class="table">
            <thead>
                <tr>
                    <th>Категория</th>
                    <th>Мероприятий</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $row): ?>
                    <tr>
                        <td class="cell-strong">
                            <?= e($row['name']) ?>
                            <?php if ($row['description']): ?>
                                <div class="hint"><?= e($row['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="cell-num"><?= (int) $row['events_count'] ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="link-quiet" href="<?= e(url('admin/catalogs', ['edit_type' => 'category', 'edit_id' => $row['id']])) ?>">Изменить</a>
                                <form method="post" action="<?= e(url('admin/catalogs')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="entity" value="category">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="link-quiet is-danger" type="submit" data-confirm="Удалить категорию?">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= $venue['id'] ? 'Изменить площадку' : 'Новая площадка' ?></h2>
        <?php if ($venue['id']): ?>
            <a class="link-quiet" href="<?= e(url('admin/catalogs')) ?>">Отменить редактирование</a>
        <?php endif; ?>
    </div>

    <form class="form" method="post" action="<?= e(url('admin/catalogs')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="venue">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $venue['id'] ?>">
        <div class="form-grid">
            <div class="field">
                <label class="label" for="venue-name">Название</label>
                <input type="text" id="venue-name" name="name" value="<?= e($venue['name']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="venue-capacity">Вместимость, чел.</label>
                <input type="number" id="venue-capacity" name="capacity" min="1" value="<?= e($venue['capacity'] ?? '') ?>">
            </div>
            <div class="field span-2">
                <label class="label" for="venue-address">Адрес</label>
                <input type="text" id="venue-address" name="address" value="<?= e($venue['address']) ?>" required>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit"><?= $venue['id'] ? 'Сохранить' : 'Добавить площадку' ?></button>
        </div>
    </form>

    <div class="table-wrap mt-24">
        <table class="table">
            <thead>
                <tr>
                    <th>Площадка</th>
                    <th>Вместимость</th>
                    <th>Мероприятий</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($venues as $row): ?>
                    <tr>
                        <td class="cell-strong">
                            <?= e($row['name']) ?>
                            <div class="hint"><?= e($row['address']) ?></div>
                        </td>
                        <td class="cell-num"><?= $row['capacity'] !== null ? (int) $row['capacity'] : '—' ?></td>
                        <td class="cell-num"><?= (int) $row['events_count'] ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="link-quiet" href="<?= e(url('admin/catalogs', ['edit_type' => 'venue', 'edit_id' => $row['id']])) ?>">Изменить</a>
                                <form method="post" action="<?= e(url('admin/catalogs')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="entity" value="venue">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="link-quiet is-danger" type="submit" data-confirm="Удалить площадку?">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
