<header class="page-head">
    <div>
        <p class="label">Личный кабинет</p>
        <h1 class="page-title">Уведомления</h1>
        <p class="page-sub">Подтверждения записи, сообщения организаторов и приглашения из листа ожидания.</p>
    </div>
    <?php if ($notifications): ?>
        <form method="post" action="<?= e(url('account/notifications')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="read_all">
            <button class="btn btn-ghost" type="submit">Прочитать все</button>
        </form>
    <?php endif; ?>
</header>

<?php partial('account-tabs', ['active' => 'notifications']); ?>

<section class="panel">
    <?php if (!$notifications): ?>
        <p class="empty">Уведомлений пока нет.</p>
    <?php endif; ?>

    <?php foreach ($notifications as $notification): ?>
        <article class="panel" style="border-top: 1px solid var(--line); padding-top: 20px;">
            <div class="panel-head">
                <div>
                    <span class="label"><?= e(dt($notification['created_at'])) ?> · <?= e($notification['type']) ?></span>
                    <h3 class="card-title" style="margin-top: 6px;"><?= e($notification['subject']) ?></h3>
                </div>
                <span class="status status-<?= $notification['is_read'] ? 'registered' : 'active' ?>">
                    <?= $notification['is_read'] ? 'Прочитано' : 'Новое' ?>
                </span>
            </div>
            <p class="form-note"><?= e($notification['message']) ?></p>
            <div class="form-actions">
                <?php if ($notification['event_slug']): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('event', ['slug' => $notification['event_slug']])) ?>">К мероприятию</a>
                <?php endif; ?>
                <?php if (!$notification['is_read']): ?>
                    <form method="post" action="<?= e(url('account/notifications')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="read">
                        <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                        <button class="link-quiet" type="submit">Отметить прочитанным</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
