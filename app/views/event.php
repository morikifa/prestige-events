<?php
$starts = new DateTime($event['starts_at']);
$ends = new DateTime($event['ends_at']);
$sameDay = $starts->format('Y-m-d') === $ends->format('Y-m-d');
?>
<nav class="breadcrumb" aria-label="Хлебные крошки">
    <a href="<?= e(url('')) ?>">Афиша</a>
    <span aria-hidden="true">/</span>
    <span><?= e($event['category_name']) ?></span>
</nav>

<header class="event-head">
    <p class="label"><?= e($event['category_name']) ?> · <?= e(event_status_label($event['status'])) ?></p>
    <h1 class="event-title"><?= e($event['title']) ?></h1>

    <div class="fact-grid">
        <div class="fact">
            <span class="label">Когда</span>
            <span class="fact-value"><?= e(dt_full($event['starts_at'])) ?></span>
            <span class="fact-value-sm">
                <?= e(dt_time($event['starts_at'])) ?> — <?= e(dt_time($event['ends_at'])) ?>
                <?= $sameDay ? '' : '· ' . e(dt_full($event['ends_at'])) ?>
            </span>
        </div>
        <div class="fact">
            <span class="label">Где</span>
            <span class="fact-value"><?= e($event['venue_name'] ?? 'Площадка уточняется') ?></span>
            <span class="fact-value-sm"><?= e($event['venue_address'] ?? '') ?></span>
        </div>
        <div class="fact">
            <span class="label">Организатор</span>
            <span class="fact-value">
                <?= e($event['organizer_last'] . ' ' . mb_substr($event['organizer_first'], 0, 1, 'UTF-8') . '.') ?>
                <?= !empty($event['organizer_middle']) ? e(mb_substr($event['organizer_middle'], 0, 1, 'UTF-8') . '.') : '' ?>
            </span>
            <span class="fact-value-sm"><?= e($event['organizer_email']) ?></span>
        </div>
        <div class="fact">
            <span class="label">Места</span>
            <span class="fact-value">
                <?= (int) $seats['taken'] ?><?= $seats['capacity'] !== null ? ' / ' . (int) $seats['capacity'] : '' ?>
            </span>
            <span class="fact-value-sm"><?= e(seat_label(['capacity' => $event['capacity'], 'taken' => $seats['taken'], 'id' => $event['id']])) ?></span>
        </div>
    </div>
</header>

