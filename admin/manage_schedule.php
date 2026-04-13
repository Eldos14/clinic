<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../reg_login/login.php');
    exit;
}

include '../db.php';

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

ensureDoctorScheduleTable($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $doctorId = intval($_POST['doctor_user_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $start = $_POST['start_time'] ?? '';
        $end = $_POST['end_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if ($doctorId && $date && $start && $end && strtotime($end) > strtotime($start)) {
            $stmt = $conn->prepare('INSERT INTO doctor_schedule (user_id, date, start_time, end_time, location, note) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$doctorId, $date, $start, $end, $location, $note]);
            $successMessage = 'Смена сохранена.';
        } else {
            $errorMessage = 'Проверьте заполненные поля и время.';
        }
    }

    if ($action === 'delete' && !empty($_POST['schedule_id'])) {
        $scheduleId = intval($_POST['schedule_id']);
        $stmt = $conn->prepare('DELETE FROM doctor_schedule WHERE id = ?');
        $stmt->execute([$scheduleId]);
        $successMessage = 'Смена удалена.';
    }

    if ($action === 'toggle_status' && !empty($_POST['schedule_id'])) {
        $scheduleId = intval($_POST['schedule_id']);
        $stmt = $conn->prepare('SELECT status FROM doctor_schedule WHERE id = ? LIMIT 1');
        $stmt->execute([$scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newStatus = $row['status'] === 'active' ? 'archived' : 'active';
            $update = $conn->prepare('UPDATE doctor_schedule SET status = ? WHERE id = ?');
            $update->execute([$newStatus, $scheduleId]);
            $successMessage = 'Статус смены изменён.';
        }
    }
}

$doctorsStmt = $conn->prepare("SELECT u.id AS user_id, u.login, dp.fio
    FROM users u
    LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
    WHERE u.role = 'doctor'
    ORDER BY dp.fio ASC, u.login ASC");
$doctorsStmt->execute();
$doctors = $doctorsStmt->fetchAll(PDO::FETCH_ASSOC);

$scheduleStmt = $conn->prepare("SELECT ds.*, u.login, dp.fio
    FROM doctor_schedule ds
    LEFT JOIN users u ON u.id = ds.user_id
    LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
    ORDER BY ds.date DESC, ds.start_time DESC");
$scheduleStmt->execute();
$scheduleList = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление расписанием</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        .page-container { max-width: 1140px; margin: 32px auto; padding: 0 16px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 28px; }
        .form-card, .table-card { background: #fff; border-radius: 18px; padding: 24px; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08); margin-bottom: 24px; }
        .form-control, .form-select { border-radius: 12px; }
        .btn-back { text-decoration: none; color: #2563eb; }
        .status-badge { border-radius: 999px; padding: 8px 12px; font-size: 12px; }
        .status-active { background: #d1fae5; color: #166534; }
        .status-archived { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1>Управление расписанием</h1>
                <p style="margin: 8px 0 0; color: #64748b;">Админ может добавлять смены врачам и менять статус.</p>
            </div>
            <a href="index.php" class="btn-back">← Назад</a>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h2 style="font-size:20px; margin-bottom:20px;">Добавить смену</h2>
            <form method="post" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="create">
                <div class="col-md-4">
                    <label class="form-label">Врач</label>
                    <select name="doctor_user_id" class="form-select" required>
                        <option value="">Выберите врача</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= intval($doctor['user_id']) ?>"><?= htmlspecialchars($doctor['fio'] ?: $doctor['login']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Дата</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Начало</label>
                    <input type="time" name="start_time" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Конец</label>
                    <input type="time" name="end_time" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Кабинет</label>
                    <input type="text" name="location" class="form-control" placeholder="Каб. 302">
                </div>
                <div class="col-12">
                    <label class="form-label">Примечание</label>
                    <input type="text" name="note" class="form-control" placeholder="Например, прием с 9:00 до 12:00">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Сохранить смену</button>
                </div>
            </form>
        </div>

        <div class="table-card">
            <h2 style="font-size:20px; margin-bottom:20px;">Список смен</h2>
            <?php if (count($scheduleList) === 0): ?>
                <p>Смены пока не добавлены.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Врач</th>
                                <th>Дата</th>
                                <th>Время</th>
                                <th>Кабинет</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduleList as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['fio'] ?: $item['login'] ?: 'Неизвестно') ?></td>
                                    <td><?= htmlspecialchars(date('d.m.Y', strtotime($item['date']))) ?></td>
                                    <td><?= htmlspecialchars(substr($item['start_time'], 0, 5)) ?> - <?= htmlspecialchars(substr($item['end_time'], 0, 5)) ?></td>
                                    <td><?= htmlspecialchars($item['location'] ?: '—') ?></td>
                                    <td><span class="status-badge <?= $item['status'] === 'active' ? 'status-active' : 'status-archived' ?>"><?= htmlspecialchars($item['status'] === 'active' ? 'Активно' : 'Архив') ?></span></td>
                                    <td>
                                        <form method="post" style="display:inline-flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                            <input type="hidden" name="schedule_id" value="<?= intval($item['id']) ?>">
                                            <button type="submit" name="action" value="toggle_status" class="btn btn-sm btn-outline-secondary"><?= $item['status'] === 'active' ? 'Архив' : 'Активировать' ?></button>
                                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить смену?');">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
