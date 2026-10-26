<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

$user = currentUser($pdo);
$pageTitle = 'Отметка посещения';
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

if (!$user) {
    require __DIR__ . '/includes/header.php';
    echo '<h2 class="page-title">Вход</h2><p class="lead">Ссылка из QR-кода работает только под учётной записью организатора или администратора. Войдите (организатор: organizer / 5678) и снова откройте QR-код, либо введите токен на странице участников.</p><p><a class="btn" href="login.php">Войти</a></p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

requireRole($user, ['organizer', 'admin']);

$stmt = $pdo->prepare('SELECT reg.*, e.title, e.starts_at, e.organizer_id, u.last_name, u.first_name, u.middle_name, u.email FROM registrations reg INNER JOIN events e ON e.id = reg.event_id INNER JOIN users u ON u.id = reg.user_id WHERE reg.qr_token = ?');
$stmt->execute([$token]);
$reg = $stmt->fetch();

if ($reg && $user['role_code'] !== 'admin' && (int) $reg['organizer_id'] !== (int) $user['id']) {
    $reg = null;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reg) {
    checkCsrf();
    if ($reg['status'] === 'cancelled') {
        $message = 'Запись по этому билету отменена, посещение не отмечено.';
    } elseif ($reg['status'] === 'attended') {
        $message = 'Участник уже отмечен ранее.';
    } else {
        $upd = $pdo->prepare("UPDATE registrations SET status = 'attended', attended_at = ? WHERE id = ?");
        $upd->execute([nowText(), $reg['id']]);
        logAction($pdo, $user['id'], 'checkin', 'registrations', (int) $reg['id']);
        $message = 'Посещение отмечено в базе данных (статус attended).';
        $reg['status'] = 'attended';
        $reg['attended_at'] = nowText();
    }
}

require __DIR__ . '/includes/header.php';
?>
<h2 class="page-title">Посещение</h2>
<?php if (!$reg): ?>
<p class="lead">Билет с токеном «<?= e($token) ?>» не найден или относится к мероприятию другого организатора.</p>
<?php else: ?>
<?php if ($message): ?><div class="message"><?= e($message) ?></div><?php endif; ?>
<div class="side-box narrow">
<h3><?= e($reg['title']) ?></h3>
<p><strong>Гость:</strong> <?= e(fullName($reg)) ?></p>
<p><strong>Email:</strong> <?= e($reg['email']) ?></p>
<p><strong>Дата:</strong> <?= formatDate($reg['starts_at']) ?></p>
<p><strong>Статус:</strong> <span class="badge"><?= e(regStatusName($reg['status'])) ?></span></p>
<div class="ticket-token"><?= e($reg['qr_token']) ?></div>
<?php if ($reg['status'] !== 'attended' && $reg['status'] !== 'cancelled'): ?>
<form method="post">
<?= csrfField() ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<button class="btn" type="submit">Отметить посещение</button>
</form>
<?php endif; ?>
</div>
<p style="margin-top:16px"><a href="participants.php?event_id=<?= (int) $reg['event_id'] ?>">К списку участников</a></p>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
