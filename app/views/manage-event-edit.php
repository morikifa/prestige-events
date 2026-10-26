<header class="page-head">
    <div>
        <p class="label">Управление · <?= $event ? 'Редактирование' : 'Создание' ?></p>
        <h1 class="page-title"><?= $event ? e($event['title']) : 'Новое мероприятие' ?></h1>
        <p class="page-sub">Заполните карточку: она отображается в афише после публикации.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('manage/events')) ?>">К списку</a>
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
    <form class="form" method="post" action="<?= e(url('manage/event', $event ? ['id' => $event['id']] : [])) ?>">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="field span-2">
                <label class="label" for="title">Название</label>
                <input type="text" id="title" name="title" value="<?= e($values['title']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="slug">Адрес страницы (slug)</label>
                <input type="text" id="slug" name="slug" value="<?= e($values['slug']) ?>" placeholder="Заполнится из названия">
                <span class="hint">Латиница, цифры и дефисы. Должен быть уникальным.</span>
            </div>
            <div class="field">
                <label class="label" for="status">Статус</label>
                <select id="status" name="status">
                    <?php foreach (event_status_labels() as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $values['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="category_id">Категория</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Выберите категорию</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) $values['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="label" for="venue_id">Площадка</label>
                <select id="venue_id" name="venue_id">
                    <option value="">Не указана</option>
                    <?php foreach ($venues as $venue): ?>
                        <option value="<?= (int) $venue['id'] ?>" <?= (int) $values['venue_id'] === (int) $venue['id'] ? 'selected' : '' ?>>
                            <?= e($venue['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($organizers): ?>
                <div class="field">
                    <label class="label" for="organizer_id">Организатор</label>
                    <select id="organizer_id" name="organizer_id">
                        <?php foreach ($organizers as $organizer): ?>
                            <option value="<?= (int) $organizer['id'] ?>" <?= (int) $values['organizer_id'] === (int) $organizer['id'] ? 'selected' : '' ?>>
                                <?= e($organizer['last_name'] . ' ' . $organizer['first_name']) ?> · <?= e($organizer['role_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="field">
                <label class="label" for="capacity">Вместимость, чел.</label>
                <input type="number" id="capacity" name="capacity" min="1" value="<?= e($values['capacity']) ?>" placeholder="Без ограничения">
            </div>

            <div class="field span-2">
                <label class="label" for="description">Описание</label>
                <textarea id="description" name="description" rows="6"><?= e($values['description']) ?></textarea>
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
                <label class="label" for="registration_opens_at">Регистрация открывается</label>
                <input type="datetime-local" id="registration_opens_at" name="registration_opens_at" value="<?= e($values['registration_opens_at']) ?>">
            </div>
            <div class="field">
                <label class="label" for="registration_closes_at">Регистрация закрывается</label>
                <input type="datetime-local" id="registration_closes_at" name="registration_closes_at" value="<?= e($values['registration_closes_at']) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit">Сохранить</button>
            <a class="form-note" href="<?= e(url('manage/events')) ?>">Отмена</a>
        </div>
    </form>
</section>
