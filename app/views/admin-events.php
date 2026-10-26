<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Мероприятия</h1>
        <p class="page-sub">Модерация, публикация и отмена мероприятий всех организаторов.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('manage/event')) ?>">Новое мероприятие</a>
</header>

<?php partial('admin-tabs', ['active' => 'events']); ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Все мероприятия</h2>
        <span class="section-count"><?= count($events) ?> <?= plural(count($events), ['мероприятие', 'мероприятия', 'мероприятий']) ?></span>
    </div>

    <form class="filters" method="get" action="<?= e(url('admin/events')) ?>">
        <div class="field">
            <label class="label" for="search">Поиск</label>
            <input type="search" id="search" name="search" value="<?= e($search) ?>" placeholder="Название мероприятия">
        </div>
        <div class="field">
            <label class="label" for="status">Статус</label>
            <select id="status" name="status" data-autosubmit>
                <option value="">Все статусы</option>
                <?php foreach (event_status_labels() as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $statusFilter === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <button class="btn btn-ghost" type="submit">Найти</button>
        </div>
        <div></div>
        <div></div>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Мероприятие</th>
                    <th>Организатор</th>
                    <th>Дата</th>
                    <th>Участники</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="cell-strong">
                            <a href="<?= e(url('event', ['slug' => $event['slug']])) ?>"><?= e($event['title']) ?></a>
                            <div class="hint"><?= e($event['category_name']) ?> · <?= e($event['venue_name'] ?? 'Площадка не указана') ?></div>
                        </td>
                        <td><?= e($event['organizer_last'] . ' ' . $event['organizer_first']) ?></td>
                        <td class="cell-num"><?= e(dt($event['starts_at'])) ?></td>
                        <td class="cell-num"><?= (int) $event['taken'] ?><?= $event['capacity'] !== null ? ' / ' . (int) $event['capacity'] : '' ?></td>
                        <td><span class="status status-<?= e($event['status']) ?>"><?= e(event_status_label($event['status'])) ?></span></td>
                        <td>
                            <div class="row-actions">
                                <a class="link-quiet" href="<?= e(url('manage/event', ['id' => $event['id']])) ?>">Изменить</a>
                                <a class="link-quiet" href="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">Участники</a>
                                <?php if ($event['status'] !== 'published'): ?>
                                    <form method="post" action="<?= e(url('admin/events')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="status" value="published">
                                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                        <button class="link-quiet" type="submit">Опубликовать</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($event['status'] !== 'completed'): ?>
                                    <form method="post" action="<?= e(url('admin/events')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="status" value="completed">
                                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                        <button class="link-quiet" type="submit">Завершить</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($event['status'] !== 'cancelled'): ?>
                                    <form method="post" action="<?= e(url('admin/events')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="status" value="cancelled">
                                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                        <button class="link-quiet" type="submit" data-confirm="Отменить мероприятие?">Отменить</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('admin/events')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                                    <button class="link-quiet is-danger" type="submit" data-confirm="Удалить мероприятие со всеми записями?">Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
