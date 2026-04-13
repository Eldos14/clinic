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

$patientId = intval($_GET['patient_id'] ?? 0);
if (!$doctorId || $patientId <= 0) {
    header('Location: patients.php');
    exit;
}

$authStmt = $conn->prepare("SELECT p.id, p.фио
    FROM Пациенты p
    JOIN Запись z ON z.пациент_айди = p.id
    WHERE p.id = ? AND z.врач_айди = ?
    LIMIT 1");
$authStmt->execute([$patientId, $doctorId]);
$patient = $authStmt->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    header('Location: patients.php');
    exit;
}

$stmt = $conn->prepare("SELECT z.время_начало, z.время_конца, z.причина_записа, z.статус, per.фио AS врач
    FROM Запись z
    LEFT JOIN Персонал per ON per.id = z.врач_айди
    WHERE z.пациент_айди = ?
    ORDER BY z.время_начало DESC");
$stmt->execute([$patientId]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$displayName = $_SESSION['user_login'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История пациента</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
        .container { max-width: 1140px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; padding: 28px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
        .table-wrap { overflow-x: auto; }
        .table th, .table td { vertical-align: middle; }
        .badge { border-radius: 999px; padding: 8px 12px; font-size: 12px; }
        .badge-status { background: #eef2ff; color: #1d4ed8; }
        .btn-back { text-decoration: none; color: #2563eb; }
    </style>
</head>
<body>
<header>
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 style="margin:0; font-size:24px;">История пациента</h1>
            <p style="margin:6px 0 0; color:#64748b;">Записи и статусы для пациента <?= htmlspecialchars($patient['фио']) ?>.</p>
        </div>
        <a href="patients.php" class="btn-back">← Назад к пациентам</a>
    </div>
</header>
<main class="container">
    <div class="card">
        <div class="table-wrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Дата начала</th>
                        <th>Дата конца</th>
                        <th>Врач</th>
                        <th>Причина</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history) === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">Записей не найдено.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($item['время_начало']))) ?></td>
                                <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($item['время_конца']))) ?></td>
                                <td><?= htmlspecialchars($item['врач']) ?></td>
                                <td><?= htmlspecialchars($item['причина_записа']) ?></td>
                                <td><span class="badge badge-status"><?= htmlspecialchars($item['статус']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
