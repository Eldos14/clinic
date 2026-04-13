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

// Получаем текущего пациента
$stmt = $conn->prepare("SELECT * FROM Пациенты WHERE user_id = ?");
$stmt->execute([$userId]);
$current_patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_patient) {
    header('Location: myDetails.php');
    exit;
}

// Получаем всех врачей
$doctors = $conn->query("SELECT id, фио FROM Персонал")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient = $current_patient['id']; // Используем только текущего пациента
    $doctor = $_POST['doctor'];
    $start = $_POST['start'];
    $end = $_POST['end'];
    $reason = $_POST['reason'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO Запись (пациент_айди, врач_айди, время_начало, время_конца, причина_записа, статус)
                            VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$patient, $doctor, $start, $end, $reason, $status]);

    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Запись к врачу</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        header {
            background: #fff;
            border-bottom: 1px solid #e6e9ef;
            padding: 16px 0;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .page-title {
            background: linear-gradient(135deg, #5eb3d6, #2a9fd8);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .page-title p {
            font-size: 16px;
            opacity: 0.9;
        }

        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px 40px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 15px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Arial', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2a7de1;
            box-shadow: 0 0 0 3px rgba(42, 125, 225, 0.1);
        }

        .form-group input:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            background: #2a7de1;
            color: white;
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-submit:hover {
            background: #1e5ba8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(42, 125, 225, 0.3);
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2a7de1;
            font-weight: 500;
            transition: color 0.2s;
        }

        .btn-back:hover {
            color: #1e5ba8;
        }

        .info-box {
            background: #f0f7ff;
            border-left: 4px solid #2a7de1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            color: #555;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .page-title h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php"><img src="images/icons/damumed_logo.png" alt="Логотип" style="height: 40px;"></a>
        </div>
        <nav class="nav">
            <a href="index.php">Главная</a>
            <a href="myDetails.php">Мои данные</a>
            <a href="myClinic.php">Моя поликлиника</a>
        </nav>
        <div class="header-right">
            <div class="user"><?= htmlspecialchars(mb_substr($current_patient['фио'], 0, 1)) ?></div>
            <a class="logout" href="logout.php">Выйти</a>
        </div>
    </div>
</header>

<div class="page-title">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <h1>📋 Запись к врачу</h1>
        <p>Выберите врача и удобное время для приёма</p>
    </div>
</div>

<div class="form-container">
    <a href="index.php" class="btn-back">← Вернуться на главную</a>

    <div class="form-card">
        <div class="info-box">
            <strong>ℹ️ Важно:</strong> Запись будет создана на вас. Проверьте правильность информации перед отправкой.
        </div>

        <form method="POST">
            <div class="form-group">
                <label>👤 Пациент</label>
                <input type="text" value="<?= htmlspecialchars($current_patient['фио']) ?>" disabled>
                <input type="hidden" name="patient" value="<?= htmlspecialchars($current_patient['id']) ?>">
            </div>

            <div class="form-group">
                <label>👨‍⚕️ Выберите врача *</label>
                <select name="doctor" required>
                    <option value="">-- Выберите врача --</option>
                    <?php foreach($doctors as $d): ?>
                        <option value="<?= htmlspecialchars($d['id']) ?>"><?= htmlspecialchars($d['фио']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>📅 Дата и время начала *</label>
                    <input type="datetime-local" name="start" required>
                </div>

                <div class="form-group">
                    <label>🕐 Дата и время конца *</label>
                    <input type="datetime-local" name="end" required>
                </div>
            </div>

            <div class="form-group">
                <label>📝 Причина визита *</label>
                <input type="text" name="reason" placeholder="Например: консультация, осмотр" required>
            </div>

            <div class="form-group">
                <label>✅ Статус записи *</label>
                <select name="status" required>
                    <option value="Запланировано" selected>Запланировано</option>
                    <option value="Завершено">Завершено</option>
                    <option value="Отменено">Отменено</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Создать запись</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
