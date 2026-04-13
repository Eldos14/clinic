<?php
session_start();
if(!isset($_SESSION["user"])){
  header("Location: ../reg_login/login.php");
  exit;
}

include 'db.php';
$userId = $_SESSION['user_id'] ?? null;
$currentPatient = null;
$patients = [];
$patients_clinic = null;
$profileIncomplete = false;

function isProfileIncomplete(array $patient): bool {
    $fields = [
        $patient['фио'] ?? '',
        $patient['дата_рождения'] ?? '',
        $patient['контакты'] ?? '',
        $patient['clinic_id'] ?? '',
    ];
    foreach ($fields as $value) {
        if (trim((string)$value) === '') {
            return true;
        }
    }
    return false;
}

if ($userId) {
    $stmt = $conn->prepare("SELECT * FROM Пациенты WHERE user_id = ?");
    $stmt->execute([$userId]);
    $currentPatient = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($currentPatient) {
        $patients = [$currentPatient];
        if (isProfileIncomplete($currentPatient)) {
            $profileIncomplete = true;
        }
        if (!empty($currentPatient['clinic_id'])) {
            $stmtClinic = $conn->prepare("SELECT * FROM ФилиалыОтделения WHERE id = ?");
            $stmtClinic->execute([$currentPatient['clinic_id']]);
            $patients_clinic = $stmtClinic->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $profileIncomplete = true;
    }
}

if ($profileIncomplete) {
    $_SESSION['profile_alert'] = true;
    header("Location: myDetails.php");
    exit;
}

$headerPhoto = $currentPatient['photo'] ?? '';
$headerInitial = strtoupper(substr($currentPatient['фио'] ?? 'EA', 0, 1));

function ensureNotificationsTable($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureNotificationsTable($conn);
$notificationCount = 0;
if ($currentPatient) {
    $stmtNotification = $conn->prepare('SELECT COUNT(*) AS unread FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmtNotification->execute([$userId]);
    $notificationCount = intval($stmtNotification->fetchColumn());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Пациенты - Клиника</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/login.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f0f4f8;
            color: #333;
        }

        /* Header */
        header {
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo img {
            height: 40px;
        }

        .nav {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex: 1;
            margin-left: 40px;
        }

        .nav a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav a:hover {
            color: #2a7de1;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user {
            background: #2a7de1;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .logout {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }

        .logout:hover {
            color: #2a7de1;
        }

        /* Main content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .content-wrapper {
            padding: 40px 0;
            min-height: calc(100vh - 300px);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #333;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            background: linear-gradient(135deg, #2a7de1, #1e5ba8);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            transition: transform 0.3s, box-shadow 0.3s;
            font-weight: 500;
            border: none;
            cursor: pointer;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(42, 125, 225, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-doctors {
            background: linear-gradient(135deg, #17a2b8, #0f7a8f);
        }

        .btn-doctors:hover {
            box-shadow: 0 4px 8px rgba(23, 162, 184, 0.3);
        }

        /* Patients Grid */
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .patient-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .patient-card-header {
            background: linear-gradient(135deg, #2a7de1, #17a2b8);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .patient-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
        }

        .patient-name {
            font-size: 18px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .patient-card-body {
            padding: 20px;
        }

        .patient-info {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .patient-info strong {
            color: #333;
            display: block;
            margin-bottom: 3px;
        }

        .patient-status {
            background: #f0f8ff;
            color: #2a7de1;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 15px;
            display: inline-block;
        }

        .patient-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .patient-action-btn {
            flex: 1;
            padding: 8px 12px;
            background: #f0f4f8;
            color: #2a7de1;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
            transition: background 0.3s;
            border: 1px solid #e0e0e0;
        }

        .patient-action-btn:hover {
            background: #2a7de1;
            color: white;
            text-decoration: none;
        }

        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        /* Footer */
        footer {
            background: #f3eeee;
            margin-top: 60px;
            padding: 30px 0;
            border-top: 1px solid #e0e0e0;
        }

        .footer-block {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
        }

        .footer-icon {
            display: flex;
            gap: 15px;
        }

        .footer-icon a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            background: #d4d4d4;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .footer-icon a:hover {
            background: #2a7de1;
        }

        .footer-icon img {
            width: 18px;
            height: 18px;
        }

        .footer-main {
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        .footer-main p {
            margin: 0 0 10px 0;
        }

        .footer-main a {
            color: #888;
            text-decoration: none;
            margin: 0 15px;
            transition: color 0.3s;
        }

        .footer-main a:hover {
            color: #2a7de1;
        }

        .footer-apps {
            display: flex;
            gap: 10px;
        }

        .footer-apps a {
            color: #888;
            text-decoration: none;
            font-size: 12px;
            transition: color 0.3s;
        }

        .footer-apps a:hover {
            color: #2a7de1;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .empty-state h2 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        /* Welcome Section */
        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px 0;
        }

        .welcome-section h1 {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .welcome-section p {
            font-size: 16px;
            color: #888;
        }

        .ai-assistant-card {
            background: #ffffff;
            border: 1px solid #d9e2ef;
            border-radius: 16px;
            padding: 20px 24px;
            max-width: 900px;
            margin: 0 auto 35px;
            box-shadow: 0 8px 24px rgba(53, 113, 196, 0.08);
        }

        .ai-assistant-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .ai-assistant-header span {
            font-size: 18px;
            font-weight: 600;
            color: #1f3d7a;
        }

        .ai-assistant-header small {
            color: #6e7a99;
            font-size: 14px;
        }

        .ai-assistant-form {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            margin-bottom: 16px;
        }

        .ai-assistant-form input {
            border: 1px solid #d9e2ef;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 15px;
            width: 100%;
            color: #2a3a5d;
        }

        .ai-assistant-form button {
            background: linear-gradient(135deg, #2a7de1, #1e5ba8);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px 24px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ai-assistant-form button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(42, 125, 225, 0.25);
        }

        .ai-assistant-response {
            background: #f6f9ff;
            border: 1px solid #dbe8ff;
            border-radius: 12px;
            padding: 18px 20px;
            color: #2c3f67;
            line-height: 1.7;
            min-height: 70px;
            white-space: pre-wrap;
        }

        .ai-assistant-response.loading {
            opacity: 0.75;
        }

        .ai-assistant-debug {
            display: none;
            margin-top: 14px;
            background: #eff4ff;
            border: 1px solid #bdd4ff;
            border-radius: 12px;
            padding: 16px;
            color: #1a2e55;
            font-size: 13px;
            white-space: pre-wrap;
            max-height: 190px;
            overflow: auto;
        }

        .ai-assistant-debug.visible {
            display: block;
        }

        /* Banners Carousel */
        .banners-carousel {
            position: relative;
            margin-bottom: 40px;
            height: 160px;
            overflow: hidden;
            border-radius: 12px;
        }

        .banners-track {
            display: flex;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
        }

        .banner {
            min-width: 100%;
            flex: 0 0 100%;
            border-radius: 12px;
            padding: 30px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .banner:hover {
            filter: brightness(1.05);
        }

        .banner-blue {
            background: linear-gradient(135deg, #5eb3d6, #2a9fd8);
        }

        .banner-teal {
            background: linear-gradient(135deg, #50c9d4, #2eb8b8);
        }

        .banner-green {
            background: linear-gradient(135deg, #52c991, #23a560);
        }

        .banner-content {
            z-index: 2;
            flex: 1;
        }

        .banner-content h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .banner-content p {
            font-size: 14px;
            opacity: 0.9;
        }

        .banner-icon {
            font-size: 60px;
            opacity: 0.3;
            margin-left: 20px;
        }

        .banner-nav {
            font-size: 28px;
            opacity: 0.5;
            margin-left: 20px;
            cursor: pointer;
        }

        .banner-arrow {
            display: inline-block;
            transition: all 0.3s;
        }

        /* Carousel Controls */
        .carousel-control {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.3);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 5;
        }

        .carousel-control:hover {
            background: rgba(0, 0, 0, 0.6);
            transform: translateY(-50%) scale(1.1);
        }

        .carousel-prev {
            left: 15px;
        }

        .carousel-next {
            right: 15px;
        }

        .carousel-dots {
            position: absolute;
            bottom: 15px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 5;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .dot.active {
            background: white;
            width: 30px;
            border-radius: 5px;
        }

        /* Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 50px;
        }

        .menu-item {
            background: white;
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }

        .menu-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .menu-item h3 {
            font-size: 15px;
            color: #333;
            font-weight: 600;
            margin: 0;
        }

        /* Patient Info Section */
        .patient-info-section {
            margin-bottom: 40px;
        }

        .info-card {
            border-radius: 12px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .info-card h3 {
            font-size: 20px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .info-content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-row {
            display: flex;
            flex-direction: column;
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 8px;
        }

        .info-row .label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-row .value {
            font-size: 16px;
            font-weight: 600;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .action-btn {
            background: white;
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            text-decoration: none;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            color: #333;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            color: #333;
            text-decoration: none;
        }

        .action-icon {
            font-size: 40px;
            display: block;
        }

        .action-text {
            font-size: 14px;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .banners-carousel {
                grid-template-columns: repeat(2, 1fr);
            }

            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .patients-grid {
                grid-template-columns: 1fr;
            }

            .nav {
                gap: 15px;
                margin-left: 20px;
                font-size: 13px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .btn-group {
                width: 100%;
            }

            .btn-action {
                flex: 1;
            }

            .footer-block {
                flex-direction: column;
                text-align: center;
            }

            .banners-carousel {
                grid-template-columns: 1fr;
            }

            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .info-content {
                grid-template-columns: 1fr;
            }

            .welcome-section h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<?php
$activePage = 'home';
include 'header.php';
?>

<div class="container">
    <div class="content-wrapper">
        <!-- Приветствие -->
        <div class="welcome-section">
            <h1>Добро пожаловать!</h1>
            <p>Ваш личный кабинет медицинской клиники</p>
        </div>

        <div class="ai-assistant-card">
            <div class="ai-assistant-header">
                <span>AI-помощник по симптомам</span>
                <small>Спросите о болезнях, симптомах или что делать дальше.</small>
            </div>
            <form id="aiAssistantForm" class="ai-assistant-form">
                <input id="aiQuery" name="query" type="text" placeholder="Например: болит горло и высокая температура" required>
                <button type="submit">Спросить</button>
            </form>
            <div id="aiAssistantResult" class="ai-assistant-response">Вставьте вопрос, и помощник подскажет возможные направления. Это не замена врачу.</div>
            <div id="aiAssistantDebug" class="ai-assistant-debug"></div>
        </div>

        <?php if ($profileIncomplete): ?>
            <div class="alert alert-warning rounded-4 mb-4">
                <h5 class="alert-heading">Пожалуйста, заполните профиль</h5>
                <p>Ваш профиль пациента пока неполный. Чтобы продолжить работу, заполните данные в личном кабинете.</p>
                <hr>
                <a href="myDetails.php" class="btn btn-sm btn-primary">Перейти к заполнению профиля</a>
            </div>
        <?php endif; ?>

        <!-- Банеры с функциями -->
        <div class="banners-carousel" id="bannersCarousel">
            <div class="banners-track">
                <div class="banner banner-blue">
                    <div class="banner-content">
                        <h2>Платежи ОСМС</h2>
                        <p>Управляйте страховыми платежами</p>
                    </div>
                    <div class="banner-icon">💳</div>
                </div>

                <div class="banner banner-teal">
                    <div class="banner-content">
                        <h2>Графики врачей</h2>
                        <p>Выберите удобное время приёма</p>
                    </div>
                    <div class="banner-icon">📅</div>
                </div>

                <div class="banner banner-green">
                    <div class="banner-content">
                        <h2>Результаты анализов</h2>
                        <p>Ваши последние анализы</p>
                    </div>
                    <div class="banner-icon">🧪</div>
                </div>
            </div>
            
            <button class="carousel-control carousel-prev" onclick="moveCarousel(-1)">❮</button>
            <button class="carousel-control carousel-next" onclick="moveCarousel(1)">❯</button>
            
            <div class="carousel-dots">
                <button class="dot active" onclick="goToSlide(0)"></button>
                <button class="dot" onclick="goToSlide(1)"></button>
                <button class="dot" onclick="goToSlide(2)"></button>
            </div>
        </div>

        <!-- Меню функций -->
        <div class="menu-grid">
            <?php if ($patients_clinic): ?>
            <a href="myClinic.php" class="menu-item">
                <div class="menu-icon">🏥</div>
                <h3><?= htmlspecialchars($patients_clinic['Филиалы'] ?? '') ?></h3>
                <p style="font-size: 12px; color: #888; margin-top: 8px;">
                    <?= htmlspecialchars($patients_clinic['Отделения'] ?? '') ?><br>
                    <?php if(isset($patients_clinic['Адрес'])): ?>
                        <?= htmlspecialchars($patients_clinic['Адрес']) ?>
                    <?php endif; ?>
                </p>
            </a>
            <?php else: ?>
            <a href="myDetails.php" class="menu-item">
                <div class="menu-icon">🏥</div>
                <h3>Выберите поликлинику</h3>
                <p style="font-size: 12px; color: #2a7de1; margin-top: 8px;">Нажмите, чтобы настроить</p>
            </a>
            <?php endif; ?>
            <div class="menu-item">
                <div class="menu-icon">🛡️</div>
                <h3>Пакет ОСМС</h3>
            </div>
            <a href="myDetails.php" class="menu-item">
                <div class="menu-icon">👤</div>
                <h3>Мои данные</h3>
            </a>
            <div class="menu-item">
                <div class="menu-icon">📋</div>
                <h3>Все рубрики</h3>
            </div>
        </div>

        <!-- Информация о пациенте -->
        <?php if(count($patients) > 0): ?>
        <div class="patient-info-section">
            <?php $patient = $patients[0]; ?>
            
            <div class="info-card" style="background: linear-gradient(135deg, #5eb3d6, #2a9fd8);">
                <h3>Ваша информация</h3>
                <div class="info-content">
                    <div class="info-row">
                        <span class="label">ФИО:</span>
                        <span class="value"><?= htmlspecialchars($patient['фио']) ?></span>
                    </div>
                    <?php if(isset($patient['дата_рождения'])): ?>
                    <div class="info-row">
                        <span class="label">Дата рождения:</span>
                        <span class="value"><?= htmlspecialchars($patient['дата_рождения']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($patient['телефон'])): ?>
                    <div class="info-row">
                        <span class="label">Телефон:</span>
                        <span class="value"><?= htmlspecialchars($patient['телефон']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($patient['адрес'])): ?>
                    <div class="info-row">
                        <span class="label">Адрес:</span>
                        <span class="value"><?= htmlspecialchars($patient['адрес']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Быстрые действия -->
            <div class="quick-actions">
                    <a href="patient_history.php" class="action-btn action-history">
                    <div class="action-icon">📋</div>
                    <div class="action-text">История визитов</div>
                </a>
                <a href="add_appointment.php?patient_id=<?= $patient['id'] ?>" class="action-btn action-appointment">
                    <div class="action-icon">📅</div>
                    <div class="action-text">Запись к врачу</div>
                </a>
                <a href="doctors.php" class="action-btn action-doctors">
                    <div class="action-icon">👨‍⚕️</div>
                    <div class="action-text">Список врачей</div>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<footer>
    <div class="footer-block">
        <div class="footer-icon">
            <a href="https://www.facebook.com/damumed/?locale=ru_RU"><img src="images/icons/facebook.png" alt="Facebook"></a>
            <a href="https://www.instagram.com/damumed/"><img src="images/icons/instagram.png" alt="Instagram"></a>
            <a href="https://t.me/Damumed_Bot"><img src="images/icons/telegram.png" alt="Telegram"></a>
            <a href="https://www.youtube.com/c/damumed"><img src="images/icons/youtube.png" alt="YouTube"></a>
        </div>
        <div class="footer-main">
            <p>© 2024 Медицинская система</p>
            <div>
                <a href="#">О Нас</a>
                <a href="#">Контакты</a>
                <a href="#">Политика конфиденциальности</a>
            </div>
        </div>
    </div>
</footer>

<script>
    let currentSlide = 0;
    const totalSlides = 3;
    let carouselInterval;

    function updateCarousel() {
        const track = document.querySelector('.banners-track');
        const offset = -currentSlide * 100;
        track.style.transform = `translateX(${offset}%)`;

        // Обновляем активную точку
        document.querySelectorAll('.dot').forEach((dot, index) => {
            if (index === currentSlide) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    function moveCarousel(direction) {
        currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
        updateCarousel();
        resetAutoPlay();
    }

    function goToSlide(index) {
        currentSlide = index;
        updateCarousel();
        resetAutoPlay();
    }

    function autoPlayCarousel() {
        moveCarousel(1);
    }

    function resetAutoPlay() {
        clearInterval(carouselInterval);
        carouselInterval = setInterval(autoPlayCarousel, 5000);
    }

    // Запускаем автоплей при загрузке страницы
    window.addEventListener('load', () => {
        carouselInterval = setInterval(autoPlayCarousel, 5000);
    });

    // Останавливаем автоплей при наведении
    document.getElementById('bannersCarousel').addEventListener('mouseenter', () => {
        clearInterval(carouselInterval);
    });

    // Возобновляем автоплей при вывате мышки
    document.getElementById('bannersCarousel').addEventListener('mouseleave', () => {
        carouselInterval = setInterval(autoPlayCarousel, 5000);
    });

    const aiForm = document.getElementById('aiAssistantForm');
    const aiQuery = document.getElementById('aiQuery');
    const aiResult = document.getElementById('aiAssistantResult');
    const aiDebug = document.getElementById('aiAssistantDebug');

    aiForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const question = aiQuery.value.trim();
        if (!question) {
            aiResult.textContent = 'Введите ваш запрос, пожалуйста.';
            aiDebug.classList.remove('visible');
            aiDebug.textContent = '';
            return;
        }

        aiResult.textContent = 'Ищем ответ... Пожалуйста, подождите.';
        aiResult.classList.add('loading');
        aiForm.querySelector('button').disabled = true;
        aiDebug.classList.remove('visible');
        aiDebug.textContent = '';

        try {
            const response = await fetch('ai_assistant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ query: question })
            });

            if (!response.ok) {
                throw new Error('Ошибка сервера');
            }

            const data = await response.json();
            aiResult.textContent = data.answer || 'К сожалению, я не смог найти ответ. Попробуйте задать вопрос иначе.';
            if (data.debug) {
                aiDebug.textContent = JSON.stringify(data.debug, null, 2);
                aiDebug.classList.add('visible');
            }
        } catch (error) {
            aiResult.textContent = 'Ошибка при получении ответа. Попробуйте позже.';
        } finally {
            aiResult.classList.remove('loading');
            aiForm.querySelector('button').disabled = false;
        }
    });
</script>

</body>
</html>
