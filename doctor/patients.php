<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'doctor') {
    header('Location: ../reg_login/login.php');
    exit;
}

include '../db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: ../reg_login/login.php');
    exit;
}

function ensurePersonnelUserIdColumn($conn) {
    $result = $conn->query("SHOW COLUMNS FROM `Персонал` LIKE 'user_id'");
    if ($result && $result->rowCount() === 0) {
        $conn->exec("ALTER TABLE `Персонал` ADD COLUMN `user_id` INT NULL AFTER `id`");
    }
}

function getDoctorPersonnelId($conn, $userId) {
    $stmt = $conn->prepare('SELECT id FROM `Персонал` WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? intval($row['id']) : null;
}

ensurePersonnelUserIdColumn($conn);
$doctorId = getDoctorPersonnelId($conn, $userId);

$patients = [];
$error = '';
if ($doctorId) {
    $stmt = $conn->prepare("SELECT p.id, p.фио, COUNT(z.id) AS visits, MAX(z.время_начало) AS last_visit
        FROM Пациенты p
        JOIN Запись z ON z.пациент_айди = p.id
        WHERE z.врач_айди = ?
        GROUP BY p.id, p.фио
        ORDER BY p.фио ASC");
    $stmt->execute([$doctorId]);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $error = 'Ваша учётная запись не связана с таблицей Персонал. Укажите doctor_id в профиле или обратитесь к администратору.';
}

$displayName = $_SESSION['user_login'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пациенты врача</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
        .container { max-width: 1140px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; padding: 28px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
        .table-wrap { overflow-x: auto; }
        .table th, .table td { vertical-align: middle; }
        .badge { border-radius: 999px; padding: 8px 12px; font-size: 12px; }
        .badge-list { background: #e0f2fe; color: #0369a1; }
        .btn-back { text-decoration: none; color: #2563eb; }
    </style>
</head>
<body>
<header>
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 style="margin:0; font-size:24px;">Пациенты врача</h1>
            <p style="margin:6px 0 0; color:#64748b;">Список пациентов, записанных на ваши приёмы.</p>
        </div>
        <a href="index.php" class="btn-back">← Назад</a>
    </div>
</header>
<main class="container">
    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (count($patients) === 0 && !$error): ?>
            <p>Пациенты не найдены. Убедитесь, что у вас есть записи в таблице <code>Запись</code>.</p>
        <?php elseif (count($patients) > 0): ?>
            <div class="table-wrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Пациент</th>
                            <th>Последний приём</th>
                            <th>Визитов</th>
                            <th>История</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><?= htmlspecialchars($patient['фио']) ?></td>
                                <td><?= htmlspecialchars($patient['last_visit'] ? date('d.m.Y H:i', strtotime($patient['last_visit'])) : '-') ?></td>
                                <td><span class="badge badge-list"><?= htmlspecialchars($patient['visits']) ?></span></td>
                                <td><a href="patient_history.php?patient_id=<?= htmlspecialchars($patient['id']) ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
