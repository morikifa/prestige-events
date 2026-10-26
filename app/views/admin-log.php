<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Журнал действий</h1>
        <p class="page-sub">Входы, изменения мероприятий, записи, экспорт и работа с копиями. Всего записей: <?= (int) $total ?>.</p>
    </div>
</header>

<?php partial('admin-tabs', ['active' => 'log']); ?>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('admin/log')) ?>">
        <div class="field">
            <label class="label" for="search">Поиск</label>
            <input type="search" id="search" name="search" value="<?= e($search) ?>" placeholder="Фамилия, почта или детали">
        </div>
        <div class="field">
            <label class="label" for="entity">Объект</label>
            <select id="entity" name="entity" data-autosubmit>
                <option value="">Все объекты</option>
                <?php foreach ($entities as $entity): ?>
                    <option value="<?= e($entity) ?>" <?= $entityFilter === $entity ? 'selected' : '' ?>><?= e($entity) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="action_name">Действие</label>
            <select id="action_name" name="action_name" data-autosubmit>
                <option value="">Все действия</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>" <?= $actionFilter === $action ? 'selected' : '' ?>><?= e($action) ?></option>
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
                    <th>Время</th>
                    <th>Пользователь</th>
                    <th>Действие</th>
                    <th>Объект</th>
                    <th>IP</th>
                    <th>Детали</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cell-num"><?= e(dt($row['created_at'])) ?></td>
                        <td>
                            <?php if ($row['last_name']): ?>
                                <?= e($row['last_name'] . ' ' . mb_substr($row['first_name'], 0, 1, 'UTF-8') . '.') ?>
                                <div class="hint"><?= e($row['email']) ?></div>
                            <?php else: ?>
                                <span class="muted">система</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['action']) ?></td>
                        <td class="cell-num"><?= e($row['entity']) ?><?= $row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '' ?></td>
                        <td class="cell-num"><?= e($row['ip_address'] ?? '—') ?></td>
                        <td><span class="hint mono"><?= e(mb_substr((string) ($row['details'] ?? ''), 0, 80, 'UTF-8')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="muted">Записей нет.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="form-actions mt-24">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/log', ['page' => $page - 1, 'entity' => $entityFilter, 'action_name' => $actionFilter, 'search' => $search])) ?>">Назад</a>
            <?php endif; ?>
            <span class="form-note">Страница <?= (int) $page ?> из <?= (int) $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/log', ['page' => $page + 1, 'entity' => $entityFilter, 'action_name' => $actionFilter, 'search' => $search])) ?>">Вперёд</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
