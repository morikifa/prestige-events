<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Резервные копии</h1>
        <p class="page-sub">Копия данных всех таблиц в формате SQL. Храните копии на носителе, отличном от основного.</p>
    </div>
    <form method="post" action="<?= e(url('admin/backup')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <button class="btn" type="submit">Создать копию</button>
    </form>
</header>

<?php partial('admin-tabs', ['active' => 'backup']); ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Файлы</h2>
        <span class="section-count"><?= count($files) ?> <?= plural(count($files), ['файл', 'файла', 'файлов']) ?></span>
    </div>

    <?php if (!$files): ?>
        <p class="empty">Резервных копий пока нет. Создайте первую копию.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Файл</th>
                        <th>Создан</th>
                        <th>Размер</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td class="cell-strong mono"><?= e($file['name']) ?></td>
                            <td class="cell-num"><?= e($file['created_at']) ?></td>
                            <td class="cell-num"><?= e(number_format($file['size'] / 1024, 1, ',', ' ')) ?> КБ</td>
                            <td>
                                <div class="row-actions">
                                    <a class="link-quiet" href="<?= e(url('admin/backup', ['download' => $file['name']])) ?>">Скачать</a>
                                    <form method="post" action="<?= e(url('admin/backup')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="file" value="<?= e($file['name']) ?>">
                                        <button class="link-quiet" type="submit" data-confirm="Заменить текущие данные содержимым копии?">Восстановить</button>
                                    </form>
                                    <form method="post" action="<?= e(url('admin/backup')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="file" value="<?= e($file['name']) ?>">
                                        <button class="link-quiet is-danger" type="submit" data-confirm="Удалить файл копии?">Удалить</button>
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

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Как настроить ежедневное копирование</h2>
    </div>
    <div class="steps">
        <div class="step">
            <span class="step-num">01</span>
            <h3 class="step-title">Создайте копию</h3>
            <p class="step-text">Кнопка «Создать копию» сохраняет файл SQL в каталог <span class="mono"><?= e($directory) ?></span>.</p>
        </div>
        <div class="step">
            <span class="step-num">02</span>
            <h3 class="step-title">Настройте расписание</h3>
            <p class="step-text">В планировщике задач Windows или cron укажите вызов PHP-скрипта копирования раз в сутки.</p>
        </div>
        <div class="step">
            <span class="step-num">03</span>
            <h3 class="step-title">Храните отдельно</h3>
            <p class="step-text">Копируйте файлы на другой носитель и храните не менее 30 суток.</p>
        </div>
    </div>
</section>
