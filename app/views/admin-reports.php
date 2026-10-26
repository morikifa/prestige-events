<header class="page-head">
    <div>
        <p class="label">Администрирование</p>
        <h1 class="page-title">Отчёты</h1>
        <p class="page-sub">Сводные показатели по всем мероприятиям платформы за всё время.</p>
    </div>
    <div class="row-actions">
        <a class="btn btn-ghost" href="<?= e(url('admin/reports', ['export' => 'csv'])) ?>">Отчёт CSV</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/reports', ['export' => 'xlsx'])) ?>">Отчёт XLSX</a>
    </div>
</header>

<?php partial('admin-tabs', ['active' => 'reports']); ?>

<div class="stat-grid">
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['events'] ?></span>
        <span class="stat-label">мероприятий создано</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['registrations'] ?></span>
        <span class="stat-label">записей участников</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['attended'] ?></span>
        <span class="stat-label">посещений отмечено</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['attendance_rate'] ?>%</span>
        <span class="stat-label">доля посещений</span>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">По категориям</h2>
        <span class="section-count">пользователей: <?= (int) $totals['users'] ?> · площадок: <?= (int) $totals['venues'] ?></span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Категория</th>
                    <th>Мероприятий</th>
                    <th>Записей</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($byCategory as $row): ?>
                    <tr>
                        <td class="cell-strong"><?= e($row['category_name']) ?></td>
                        <td class="cell-num"><?= (int) $row['events_count'] ?></td>
                        <td class="cell-num"><?= (int) $row['registrations'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">По мероприятиям</h2>
        <span class="section-count">лист ожидания: <?= (int) $totals['waitlist'] ?> · суммарная вместимость: <?= (int) $totals['capacity'] ?></span>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Мероприятие</th>
                    <th>Начало</th>
                    <th>Статус</th>
                    <th>Мест</th>
                    <th>Записано</th>
                    <th>Посетило</th>
                    <th>Отменено</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="cell-strong">
                            <?= e($event['title']) ?>
                            <div class="hint"><?= e($event['category_name']) ?></div>
                        </td>
                        <td class="cell-num"><?= e(dt($event['starts_at'])) ?></td>
                        <td><span class="status status-<?= e($event['status']) ?>"><?= e(event_status_label($event['status'])) ?></span></td>
                        <td class="cell-num"><?= $event['capacity'] !== null ? (int) $event['capacity'] : '—' ?></td>
                        <td class="cell-num"><?= (int) $event['registered'] ?></td>
                        <td class="cell-num"><?= (int) $event['attended'] ?></td>
                        <td class="cell-num"><?= (int) $event['cancelled'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
