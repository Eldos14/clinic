<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../reg_login/login.php');
    exit;
}

include '../db.php';

$stmt = $conn->prepare("SELECT p.id AS patient_id, p.фио, p.дата_рождения, p.контакты, p.адресс, u.login, u.email, u.id AS user_id
    FROM Пациенты p
    LEFT JOIN users u ON u.id = p.user_id
    ORDER BY p.фио ASC");
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Список пациентов</title>
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
        .badge-user { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h1>Список пациентов</h1>
                <p style="margin: 8px 0 0; color: #64748b;">Все зарегистрированные пациенты и их данные.</p>
            </div>
            <a href="index.php" class="btn-back">← Назад</a>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Дата рождения</th>
                            <th>Контакты</th>
                            <th>Адрес</th>
                            <th>Пользователь</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($patients) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">Пациентов пока нет.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?= intval($patient['patient_id']) ?></td>
                                    <td><?= htmlspecialchars($patient['фио'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($patient['дата_рождения'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($patient['контакты'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($patient['адресс'] ?: '-') ?></td>
                                    <td><span class="badge badge-user"><?= $patient['login'] ? htmlspecialchars($patient['login']) : 'Нет привязки' ?></span></td>
                                    <td><?= htmlspecialchars($patient['email'] ?: '-') ?></td>
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
