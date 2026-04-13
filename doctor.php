<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: reg_login/login.php');
    exit;
}

include 'db.php';

$doctor_id = intval($_GET['id'] ?? 0);
if ($doctor_id <= 0) {
    header('Location: doctors.php');
    exit;
}

$stmt = $conn->prepare("SELECT per.id, per.фио, per.должность, per.контакты, sp.Специальность, f.Филиалы, f.адрес AS филиал_адрес, m.кабинет
    FROM Персонал per
    LEFT JOIN СпециализацииПерсонала sp ON sp.персонал_айди = per.id
    LEFT JOIN МестоРаботыВрача m ON m.персонал_айди = per.id
    LEFT JOIN ФилиалыОтделения f ON f.id = m.ФилиалыОтделения
    WHERE per.id = ?");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    header('Location: doctors.php');
    exit;
}

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

$searchDate = $_GET['date'] ?? date('Y-m-d');
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $searchDate) ? $searchDate : date('Y-m-d');

$timeSlots = [
    '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'
];

$stmtBusy = $conn->prepare("SELECT время_начало FROM Запись WHERE врач_айди = ? AND DATE(время_начало) = ? AND статус <> 'Отменено'");
$stmtBusy->execute([$doctor_id, $selectedDate]);
$busyRows = $stmtBusy->fetchAll(PDO::FETCH_ASSOC);
$busySlots = array_map(function($row) {
    return date('H:i', strtotime($row['время_начало']));
}, $busyRows);

$stmtUpcoming = $conn->prepare("SELECT p.фио AS пациент, z.время_начало, z.статус
    FROM Запись z
    LEFT JOIN Пациенты p ON p.id = z.пациент_айди
    WHERE z.врач_айди = ? AND z.время_начало >= ? AND z.статус <> 'Отменено'
    ORDER BY z.время_начало ASC
    LIMIT 5");
$stmtUpcoming->execute([$doctor_id, date('Y-m-d H:i:s')]);
$upcomingAppointments = $stmtUpcoming->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedDate = $_POST['date'] ?? $selectedDate;
    $selectedTime = $_POST['time'] ?? '';
    $reason = trim($_POST['reason'] ?? 'Консультация');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $errors[] = 'Неверная дата.';
    }
    if (!in_array($selectedTime, $timeSlots, true)) {
        $errors[] = 'Выберите корректное время.';
    }
    if (empty($reason)) {
        $reason = 'Консультация';
    }

    $bookedCheck = $conn->prepare("SELECT COUNT(*) FROM Запись WHERE врач_айди = ? AND время_начало = ? AND статус <> 'Отменено'");
    $startDateTime = date('Y-m-d H:i:s', strtotime("{$selectedDate} {$selectedTime}"));
    $bookedCheck->execute([$doctor_id, $startDateTime]);
    $alreadyBooked = $bookedCheck->fetchColumn();

    if ($alreadyBooked) {
        $errors[] = 'Это время уже занято. Выберите другой слот.';
    }

    if (empty($errors)) {
        $endDateTime = date('Y-m-d H:i:s', strtotime("{$startDateTime} +1 hour"));
        $insert = $conn->prepare("INSERT INTO Запись (пациент_айди, врач_айди, время_начало, время_конца, причина_записа, статус)
            VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$patient['id'], $doctor_id, $startDateTime, $endDateTime, $reason, 'Запланировано']);
        $success = 'Ваша запись успешно создана.';
        $busySlots[] = $selectedTime;
    }
}

