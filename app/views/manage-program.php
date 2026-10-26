<header class="page-head">
    <div>
        <p class="label">Управление · Программа</p>
        <h1 class="page-title"><?= e($event['title']) ?></h1>
        <p class="page-sub"><?= e(dt($event['starts_at'])) ?> · <?= e($event['venue_name'] ?? 'Площадка не указана') ?></p>
    </div>
    <div class="row-actions">
        <a class="btn btn-ghost" href="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">Участники</a>
        <a class="btn btn-ghost" href="<?= e(url('manage/events')) ?>">К списку</a>
    </div>
</header>

<?php partial('manage-tabs', ['active' => 'events']); ?>

<?php if ($errors): ?>
    <div class="alert mt-24">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title"><?= $editItem ? 'Изменить пункт программы' : 'Добавить пункт' ?></h2>
        <?php if ($editItem): ?>
            <a class="link-quiet" href="<?= e(url('manage/program', ['id' => $event['id']])) ?>">Отменить редактирование</a>
        <?php endif; ?>
    </div>

    <form class="form" method="post" action="<?= e(url('manage/program', ['id' => $event['id']])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="program_id" value="<?= $editItem ? (int) $editItem['id'] : 0 ?>">

        <div class="form-grid">
            <div class="field span-2">
                <label class="label" for="title">Название пункта</label>
                <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="speaker">Докладчик</label>
                <input type="text" id="speaker" name="speaker" value="<?= e($values['speaker']) ?>" placeholder="Необязательно">
            </div>
            <div class="field">
                <label class="label" for="place">Место</label>
                <input type="text" id="place" name="place" value="<?= e($values['place']) ?>" placeholder="Ауд. 210">
            </div>
            <div class="field">
                <label class="label" for="starts_at">Начало</label>
                <input type="datetime-local" id="starts_at" name="starts_at" value="<?= e($values['starts_at']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="ends_at">Окончание</label>
                <input type="datetime-local" id="ends_at" name="ends_at" value="<?= e($values['ends_at']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="sort_order">Порядок вывода</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= e($values['sort_order']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $editItem ? 'Сохранить изменения' : 'Добавить пункт' ?></button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Расписание</h2>
        <span class="section-count"><?= count($program) ?> <?= plural(count($program), ['пункт', 'пункта', 'пунктов']) ?></span>
    </div>

    <?php if (!$program): ?>
        <p class="empty">Программа пуста. Добавьте первый пункт.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Время</th>
                        <th>Пункт</th>
                        <th>Место</th>
                        <th>Порядок</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($program as $item): ?>
                        <tr>
                            <td class="cell-num"><?= e(dt_time($item['starts_at'])) ?> — <?= e(dt_time($item['ends_at'])) ?></td>
                            <td class="cell-strong">
                                <?= e($item['title']) ?>
                                <?php if ($item['speaker']): ?>
                                    <div class="hint"><?= e($item['speaker']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($item['place'] ?? '—') ?></td>
                            <td class="cell-num"><?= (int) $item['sort_order'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="link-quiet" href="<?= e(url('manage/program', ['id' => $event['id'], 'edit' => $item['id']])) ?>">Изменить</a>
                                    <form method="post" action="<?= e(url('manage/program', ['id' => $event['id']])) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="program_id" value="<?= (int) $item['id'] ?>">
                                        <button class="link-quiet is-danger" type="submit" data-confirm="Удалить пункт программы?">Удалить</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
