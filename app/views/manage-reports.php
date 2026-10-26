<header class="page-head">
    <div>
        <p class="label">Управление</p>
        <h1 class="page-title">Отчёты</h1>
        <p class="page-sub">Показатели по мероприятиям, за которые вы отвечаете.</p>
    </div>
    <div class="row-actions">
        <a class="btn btn-ghost" href="<?= e(url('manage/export', ['format' => 'csv'])) ?>">Участники CSV</a>
        <a class="btn btn-ghost" href="<?= e(url('manage/export', ['format' => 'xlsx'])) ?>">Участники XLSX</a>
    </div>
</header>

<?php partial('manage-tabs', ['active' => 'reports']); ?>

<div class="stat-grid">
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['events'] ?></span>
        <span class="stat-label">мероприятий</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $stats['published'] ?></span>
        <span class="stat-label">опубликовано</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $totals['registered'] ?></span>
        <span class="stat-label">активных записей</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $totals['attended'] ?></span>
        <span class="stat-label">посещений отмечено</span>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Мероприятия</h2>
        <span class="section-count">лист ожидания: <?= (int) $totals['waiting'] ?></span>
    </div>

    <?php if (!$events): ?>
        <p class="empty">Данных пока нет. Создайте мероприятие.</p>
    <?php else: ?>
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
                        <th>Ожидают</th>
                        <th></th>
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
                            <td class="cell-num"><?= (int) $event['waiting'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="link-quiet" href="<?= e(url('manage/export', ['id' => $event['id'], 'format' => 'csv'])) ?>">CSV</a>
                                    <a class="link-quiet" href="<?= e(url('manage/export', ['id' => $event['id'], 'format' => 'xlsx'])) ?>">XLSX</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
