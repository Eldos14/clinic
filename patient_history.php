<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: reg_login/login.php');
    exit;
}

include 'db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: reg_login/login.php');
    exit;
}

$stmtPatient = $conn->prepare("SELECT id, фио FROM Пациенты WHERE user_id = ?");
$stmtPatient->execute([$userId]);
$patient = $stmtPatient->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: myDetails.php');
    exit;
}

$patient_id = $patient['id'];

$stmt = $conn->prepare("SELECT z.id, z.время_начало, z.время_конца, z.причина_записа, z.статус, per.фио AS врач
    FROM Запись z
    LEFT JOIN Персонал per ON per.id = z.врач_айди
    WHERE z.`пациент_айди` = ?
    ORDER BY z.время_начало DESC");
$stmt->execute([$patient_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История посещений</title>
    <link rel="stylesheet" href="css/style.css">
<style>
    .history-card {
        background: white;
        border-radius: 22px;
        padding: 28px;
        box-shadow: 0 24px 70px rgba(15, 40, 80, 0.08);
        max-width: 1180px;
        margin: 28px auto 60px;
    }

    .history-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 20px;
    }

    .history-card-header h2 {
        margin: 0;
        font-size: 22px;
        color: #1f3a72;
    }

    .history-subtitle {
        margin: 8px 0 0;
        color: #6b7280;
        font-size: 14px;
    }

    .history-table-wrap {
        overflow-x: auto;
    }

    .history-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 720px;
    }

    .history-table th,
    .history-table td {
        padding: 16px 18px;
        border-bottom: 1px solid #e8ecf1;
        text-align: left;
        vertical-align: middle;
    }

    .history-table th {
        background: #f5f8ff;
        color: #1e3a72;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .history-table tbody tr:hover {
        background: #f8fbff;
    }

    .history-no-record {
        text-align: center;
        padding: 24px 0;
        color: #6b7280;
    }

    .page-content {
        padding: 20px 0 60px;
    }

    .page-title-block {
        background: white;
        max-width: 1200px;
        margin: 20px auto 0;
        padding: 28px 24px;
        border-radius：
        22px;
        box-shadow: 0 24px 70px rgba(15, 40,    80, 0.08);
    }
</style>
</head>

<body>  
<?php
include 'header.php';
?>
<main class="page-content">
    <section class="page-title-block">
        <div class="page-title-inner">
            <h1>Моя история визитов</h1>
            <p>Вы видите все записи своей личной истории посещений.</p>
        </div>
    </section>

    <div class="history-card">
        <div class="history-card-header">
            <div>
                <h2>Пациент: <?= htmlspecialchars($patient['фио']) ?></h2>
                <p class="history-subtitle">Последние посещения и статус записи</p>
            </div>
            <a href="index.php" class="btn">← Вернуться на главную</a>
        </div>

        <div class="history-table-wrap">
            <table class="history-table">
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
                        <td colspan="5" class="history-no-record">Записей не найдено.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($history as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars($h['время_начало']) ?></td>
                            <td><?= htmlspecialchars($h['время_конца']) ?></td>
                            <td><?= htmlspecialchars($h['врач']) ?></td>
                            <td><?= htmlspecialchars($h['причина_записа']) ?></td>
                            <td><?= htmlspecialchars($h['статус']) ?></td>
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
