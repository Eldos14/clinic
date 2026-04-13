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

function ensureDoctorScheduleTable($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS doctor_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        location VARCHAR(255) DEFAULT '',
        status VARCHAR(80) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getDoctorProfile($conn, $userId) {
    $stmt = $conn->prepare('SELECT * FROM doctor_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

ensureDoctorScheduleTable($conn);

$profile = getDoctorProfile($conn, $userId);
if (!$profile) {
    $stmt = $conn->prepare('INSERT INTO doctor_profiles (user_id, email) VALUES (?, ?)');
    $stmt->execute([$userId, $_SESSION['user_email'] ?? '']);
    $profile = getDoctorProfile($conn, $userId);
}

$scheduleStmt = $conn->prepare('SELECT * FROM doctor_schedule WHERE user_id = ? ORDER BY date, start_time');
$scheduleStmt->execute([$userId]);
$schedule = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

$weekStart = strtotime('monday this week');
if (date('N', $weekStart) === 7) {
    $weekStart = strtotime('monday last week');
}
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $dayTimestamp = strtotime("+{$i} days", $weekStart);
    $weekDays[] = [
        'date' => date('Y-m-d', $dayTimestamp),
        'label' => date('D d.m', $dayTimestamp),
        'dayName' => date('l', $dayTimestamp),
    ];
}

$events = [];
foreach ($schedule as $item) {
    $events[$item['date']][] = $item;
}

$timeSlots = range(7, 19);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание врача</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .calendar { display: grid; grid-template-columns: 100px repeat(7, minmax(0, 1fr)); gap: 1px; background: #d1d5db; border-radius: 18px; overflow: hidden; }
        .calendar-cell, .calendar-header, .time-cell { background: #fff; padding: 14px 12px; min-height: 90px; }
        .calendar-header { background: #0f172a; color: #f8fafc; text-align: center; font-weight: 700; }
        .calendar-header .day-name { font-size: 0.95rem; margin-bottom: 4px; }
        .calendar-header .day-date { font-size: 0.85rem; color: #cbd5e1; }
        .time-cell { background: #f8fafc; color: #475569; font-weight: 700; text-align: right; }
        .calendar-row { min-height: 90px; position: relative; }
        .event-card { background: #e0f2fe; border-left: 4px solid #0284c7; border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.07); }
        .event-card.archived { background: #f8fafc; border-color: #94a3b8; }
        .event-time { font-size: 0.82rem; color: #0f172a; font-weight: 700; }
        .event-title { margin: 6px 0 0; font-size: 0.92rem; }
        .event-location { margin: 4px 0 0; font-size: 0.82rem; color: #475569; }
        .no-schedule { text-align: center; color: #52606d; padding: 40px 0; }
        .btn-back { text-decoration: none; color: #2563eb; }
        .legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px; }
        .legend-item { display: flex; align-items: center; gap: 8px; color: #475569; font-size: 14px; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
        .legend-active { background: #0ea5e9; }
        .legend-archived { background: #64748b; }
    </style>
</head>
<body>
<header>
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 style="margin:0; font-size:24px;">Расписание врача</h1>
            <p style="margin:6px 0 0; color:#64748b;">Просмотрите расписание за неделю в удобном календарном виде.</p>
            <div class="legend">
                <div class="legend-item"><span class="legend-dot legend-active"></span> Активная смена</div>
                <div class="legend-item"><span class="legend-dot legend-archived"></span> Архив</div>
            </div>
        </div>
        <a href="index.php" class="btn-back">← Назад</a>
    </div>
</header>
<main class="container">
    <?php if (count($schedule) === 0): ?>
        <div class="card">
            <div class="no-schedule">
                <h3>Расписание пока не создано.</h3>
                <p>Администратор может добавить рабочие смены и доступное время для вас.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="calendar">
            <div class="calendar-header"></div>
            <?php foreach ($weekDays as $day): ?>
                <div class="calendar-header">
                    <div class="day-name"><?= htmlspecialchars($day['label']) ?></div>
                    <div class="day-date"><?= htmlspecialchars($day['dayName']) ?></div>
                </div>
            <?php endforeach; ?>
            <?php foreach ($timeSlots as $hour): ?>
                <div class="time-cell"><?= sprintf('%02d:00', $hour) ?></div>
                <?php foreach ($weekDays as $day): ?>
                    <div class="calendar-cell calendar-row">
                        <?php if (!empty($events[$day['date']])): ?>
                            <?php foreach ($events[$day['date']] as $item): ?>
                                <?php if (intval(substr($item['start_time'], 0, 2)) !== $hour) continue; ?>
                                <div class="event-card<?= $item['status'] !== 'active' ? ' archived' : '' ?>">
                                    <div class="event-time"><?= htmlspecialchars(substr($item['start_time'], 0, 5)) ?>–<?= htmlspecialchars(substr($item['end_time'], 0, 5)) ?></div>
                                    <div class="event-title"><?= htmlspecialchars($item['location'] ?: 'Рабочая смена') ?></div>
                                    <?php if (!empty($item['note'])): ?>
                                        <div class="event-location"><?= htmlspecialchars($item['note']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
