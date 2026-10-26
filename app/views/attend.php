<header class="page-head">
    <div>
        <p class="label">Управление</p>
        <h1 class="page-title">Отметка посещения</h1>
        <p class="page-sub">Введите токен из билета участника или откройте ссылку, зашитую в QR-код.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('manage/events')) ?>">К мероприятиям</a>
</header>

<?php partial('manage-tabs', ['active' => 'attend']); ?>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Токен билета</h2>
    </div>

    <form class="inline-form" method="post" action="<?= e(url('attend')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark">
        <div class="field" style="flex: 2 1 340px;">
            <label class="label" for="token">Токен</label>
            <input type="text" id="token" name="token" value="<?= e($token) ?>" placeholder="32 символа из билета" required>
        </div>
        <div class="field" style="flex: 0 0 auto;">
            <button class="btn" type="submit">Отметить посещение</button>
        </div>
    </form>

    <?php if ($result && !isset($result['error'])): ?>
        <div class="ticket mt-24">
            <span class="label">Посещение отмечено</span>
            <h3 class="card-title"><?= e($result['registration']['event_title']) ?></h3>
            <p class="form-note">Токен: <span class="mono"><?= e($result['registration']['qr_token']) ?></span></p>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Как это работает</h2>
    </div>
    <div class="steps">
        <div class="step">
            <span class="step-num">01</span>
            <h3 class="step-title">Участник показывает билет</h3>
            <p class="step-text">В личном кабинете открыт цифровой билет с QR-кодом и текстовым токеном.</p>
        </div>
        <div class="step">
            <span class="step-num">02</span>
            <h3 class="step-title">Организатор вводит токен</h3>
            <p class="step-text">Доступна и ссылка вида «страница отметки + токен» — она открывается сканером QR.</p>
        </div>
        <div class="step">
            <span class="step-num">03</span>
            <h3 class="step-title">Статус меняется на «Посетил»</h3>
            <p class="step-text">Отметка фиксируется в журнале действий и попадает в отчёты по мероприятию.</p>
        </div>
    </div>
</section>