<div class="split">
    <div>
        <div class="prose"><?= e($event['description'] ?? 'Описание мероприятия готовится.') ?></div>

        <section class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Программа</h2>
                <span class="section-count"><?= count($program) ?> <?= plural(count($program), ['пункт', 'пункта', 'пунктов']) ?></span>
            </div>

            <?php if (!$program): ?>
                <p class="empty">Программа пока не опубликована.</p>
            <?php else: ?>
                <div class="program">
                    <?php foreach ($program as $item): ?>
                        <div class="program-row">
                            <span class="program-time">
                                <?= e(dt_time($item['starts_at'])) ?> — <?= e(dt_time($item['ends_at'])) ?>
                            </span>
                            <span>
                                <span class="program-title"><?= e($item['title']) ?></span>
                                <?php if (!empty($item['speaker'])): ?>
                                    <span class="program-speaker"><?= e($item['speaker']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="program-place"><?= e($item['place'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($event['registration_opens_at']) || !empty($event['registration_closes_at'])): ?>
            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Регистрация</h2>
                </div>
                <div class="fact-grid">
                    <div class="fact">
                        <span class="label">Открыта</span>
                        <span class="fact-value-sm"><?= e(dt($event['registration_opens_at'])) ?></span>
                    </div>
                    <div class="fact">
                        <span class="label">Закрывается</span>
                        <span class="fact-value-sm"><?= e(dt($event['registration_closes_at'])) ?></span>
                    </div>
                    <div class="fact">
                        <span class="label">Площадка вмещает</span>
                        <span class="fact-value-sm"><?= $event['venue_capacity'] !== null ? (int) $event['venue_capacity'] . ' чел.' : 'не указано' ?></span>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="aside">
        <span class="label">Участие</span>

        <div>
            <div class="meter"><span style="width: <?= (int) $seats['percent'] ?>%"></span></div>
            <div class="meter-row">
                <span>Записано: <?= (int) $seats['taken'] ?></span>
                <span><?= $seats['capacity'] !== null ? 'Мест: ' . (int) $seats['capacity'] : 'Без ограничения' ?></span>
            </div>
        </div>

        <?php if ($event['status'] === 'cancelled'): ?>
            <p class="form-note">Мероприятие отменено организатором. Регистрация недоступна.</p>
        <?php elseif ($event['status'] === 'completed'): ?>
            <p class="form-note">Мероприятие завершено.</p>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <div class="row-actions" style="justify-content: flex-start">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('manage/event', ['id' => $event['id']])) ?>">Редактировать</a>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('manage/participants', ['id' => $event['id']])) ?>">Участники</a>
            </div>
        <?php endif; ?>

        <?php if ($registration): ?>
            <div>
                <span class="status status-<?= e($registration['status']) ?>"><?= e(registration_status_label($registration['status'])) ?></span>
                <?php if ($registration['status'] === 'attended'): ?>
                    <p class="form-note">Посещение отмечено <?= e(dt($registration['attended_at'])) ?>.</p>
                <?php endif; ?>
            </div>

            <div class="ticket">
                <span class="label">Цифровой билет</span>
                <div class="ticket-qr"><?= qr_svg($registration['qr_token'], 132) ?></div>
                <div>
                    <span class="label">Токен</span>
                    <span class="ticket-token"><?= e($registration['qr_token']) ?></span>
                </div>
                <p class="form-note">Покажите код организатору при входе или назовите токен.</p>
                <button class="btn btn-ghost btn-sm" type="button" data-print>Печать билета</button>
            </div>

            <?php if (strtotime($event['starts_at']) > time() && $registration['status'] !== 'attended'): ?>
                <form method="post" action="<?= e(url('event', ['slug' => $event['slug']])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn btn-danger btn-sm" type="submit" data-confirm="Отменить запись на мероприятие?">Отменить запись</button>
                </form>
            <?php endif; ?>
        <?php elseif ($waitlistEntry): ?>
            <div>
                <span class="status status-<?= e($waitlistEntry['status']) ?>"><?= e(waitlist_status_label($waitlistEntry['status'])) ?></span>
                <p class="form-note">
                    Ваш номер в листе ожидания — <?= (int) $waitlistEntry['position'] ?>.
                    <?= $waitlistEntry['status'] === 'invited' ? 'Место освободилось, можно записаться.' : 'Мы уведомим, если появится место.' ?>
                </p>
            </div>

            <?php if ($waitlistEntry['status'] === 'invited'): ?>
                <form method="post" action="<?= e(url('event', ['slug' => $event['slug']])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="register">
                    <button class="btn" type="submit">Записаться</button>
                </form>
            <?php endif; ?>

            <form method="post" action="<?= e(url('event', ['slug' => $event['slug']])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="leave_waitlist">
                <button class="btn btn-ghost btn-sm" type="submit">Выйти из листа ожидания</button>
            </form>
        <?php elseif (!$registrationOpen): ?>
            <p class="form-note">
                <?= $seats['is_full'] ? 'Свободных мест нет. Регистрация закрыта.' : 'Регистрация на мероприятие закрыта.' ?>
            </p>
        <?php elseif (!$currentUser): ?>
            <a class="btn" href="<?= e(url('login')) ?>">Войти и записаться</a>
            <p class="form-note">Для записи нужен личный кабинет участника.</p>
        <?php else: ?>
            <form method="post" action="<?= e(url('event', ['slug' => $event['slug']])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <button class="btn" type="submit"><?= $seats['is_full'] ? 'Встать в лист ожидания' : 'Записаться' ?></button>
            </form>
            <p class="form-note"><?= e(setting('registration_notice')) ?></p>
        <?php endif; ?>
    </aside>
</div>
