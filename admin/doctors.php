<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../reg_login/login.php');
    exit;
}

include '../db.php';

$stmt = $conn->prepare("SELECT u.id AS user_id, u.login, u.email, dp.fio, dp.position, dp.specialization, dp.experience, dp.doctor_id, per.id AS personnel_id
    FROM users u
    LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
    LEFT JOIN `Персонал` per ON per.user_id = u.id
    WHERE u.role = 'doctor'
    ORDER BY dp.fio ASC, u.login ASC");
$stmt->execute();
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список врачей</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        .container { max-width: 1160px; margin: 32px auto; padding: 0 16px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header h1 { margin: 0; font-size: 28px; }
        .btn-back { text-decoration: none; color: #2563eb; }
        .table-card { background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); }
        .table th, .table td { vertical-align: middle; }
        .badge { border-radius: 999px; padding: 6px 12px; font-size: 12px; }
        .badge-info { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Список врачей</h1>
                <p style="margin: 8px 0 0; color: #64748b;">Все зарегистрированные врачи и их профильные данные.</p>
            </div>
            <a href="index.php" class="btn-back">← Назад</a>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>ФИО</th>
                            <th>Специализация</th>
                            <th>Должность</th>
                            <th>Стаж</th>
                            <th>ID Персонала</th>
                            <th>Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($doctors) === 0): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">Врачей пока нет.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?= intval($doctor['user_id']) ?></td>
                                    <td><?= htmlspecialchars($doctor['login']) ?></td>
                                    <td><?= htmlspecialchars($doctor['email']) ?></td>
                                    <td><?= htmlspecialchars($doctor['fio'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($doctor['specialization'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($doctor['position'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($doctor['experience'] ?: '-') ?></td>
                                    <td>
                                        <?= htmlspecialchars($doctor['doctor_id'] ?: '-') ?>
                                        <?php if ($doctor['personnel_id']): ?>
                                            <span class="badge badge-info">personnel #<?= intval($doctor['personnel_id']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="register.php" class="btn btn-sm btn-outline-primary">Редактировать</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