$days = [];
$daysLabels = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
for ($i = 0; $i < 7; $i++) {
    $dateValue = date('Y-m-d', strtotime("+$i days"));
    $days[] = [
        'value' => $dateValue,
        'label' => $daysLabels[date('w', strtotime($dateValue))] . ', ' . date('d.m.', strtotime($dateValue)),
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Врач <?= htmlspecialchars($doctor['фио']) ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f0f4f8;
            color: #333;
            margin: 0;
        }
        header {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .nav { display:flex; gap:20px; }
        .nav a { text-decoration:none; color:#555; font-weight:500; }
        .nav a:hover { color:#2a7de1; }
        .header-right { display:flex; align-items:center; gap:14px; }
        .user { width:40px; height:40px; background:#2a7de1; color:#fff; border-radius:50%; display:grid; place-items:center; font-weight:700; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .back-btn { display:inline-block; margin-bottom:20px; color:#2a7de1; text-decoration:none; font-weight:500; }
        .doctor-detail { display:grid; grid-template-columns: 1.1fr 0.9fr; gap: 30px; }
        .doctor-card, .booking-card { background:#fff; border-radius:18px; padding:28px; box-shadow:0 20px 60px rgba(15,40,80,0.08); }
        .doctor-header { display:flex; align-items:center; gap:20px; margin-bottom:24px; }
        .doctor-avatar { width:98px; height:98px; border-radius:50%; background:linear-gradient(135deg,#2a7de1,#17a2b8); display:grid; place-items:center; color:#fff; font-size:36px; font-weight:700; }
        .doctor-meta h1 { margin:0; font-size:28px; color:#1d3468; }
        .doctor-meta p { margin:8px 0 0; color:#5f6c7e; }
        .info-list { display:grid; gap:16px; margin-bottom:24px; }
        .info-item { display:grid; gap:8px; }
        .info-item strong { color:#1f3867; }
        .work-schedule { display:grid; gap:12px; background:#f4f8ff; border-radius:14px; padding:18px; }
        .work-schedule span { color:#1f3a72; font-weight:600; }
        .calendar-row { display:grid; grid-template-columns: repeat(7,minmax(0,1fr)); gap:10px; margin-bottom:20px; }
        .date-pill { display:block; padding:12px 10px; border-radius:14px; border:1px solid #d2d6dc; background:#fff; color:#1f3a72; text-align:center; text-decoration:none; font-size:13px; font-weight:600; }
        .date-pill.active { background:#2a7de1; color:#fff; border-color:#2a7de1; }
        .date-pill:hover { border-color:#2a7de1; }
        .slots-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap:12px; }
        .slot-label { display:block; padding:14px; border-radius:12px; border:1px solid #d2d6dc; text-align:center; cursor:pointer; transition: 0.2s; }
        .slot-label input { display:none; }
        .slot-label.available:hover { background:#eef6ff; border-color:#2a7de1; }
        .slot-label.selected { background:#2a7de1; color:#fff; border-color:#2a7de1; }
        .slot-label.busy { background:#f6f6f6; color:#999; border-color:#e2e7ee; cursor:not-allowed; }
        .slot-legend { display:flex; gap:12px; flex-wrap: wrap; margin-bottom:18px; }
        .legend-item { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:12px; font-size:13px; font-weight:600; }
        .legend-item.available { background:#eef6ff; color:#1f3a72; border:1px solid #cfe4ff; }
        .legend-item.busy { background:#f6f6f6; color:#6b7280; border:1px solid #e2e7ee; }
        .legend-item::before { content:''; display:inline-block; width:12px; height:12px; border-radius:50%; }
        .legend-item.available::before { background:#2a7de1; }
        .legend-item.busy::before { background:#999; }
        .upcoming-block { margin-bottom:20px; padding:18px; border-radius:16px; background:#f8fbff; border:1px solid #dce9fb; }
        .upcoming-block h3 { margin:0 0 12px; font-size:16px; color:#1f3a72; }
        .upcoming-block ul { list-style:none; padding:0; margin:0; display:grid; gap:10px; }
        .upcoming-block li { padding:12px 14px; border-radius:12px; background:#fff; border:1px solid #e8eff8; color:#334155; }
        .upcoming-block li strong { color:#1f3a72; }
        .booking-note { margin:18px 0 0; color:#4a5568; font-size:14px; }
        .btn-submit { width:100%; background:#2a7de1; color:#fff; border:none; padding:14px 18px; border-radius:14px; cursor:pointer; font-weight:700; transition:background .25s; }
        .btn-submit:hover { background:#1b5db3; }
        .alert { padding:16px 18px; border-radius:14px; margin-bottom:20px; }
        .alert-success { background:#e8f4ff; color:#1b4f8d; }
        .alert-error { background:#ffe4e5; color:#8d1d2e; }
        @media (max-width: 960px) { .doctor-detail { grid-template-columns: 1fr; } .calendar-row { grid-template-columns: repeat(4,minmax(0,1fr)); } }
        @media (max-width: 640px) { .calendar-row { grid-template-columns: repeat(2,minmax(0,1fr)); } }
    </style>
</head>
<body>
<header>
    <div class="header-container">
        <div class="logo"><img src="images/icons/damumed_logo.png" alt="Логотип" style="height:40px;"></div>
        <nav class="nav">
            <a href="index.php">Главная</a>
            <a href="doctors.php">Врачи</a>
            <a href="myDetails.php">Мои данные</a>
        </nav>
        <div class="header-right">
            <span class="user"><?= strtoupper(substr($patient['фио'], 0, 1)) ?></span>
            <a href="logout.php" class="logout">Выйти</a>
        </div>
    </div>
</header>

<div class="container">
    <a class="back-btn" href="doctors.php">← Вернуться к списку врачей</a>

    <div class="doctor-detail">
        <div class="doctor-card">
            <div class="doctor-header">
                <div class="doctor-avatar"><?= strtoupper(substr($doctor['фио'], 0, 1)) ?></div>
                <div class="doctor-meta">
                    <h1><?= htmlspecialchars($doctor['фио']) ?></h1>
                    <p><?= htmlspecialchars($doctor['должность']) ?></p>
                </div>
            </div>

            <div class="info-list">
                <?php if (!empty($doctor['Специальность'])): ?>
                <div class="info-item"><strong>Специализация</strong><span><?= htmlspecialchars($doctor['Специальность']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($doctor['Филиалы'])): ?>
                <div class="info-item"><strong>Филиал</strong><span><?= htmlspecialchars($doctor['Филиалы']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($doctor['филиал_адрес'])): ?>
                <div class="info-item"><strong>Адрес филиала</strong><span><?= htmlspecialchars($doctor['филиал_адрес']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($doctor['кабинет'])): ?>
                <div class="info-item"><strong>Кабинет</strong><span><?= htmlspecialchars($doctor['кабинет']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($doctor['контакты'])): ?>
                <div class="info-item"><strong>Контакты</strong><span><?= htmlspecialchars($doctor['контакты']) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="work-schedule">
                <div><strong>Режим работы</strong></div>
                <span>Пн–Пт, 09:00–18:00</span>
                <span>Запись на 1 час</span>
            </div>
        </div>

        <div class="booking-card">
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if (!empty($errors)): ?><div class="alert alert-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>

            <h2>Выберите день и время</h2>
            <div class="calendar-row">
                <?php foreach ($days as $day): ?>
                    <a href="doctor.php?id=<?= $doctor_id ?>&date=<?= $day['value'] ?>" class="date-pill<?= $day['value'] === $selectedDate ? ' active' : '' ?>"><?= $day['label'] ?></a>
                <?php endforeach; ?>
            </div>

            <div class="slot-legend">
                <span class="legend-item available">Свободно</span>
                <span class="legend-item busy">Занято</span>
            </div>

            <?php if (!empty($upcomingAppointments)): ?>
            <div class="upcoming-block">
                <h3>Ближайшие записи</h3>
                <ul>
                    <?php foreach ($upcomingAppointments as $appointment): ?>
                        <li>
                            <strong><?= htmlspecialchars(date('d.m.Y H:i', strtotime($appointment['время_начало']))) ?></strong>
                            — <?= htmlspecialchars($appointment['пациент']) ?>
                            (<?= htmlspecialchars($appointment['статус']) ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" action="doctor.php?id=<?= $doctor_id ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                <div class="slots-grid">
                    <?php foreach ($timeSlots as $time):
                        $busy = in_array($time, $busySlots, true);
                        $selected = isset($_POST['time']) && $_POST['time'] === $time;
                    ?>
                        <label class="slot-label <?= $busy ? 'busy' : 'available' ?><?= $selected ? ' selected' : '' ?>">
                            <input type="radio" name="time" value="<?= $time ?>" <?= $busy ? 'disabled' : '' ?> <?= $selected ? 'checked' : '' ?>>
                            <?= $time ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="booking-note">Если вы хотите, укажите причину визита в комментарии ниже или оставьте стандартную.</div>
                <input type="hidden" name="reason" value="Запись к врачу">
                <button type="submit" class="btn-submit">Записаться на <?= htmlspecialchars($selectedDate) ?></button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
