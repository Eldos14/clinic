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

ensurePersonnelUserIdColumn($conn);

$stmt = $conn->prepare('SELECT id FROM `Персонал` WHERE user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$doctor) {
    header('Location: index.php?message=need_link_doctor');
    exit;
}

$doctorId = $doctor['id'];
$patients = $conn->query('SELECT id, фио FROM Пациенты ORDER BY фио')->fetchAll(PDO::FETCH_ASSOC);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = intval($_POST['patient'] ?? 0);
    $start = $_POST['start'] ?? '';
    $end = $_POST['end'] ?? '';
    $reason = trim($_POST['reason'] ?? 'Консультация');
    $status = 'Принято';

    if ($patientId <= 0) {
        $errors[] = 'Выберите пациента.';
    }
    if (!$start || !$end) {
        $errors[] = 'Укажите время начала и конца записи.';
    }
    if (strtotime($end) <= strtotime($start)) {
        $errors[] = 'Время конца должно быть позже времени начала.';
    }

    if (empty($errors)) {
        $insert = $conn->prepare('INSERT INTO Запись (пациент_айди, врач_айди, время_начало, время_конца, причина_записа, статус) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$patientId, $doctorId, $start, $end, $reason, $status]);
        $success = 'Запись создана и принята вами.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать запись</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; padding: 28px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
        .form-label { font-weight: 600; }
        .form-control { border-radius: 12px; }
        .btn-primary { border-radius: 14px; padding: 12px 24px; }
        .alert { border-radius: 14px; }
    </style>
</head>
<body>
<header>
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 style="margin:0; font-size:24px;">Создать запись пациента</h1>
            <p style="margin:6px 0 0; color:#64748b;">Запишите пациента на прием от имени врача.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">← Назад</a>
    </div>
</header>
<main class="container">
    <div class="card">
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>
        <form method="post" action="create_appointment.php">
            <div class="mb-3">
                <label class="form-label" for="patient">Пациент</label>
                <select id="patient" name="patient" class="form-control" required>
                    <option value="">Выберите пациента</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?= htmlspecialchars($patient['id']) ?>"><?= htmlspecialchars($patient['фио']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="start">Дата и время начала</label>
                    <input type="datetime-local" id="start" name="start" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="end">Дата и время конца</label>
                    <input type="datetime-local" id="end" name="end" class="form-control" required>
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label" for="reason">Причина визита</label>
                <input type="text" id="reason" name="reason" class="form-control" placeholder="Консультация, осмотр и т.п.">
            </div>
            <button type="submit" class="btn btn-primary">Сохранить запись</button>
        </form>
    </div>
</main>
</body>
</html>
