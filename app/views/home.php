<section class="hero">
    <p class="hero-kicker label">Платформа мероприятий · <?= e(config('app.institute')) ?> · 2026</p>
    <h1 class="display">
        <span class="display-line">Prestige</span>
        <span class="display-line display-line--offset">Events</span>
    </h1>
    <div class="hero-foot">
        <p class="lead">Афиша, программа, регистрация и учёт участников колледжа в одном месте.</p>
        <div class="hero-facts">
            <div class="hero-fact">
                <span class="hero-fact-value"><?= (int) $totals['events'] ?></span>
                <span class="label">мероприятий</span>
            </div>
            <div class="hero-fact">
                <span class="hero-fact-value"><?= (int) $totals['participants'] ?></span>
                <span class="label">участников</span>
            </div>
            <div class="hero-fact">
                <span class="hero-fact-value"><?= (int) $totals['categories'] ?></span>
                <span class="label">категорий</span>
            </div>
        </div>
    </div>
</section>

<section class="section" id="afisha">
    <div class="section-head">
        <h2 class="section-title">Афиша</h2>
        <span class="section-count">
            <?= count($events) ?> <?= plural(count($events), ['мероприятие', 'мероприятия', 'мероприятий']) ?>
            <?= $isFiltered ? 'по выбранному отбору' : 'в расписании' ?>
        </span>
    </div>

    <form class="filters" method="get" action="<?= e(url('')) ?>">
        <div class="field">
            <label class="label" for="filter-category">Категория</label>
            <select id="filter-category" name="category" data-autosubmit>
                <option value="">Все категории</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) $filters['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="filter-venue">Площадка</label>
            <select id="filter-venue" name="venue" data-autosubmit>
                <option value="">Все площадки</option>
                <?php foreach ($venues as $venue): ?>
                    <option value="<?= (int) $venue['id'] ?>" <?= (int) $filters['venue_id'] === (int) $venue['id'] ? 'selected' : '' ?>>
                        <?= e($venue['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="filter-date">Дата</label>
            <input type="date" id="filter-date" name="date" value="<?= e($filters['date']) ?>" data-autosubmit>
        </div>
        <div class="field">
            <label class="label" for="filter-search">Поиск</label>
            <input type="search" id="filter-search" name="search" value="<?= e($filters['search']) ?>" placeholder="Название или описание">
        </div>
        <div class="field">
            <button class="btn" type="submit">Показать</button>
        </div>
    </form>

    <div class="event-list">
        <?php if (!$events): ?>
            <p class="empty">По выбранным условиям мероприятий нет. Измените отбор или сбросьте фильтры.</p>
        <?php endif; ?>

        <?php foreach ($events as $event): ?>
            <a class="event-row" href="<?= e(url('event', ['slug' => $event['slug']])) ?>">
                <span class="event-row-date">
                    <span class="day"><?= e(dt_day($event['starts_at'])) ?></span>
                    <span><?= e(dt_month_short($event['starts_at'])) ?> · <?= e(dt_time($event['starts_at'])) ?></span>
                </span>
                <span>
                    <span class="event-row-title"><?= e($event['title']) ?></span>
                    <span class="event-row-meta">
                        <?= e($event['venue_name'] ?? 'Площадка уточняется') ?>
                        · <?= e($event['category_name']) ?>
                        · <?= e(user_short_name(['last_name' => $event['organizer_last'], 'first_name' => $event['organizer_first'], 'middle_name' => null])) ?>
                    </span>
                </span>
                <span class="event-row-cat"><?= e($event['category_name']) ?></span>
                <span class="event-row-seats"><?= e(seat_label($event)) ?></span>
                <span class="event-row-arrow" aria-hidden="true">→</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">Как это работает</h2>
        <span class="section-count">Три шага</span>
    </div>
    <div class="steps">
        <div class="step">
            <span class="step-num">01</span>
            <h3 class="step-title">Выберите мероприятие</h3>
            <p class="step-text">Афиша с фильтрами по категории, площадке и дате. В карточке — программа и число свободных мест.</p>
        </div>
        <div class="step">
            <span class="step-num">02</span>
            <h3 class="step-title">Запишитесь</h3>
            <p class="step-text">Регистрация занимает минуту. Если мест нет, платформа предложит лист ожидания.</p>
        </div>
        <div class="step">
            <span class="step-num">03</span>
            <h3 class="step-title">Получите билет</h3>
            <p class="step-text">В личном кабинете появится цифровой токен с QR-кодом. По нему организатор отмечает посещение.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">Для организаторов</h2>
        <a class="btn btn-ghost" href="<?= e(url('login')) ?>">Войти в кабинет</a>
    </div>
    <p class="lead">
        Организаторы создают мероприятия, ведут программу, следят за списками участников и выгружают отчёты.
        Администратор управляет пользователями, справочниками и резервными копиями базы данных.
    </p>
</section>
