<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'patient') {
    header('Location: reg_login/login.php');
    exit;
}

include 'db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: reg_login/login.php');
    exit;
}

function ensureNotificationsTable($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureNotificationsTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read_single' && !empty($_POST['notification_id'])) {
        $notificationId = intval($_POST['notification_id']);
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
    } elseif ($action === 'mark_read_all') {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
}

$stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unreadCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уведомления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        .page-container { max-width: 1000px; margin: 40px auto; padding: 0 16px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 28px; }
        .notification-card { background: #fff; border-radius: 18px; border: 1px solid #e2e8f0; padding: 22px; margin-bottom: 16px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); }
        .notification-card.unread { background: #eef6ff; border-color: #bee3f8; }
        .notification-meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; color: #64748b; font-size: 13px; }
        .btn-back { text-decoration: none; color: #2563eb; }
        .badge-unread { background: #2563eb; color: white; border-radius: 999px; padding: 6px 12px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1>Уведомления</h1>
                <p style="margin: 8px 0 0; color: #64748b;">Здесь показываются сообщения от клиники и врача.</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($unreadCount > 0): ?>
                    <span class="badge-unread"><?= $unreadCount ?> непрочитанных</span>
                <?php endif; ?>
                <a href="index.php" class="btn-back">← Вернуться на главную</a>
            </div>
        </div>

        <form method="post" class="mb-4">
            <input type="hidden" name="action" value="mark_read_all">
            <button type="submit" class="btn btn-outline-primary">Отметить всё как прочитанное</button>
        </form>

        <?php if (count($notifications) === 0): ?>
            <div class="notification-card">
                <p>У вас пока нет уведомлений.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <div class="notification-card<?= $notification['is_read'] ? '' : ' unread' ?>">
                    <div class="notification-meta">
                        <span><?= htmlspecialchars(date('d.m.Y H:i', strtotime($notification['created_at']))) ?></span>
                        <span><?= $notification['is_read'] ? 'Прочитано' : 'Непрочитано' ?></span>
                    </div>
                    <p style="margin: 16px 0; line-height: 1.7;"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                    <?php if (!$notification['is_read']): ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="action" value="mark_read_single">
                            <input type="hidden" name="notification_id" value="<?= intval($notification['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Отметить как прочитанное</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
