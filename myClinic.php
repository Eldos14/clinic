<?php
session_start();
if(!isset($_SESSION["user"])){
    header("Location: reg_login/login.php");
    exit;
}

include 'db.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: reg_login/login.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM Пациенты WHERE user_id = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient || empty($patient['clinic_id'])) {
    header('Location: myDetails.php');
    exit;
}

$stmtClinic = $conn->prepare("SELECT * FROM ФилиалыОтделения WHERE id = ?");
$stmtClinic->execute([$patient['clinic_id']]);
$clinic = $stmtClinic->fetch(PDO::FETCH_ASSOC);

// Получаем отделения для этого филиала через JOIN
$stmtDepts = $conn->prepare("SELECT * FROM отделения WHERE филлиал_id = ? ORDER BY отделения");
$stmtDepts->execute([$patient['clinic_id']]);
$departments = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

$initials = mb_substr($patient['фио'], 0, 1);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Моя поликлиника</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f4f7fb;
            color: #333;
        }

        .nav {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .nav a {
            text-decoration: none;
            color: #5f6c7b;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav a:hover {
            color: #1f7ed1;
        }

        .nav a.active {
            color: #1f7ed1;
            font-weight: 700;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: #1f7ed1;
            color: #fff;
            font-weight: 700;
        }

        .logout {
            color: #5f6c7b;
            text-decoration: none;
            font-weight: 500;
        }

        .clinic-hero {
            background: linear-gradient(135deg, #5eb3d6, #2a9fd8);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }

        .clinic-hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .clinic-hero h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .clinic-hero p {
            font-size: 16px;
            opacity: 0.9;
        }

        .clinics-welcome {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .welcome-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .welcome-box h2 {
            font-size: 20px;
            color: #2a7de1;
            margin-bottom: 8px;
        }

        .welcome-box p {
            color: #888;
            font-size: 15px;
        }

        .clinic-info {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .clinic-section {
            margin-bottom: 25px;
        }

        .clinic-section:last-child {
            margin-bottom: 0;
        }

        .clinic-section h3 {
            font-size: 16px;
            color: #2a7de1;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .clinic-section p {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }

        .clinic-detail {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .clinic-detail-icon {
            font-size: 20px;
            min-width: 24px;
        }

        .clinic-detail-text {
            color: #333;
            font-size: 15px;
        }

        .clinic-detail-label {
            color: #888;
            font-size: 13px;
        }

        .appointment-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .appointment-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .appointment-section h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .appointment-section p {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-appointment {
            background: #2a7de1;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn-appointment:hover {
            background: #1e5ba8;
            text-decoration: none;
            color: white;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .action-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .action-icon {
            font-size: 32px;
            min-width: 40px;
            text-align: center;
        }

        .action-text h4 {
            font-size: 15px;
            color: #333;
            margin-bottom: 4px;
        }

        .action-text p {
            font-size: 12px;
            color: #888;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2a7de1;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-btn:hover {
            color: #1e5ba8;
        }

        @media (max-width: 768px) {
            .clinic-hero h1 {
                font-size: 24px;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .clinic-info {
                padding: 20px;
            }
        }
    </style>
</head>
<body>


<div class="clinic-hero">
    <div class="clinic-hero-content">
        <h1>Моя поликлиника</h1>
        <p>Добрый день, <?= htmlspecialchars(explode(' ', $patient['фио'])[0]) ?>!</p>
    </div>
</div>

<div class="clinics-welcome">
    <a href="index.php" class="back-btn">← Вернуться на главную</a>

    <div class="welcome-box">
        <h2><?= htmlspecialchars($clinic['Отделения'] ?? '') ?></h2>
        <p><?= htmlspecialchars($clinic['Филиалы'] ?? '') ?></p>
    </div>

    <div class="appointment-section">
        <div class="appointment-icon">📋</div>
        <h3>Самозапись на приём</h3>
        <p>Запишитесь к врачу в удобное для вас время</p>
        <a href="add_appointment.php" class="btn-appointment">Записаться к врачу</a>
    </div>

    <div class="clinic-info">
        <div class="clinic-section">
            <h3>ℹ️ Информация о поликлинике</h3>
            <div class="clinic-detail">
                <span class="clinic-detail-icon">📍</span>
                <div>
                    <div class="clinic-detail-label">Адрес</div>
                    <div class="clinic-detail-text"><?= htmlspecialchars($clinic['адрес'] ?? 'Не указан') ?></div>
                </div>
            </div>
            <?php if(isset($clinic['Телефон'])): ?>
            <div class="clinic-detail">
                <span class="clinic-detail-icon">📞</span>
                <div>
                    <div class="clinic-detail-label">Телефон</div>
                    <div class="clinic-detail-text"><?= htmlspecialchars($clinic['Телефон']) ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="clinic-section">
            <h3>🏥 Филиал</h3>
            <p><?= htmlspecialchars($clinic['Филиалы'] ?? '') ?></p>
        </div>

        <div class="clinic-section">
            <h3>🔬 Отделение</h3>
            <?php if (count($departments) > 0): ?>
                <select style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; width: 100%; max-width: 300px;">
                    <option value="">-- Выберите отделение --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['id'] ?? '') ?>">
                            <?= htmlspecialchars($dept['отделения'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <p><?= htmlspecialchars($clinic['Отделения'] ?? 'Отделения не найдены') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="actions-grid">
        <div class="action-item">
            <div class="action-icon">🚗</div>
            <div class="action-text">
                <h4>Вывезти врача на дом</h4>
                <p>Заказать выездное обслуживание</p>
            </div>
        </div>
        <div class="action-item">
            <div class="action-icon">📤</div>
            <div class="action-text">
                <h4>Активные направления</h4>
                <p>Просмотр ваших направлений</p>
            </div>
        </div>
        <div class="action-item">
            <div class="action-icon">📞</div>
            <div class="action-text">
                <h4>Позвонить в поликлинику</h4>
                <p><?= htmlspecialchars($clinic['Телефон'] ?? '+7 (000) 00-00-00') ?></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
