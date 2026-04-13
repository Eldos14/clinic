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

function ensureDoctorProfileTable($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS doctor_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        doctor_id INT DEFAULT NULL,
        fio VARCHAR(255) DEFAULT '',
        position VARCHAR(255) DEFAULT '',
        specialization VARCHAR(255) DEFAULT '',
        experience VARCHAR(255) DEFAULT '',
        office VARCHAR(255) DEFAULT '',
        contacts VARCHAR(255) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        note TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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

function ensurePersonnelUserIdColumn($conn) {
    $result = $conn->query("SHOW COLUMNS FROM `Персонал` LIKE 'user_id'");
    if ($result && $result->rowCount() === 0) {
        $conn->exec("ALTER TABLE `Персонал` ADD COLUMN `user_id` INT NULL AFTER `id`");
    }
}

function getDoctorProfile($conn, $userId) {
    $stmt = $conn->prepare('SELECT * FROM doctor_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getDoctorPersonnelId($conn, $userId) {
    $stmt = $conn->prepare('SELECT id FROM `Персонал` WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? intval($row['id']) : null;
}

function addNotification($conn, $patientId, $message) {
    $stmt = $conn->prepare('SELECT user_id FROM Пациенты WHERE id = ? LIMIT 1');
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($patient && !empty($patient['user_id'])) {
        $insert = $conn->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
        $insert->execute([$patient['user_id'], $message]);
    }
}

ensureDoctorProfileTable($conn);
ensureDoctorScheduleTable($conn);
ensureNotificationsTable($conn);
ensurePersonnelUserIdColumn($conn);

$profile = getDoctorProfile($conn, $userId);
if (!$profile) {
    $insert = $conn->prepare('INSERT INTO doctor_profiles (user_id, email) VALUES (?, ?)');
    $insert->execute([$userId, $_SESSION['user_email'] ?? '']);
    $profile = getDoctorProfile($conn, $userId);
}

$doctorPersonnelId = getDoctorPersonnelId($conn, $userId);
if (!$doctorPersonnelId && !empty($profile['doctor_id'])) {
    $doctorPersonnelId = intval($profile['doctor_id']);
}

$pendingAppointments = [];
$upcomingAppointments = [];
$patients = [];
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = intval($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($doctorPersonnelId && $appointmentId > 0 && in_array($action, ['accept', 'cancel'], true)) {
        $newStatus = $action === 'accept' ? 'Принято' : 'Отменено';
        $update = $conn->prepare('UPDATE Запись SET статус = ? WHERE id = ? AND врач_айди = ?');
        $update->execute([$newStatus, $appointmentId, $doctorPersonnelId]);

        $appointmentStmt = $conn->prepare('SELECT пациент_айди, время_начало FROM Запись WHERE id = ? LIMIT 1');
        $appointmentStmt->execute([$appointmentId]);
        $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
        if ($appointment) {
            $message = $action === 'accept'
                ? 'Ваша запись на ' . date('d.m.Y H:i', strtotime($appointment['время_начало'])) . ' принята врачом.'
                : 'Ваша запись на ' . date('d.m.Y H:i', strtotime($appointment['время_начало'])) . ' отменена врачом.';
            addNotification($conn, $appointment['пациент_айди'], $message);
        }
    }
}

if ($doctorPersonnelId) {
    $stmt = $conn->prepare("SELECT z.id, z.пациент_айди, z.время_начало, z.время_конца, z.статус, z.причина_записа, p.фио AS пациент
        FROM Запись z
        LEFT JOIN Пациенты p ON p.id = z.пациент_айди
        WHERE z.врач_айди = ?
        ORDER BY z.время_начало ASC");
    $stmt->execute([$doctorPersonnelId]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($appointments as $appointment) {
        $patients[$appointment['пациент_айди']] = [
            'фио' => $appointment['пациент'],
            'last_visit' => $appointment['время_начало'],
            'status' => $appointment['статус'],
        ];
        if ($appointment['статус'] === 'Запланировано') {
            $pendingAppointments[] = $appointment;
        }
        if (strtotime($appointment['время_начало']) >= time() && count($upcomingAppointments) < 5) {
            $upcomingAppointments[] = $appointment;
        }
    }
}

$scheduleStmt = $conn->prepare('SELECT * FROM doctor_schedule WHERE user_id = ? AND date >= ? ORDER BY date, start_time');
$scheduleStmt->execute([$userId, $today]);
$scheduleList = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

$displayName = $profile['fio'] ?: $_SESSION['user_login'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель врача</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1e293b; font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
        .header-container { max-width: 1140px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .nav-links { display: flex; gap: 14px; flex-wrap: wrap; }
        .nav-links a { text-decoration: none; color: #475569; padding: 10px 16px; border-radius: 12px; border: 1px solid transparent; transition: border-color .2s, background .2s; }
        .nav-links a:hover { background: #eef2ff; border-color: #c7d2fe; color: #1d4ed8; }
        .dashboard { max-width: 1140px; margin: 24px auto; padding: 0 20px; }
        .card { border: none; border-radius: 20px; box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08); }
        .stat-card { min-height: 170px; }
        .stat-card h3 { margin-bottom: 10px; font-size: 1.75rem; }
        .stat-card p { color: #64748b; }
        .patient-card, .request-card, .schedule-card { background: #fff; border-radius: 20px; padding: 24px; margin-bottom: 24px; box-shadow: 0 18px 48px rgba(15, 23, 42, 0.06); }
        .badge { font-size: 12px; padding: 8px 12px; border-radius: 999px; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-accepted { background: #d1fae5; color: #15795c; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .table-wrap { overflow-x: auto; }
        .table th, .table td { vertical-align: middle; }
        .alert-note { border-radius: 18px; background: #f8fafc; padding: 18px; color: #334155; }
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <div>
            <p style="margin: 0; color: #64748b; font-size: 14px;">Доктор</p>
            <h1 style="margin: .2rem 0 0; font-size: 28px;">Добро пожаловать, <?= htmlspecialchars($displayName) ?></h1>
            <p style="margin: 8px 0 0; color: #64748b;">Здесь вы видите список пациентов, запросы и своё расписание.</p>
        </div>
        <div class="nav-links">
            <a href="index.php">Главная</a>
            <a href="patients.php">Пациенты</a>
            <a href="profile.php">Профиль</a>
            <a href="schedule.php">Расписание</a>
            <a href="create_appointment.php">Новая запись</a>
            <a href="../logout.php">Выйти</a>
        </div>
    </div>
</header>
<main class="dashboard">
    <?php if (!$doctorPersonnelId): ?>
        <div class="patient-card">
            <div class="alert-note">
                <strong>Внимание:</strong> ваша учётная запись ещё не связана с таблицей <code>Персонал</code>.
                Обратитесь к администратору для привязки вашего профиля к врачу, чтобы видеть пациентов и записи.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card p-4 stat-card">
                <h3><?= count($patients) ?></h3>
                <p>Пациентов в моей практике</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 stat-card">
                <h3><?= count($pendingAppointments) ?></h3>
                <p>Новых запросов на прием</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-4 stat-card">
                <h3><?= count($scheduleList) ?></h3>
                <p>Доступных смен в расписании</p>
            </div>
        </div>
    </div>

    <?php if ($doctorPersonnelId): ?>
        <div class="patient-card">
            <h4>Запросы на приём</h4>
            <?php if (count($pendingAppointments) === 0): ?>
                <p>Нет новых запросов от пациентов.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Пациент</th>
                                <th>Дата</th>
                                <th>Причина</th>
                                <th>Статус</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingAppointments as $appointment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appointment['пациент']) ?></td>
                                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($appointment['время_начало']))) ?></td>
                                    <td><?= htmlspecialchars($appointment['причина_записа']) ?></td>
                                    <td><span class="badge badge-pending">Запланировано</span></td>
                                    <td>
                                        <form method="post" action="index.php" class="d-flex gap-2">
                                            <input type="hidden" name="appointment_id" value="<?= htmlspecialchars($appointment['id']) ?>">
                                            <button type="submit" name="action" value="accept" class="btn btn-sm btn-success">Принять</button>
                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-danger">Отменить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="patient-card">
            <h4>Ближайшие приёмы</h4>
            <?php if (count($upcomingAppointments) === 0): ?>
                <p>Сегодня и ближайшие дни приёмов не назначено.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Пациент</th>
                                <th>Дата</th>
                                <th>Причина</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($appointment['пациент']) ?></td>
                                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($appointment['время_начало']))) ?></td>
                                    <td><?= htmlspecialchars($appointment['причина_записа']) ?></td>
                                    <td>
                                        <?php if ($appointment['статус'] === 'Принято'): ?>
                                            <span class="badge badge-accepted">Принято</span>
                                        <?php elseif ($appointment['статус'] === 'Отменено'): ?>
                                            <span class="badge badge-cancelled">Отменено</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending"><?= htmlspecialchars($appointment['статус']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="patient-card">
        <h4>Пациенты</h4>
        <?php if (count($patients) === 0): ?>
            <p>Пока нет пациентов. После создания привязки к врачу вы увидите их здесь.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Пациент</th>
                            <th>Последняя запись</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $info): ?>
                            <tr>
                                <td><?= htmlspecialchars($info['фио']) ?></td>
                                <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($info['last_visit']))) ?></td>
                                <td>
                                    <?php if ($info['status'] === 'Принято'): ?>
                                        <span class="badge badge-accepted">Принято</span>
                                    <?php elseif ($info['status'] === 'Отменено'): ?>
                                        <span class="badge badge-cancelled">Отменено</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending"><?= htmlspecialchars($info['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="schedule-card">
        <h4>Ближайшее расписание</h4>
        <?php if (count($scheduleList) === 0): ?>
            <p>Расписание не заполнено. Администратор может создать доступные смены на странице расписания.</p>
        <?php else: ?>
            <?php foreach ($scheduleList as $item): ?>
                <div class="mb-3 p-3 border rounded-3">
                    <strong><?= htmlspecialchars(date('d.m.Y', strtotime($item['date']))) ?> <?= htmlspecialchars(substr($item['start_time'], 0, 5)) ?>–<?= htmlspecialchars(substr($item['end_time'], 0, 5)) ?></strong>
                    <div style="color:#64748b;"><?= htmlspecialchars($item['location'] ?: 'Обычная смена') ?> <?= $item['note'] ? '· ' . htmlspecialchars($item['note']) : '' ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
