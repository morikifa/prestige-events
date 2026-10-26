<header class="page-head">
    <div>
        <p class="label">Личный кабинет</p>
        <h1 class="page-title">Мои мероприятия</h1>
        <p class="page-sub">Записи, статусы участия и цифровые билеты.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('')) ?>">Перейти в афишу</a>
</header>

<?php partial('account-tabs', ['active' => 'events']); ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Записи</h2>
        <span class="section-count"><?= count($registrations) ?> <?= plural(count($registrations), ['запись', 'записи', 'записей']) ?></span>
    </div>

    <?php if (!$registrations): ?>
        <p class="empty">Записей пока нет. Выберите мероприятие в <a href="<?= e(url('')) ?>">афише</a>.</p>
    <?php endif; ?>

    <?php foreach ($registrations as $registration): ?>
        <article class="panel" style="border-top: 1px solid var(--line); padding-top: 22px;">
            <div class="panel-head">
                <div>
                    <span class="label"><?= e(dt($registration['starts_at'])) ?> · <?= e($registration['venue_name'] ?? 'Площадка уточняется') ?></span>
                    <h3 class="card-title" style="margin-top: 8px;">
                        <a href="<?= e(url('event', ['slug' => $registration['slug']])) ?>"><?= e($registration['title']) ?></a>
                    </h3>
                </div>
                <span class="status status-<?= e($registration['status']) ?>"><?= e(registration_status_label($registration['status'])) ?></span>
            </div>

            <?php if (in_array($registration['status'], ['registered', 'confirmed', 'attended'], true)): ?>
                <div class="form-grid">
                    <div class="fact">
                        <span class="label">Дата записи</span>
                        <span class="fact-value-sm"><?= e(dt($registration['registered_at'])) ?></span>
                    </div>
                    <div class="fact">
                        <span class="label">Токен билета</span>
                        <span class="mono"><?= e($registration['qr_token']) ?></span>
                    </div>
                    <?php if ($registration['attended_at']): ?>
                        <div class="fact">
                            <span class="label">Посещение</span>
                            <span class="fact-value-sm"><?= e(dt($registration['attended_at'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('event', ['slug' => $registration['slug']])) ?>">Открыть мероприятие</a>
                <?php if (in_array($registration['status'], ['registered', 'confirmed'], true) && strtotime($registration['starts_at']) > time()): ?>
                    <form method="post" action="<?= e(url('account/events')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit" data-confirm="Отменить запись?">Отменить запись</button>
                    </form>
                <?php endif; ?>
                <?php if ($registration['event_status'] === 'completed'): ?>
                    <span class="form-note">Мероприятие завершено.</span>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Лист ожидания</h2>
        <span class="section-count"><?= count($waitlist) ?> <?= plural(count($waitlist), ['позиция', 'позиции', 'позиций']) ?></span>
    </div>

    <?php if (!$waitlist): ?>
        <p class="empty">Активных позиций в листе ожидания нет.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Мероприятие</th>
                        <th>Дата</th>
                        <th>Позиция</th>
                        <th>Статус</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($waitlist as $entry): ?>
                        <tr>
                            <td class="cell-strong"><a href="<?= e(url('event', ['slug' => $entry['slug']])) ?>"><?= e($entry['title']) ?></a></td>
                            <td class="cell-num"><?= e(dt($entry['starts_at'])) ?></td>
                            <td class="cell-num">№ <?= (int) $entry['position'] ?></td>
                            <td><span class="status status-<?= e($entry['status']) ?>"><?= e(waitlist_status_label($entry['status'])) ?></span></td>
                            <td>
                                <form method="post" action="<?= e(url('account/events')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="leave_waitlist">
                                    <input type="hidden" name="waitlist_id" value="<?= (int) $entry['id'] ?>">
                                    <button class="link-quiet" type="submit">Выйти</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
