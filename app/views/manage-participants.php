<header class="page-head">
    <div>
        <p class="label">Управление · Участники</p>
        <h1 class="page-title"><?= e($event['title']) ?></h1>
        <p class="page-sub"><?= e(dt($event['starts_at'])) ?> · <?= e($event['venue_name'] ?? 'Площадка не указана') ?></p>
    </div>
    <div class="row-actions">
        <a class="btn btn-ghost" href="<?= e(url('manage/program', ['id' => $event['id']])) ?>">Программа</a>
        <a class="btn btn-ghost" href="<?= e(url('manage/export', ['id' => $event['id'], 'format' => 'csv'])) ?>">CSV</a>
        <a class="btn btn-ghost" href="<?= e(url('manage/export', ['id' => $event['id'], 'format' => 'xlsx'])) ?>">XLSX</a>
    </div>
</header>

<?php partial('manage-tabs', ['active' => 'events']); ?>

<div class="stat-grid">
    <div class="stat">
        <span class="stat-value"><?= (int) $seats['taken'] ?></span>
        <span class="stat-label">записано</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= (int) $seats['capacity'] ?></span>
        <span class="stat-label">мест всего</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= $seats['free'] === null ? '∞' : (int) $seats['free'] ?></span>
        <span class="stat-label">свободно</span>
    </div>
    <div class="stat">
        <span class="stat-value"><?= count($waitlistRows) ?></span>
        <span class="stat-label">в листе ожидания</span>
    </div>
</div>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Отметка посещения</h2>
        <a class="link-quiet" href="<?= e(url('attend')) ?>">Отдельная страница</a>
    </div>
    <form class="inline-form" method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="attendance">
        <div class="field" style="flex: 2 1 320px;">
            <label class="label" for="token">Токен билета из QR-кода</label>
            <input type="text" id="token" name="token" placeholder="Например, a4f19c2d7b8e4365a1c9d2e7f0b38c51">
        </div>
        <div class="field" style="flex: 0 0 auto;">
            <button class="btn" type="submit">Отметить</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Список участников</h2>
        <span class="section-count"><?= count($registrations) ?> <?= plural(count($registrations), ['запись', 'записи', 'записей']) ?></span>
    </div>

    <?php if (!$registrations): ?>
        <p class="empty">Записей пока нет.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Участник</th>
                        <th>Контакты</th>
                        <th>Записан</th>
                        <th>Статус</th>
                        <th>Токен</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $registration): ?>
                        <tr>
                            <td class="cell-strong">
                                <?= e(trim($registration['last_name'] . ' ' . $registration['first_name'] . ' ' . ($registration['middle_name'] ?? ''))) ?>
                                <?php if ($registration['attended_at']): ?>
                                    <div class="hint">посещение: <?= e(dt($registration['attended_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($registration['email']) ?>
                                <?php if ($registration['phone']): ?>
                                    <div class="hint"><?= e($registration['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="cell-num"><?= e(dt($registration['registered_at'])) ?></td>
                            <td><span class="status status-<?= e($registration['status']) ?>"><?= e(registration_status_label($registration['status'])) ?></span></td>
                            <td class="mono"><?= e($registration['qr_token']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php if (in_array($registration['status'], ['registered', 'confirmed'], true)): ?>
                                        <form method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="attendance">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
                                            <button class="link-quiet" type="submit">Отметить посещение</button>
                                        </form>
                                        <form method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel_registration">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
                                            <button class="link-quiet" type="submit" data-confirm="Отменить запись участника?">Отменить</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($registration['status'] === 'cancelled'): ?>
                                        <form method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="confirm_registration">
                                            <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
                                            <button class="link-quiet" type="submit">Восстановить</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_registration">
                                        <input type="hidden" name="registration_id" value="<?= (int) $registration['id'] ?>">
                                        <button class="link-quiet is-danger" type="submit" data-confirm="Удалить запись безвозвратно?">Удалить</button>
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
        <h2 class="panel-title">Лист ожидания</h2>
        <span class="section-count"><?= count($waitlistRows) ?> <?= plural(count($waitlistRows), ['человек', 'человека', 'человек']) ?></span>
    </div>

    <?php if (!$waitlistRows): ?>
        <p class="empty">Лист ожидания пуст.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Участник</th>
                        <th>Добавлен</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($waitlistRows as $row): ?>
                        <tr>
                            <td class="cell-num"><?= (int) $row['position'] ?></td>
                            <td class="cell-strong">
                                <?= e(trim($row['last_name'] . ' ' . $row['first_name'])) ?>
                                <div class="hint"><?= e($row['email']) ?></div>
                            </td>
                            <td class="cell-num"><?= e(dt($row['created_at'])) ?></td>
                            <td><span class="status status-<?= e($row['status']) ?>"><?= e(waitlist_status_label($row['status'])) ?></span></td>
                            <td>
                                <div class="row-actions">
                                    <?php foreach (['invited' => 'Пригласить', 'rejected' => 'Отклонить'] as $status => $label): ?>
                                        <form method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="waitlist_status">
                                            <input type="hidden" name="waitlist_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="status" value="<?= e($status) ?>">
                                            <button class="link-quiet" type="submit"><?= e($label) ?></button>
                                        </form>
                                    <?php endforeach; ?>
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
    <details class="disclosure">
        <summary>Рассылка уведомлений участникам</summary>
        <div class="disclosure-body">
            <form class="form" method="post" action="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="notify">
                <div class="field">
                    <label class="label" for="subject">Тема</label>
                    <input type="text" id="subject" name="subject" placeholder="Например: Изменение времени начала" required>
                </div>
                <div class="field">
                    <label class="label" for="message">Текст уведомления</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Отправить всем участникам</button>
                    <span class="form-note">Сообщение появится в личных кабинетах участников и в листе ожидания.</span>
                </div>
            </form>
        </div>
    </details>
</section>
