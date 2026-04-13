<?php
session_start();
if(!isset($_SESSION["user"])){
  header("Location: ../reg_login/login.php");
}

include 'db.php';

$search = trim($_GET['search'] ?? '');
$doctorsQuery = "SELECT per.id, per.фио, per.должность, sp.Специальность, f.Филиалы, f.адрес AS филиал_адрес, m.кабинет
    FROM Персонал per
    LEFT JOIN СпециализацииПерсонала sp ON sp.персонал_айди = per.id
    LEFT JOIN МестоРаботыВрача m ON m.персонал_айди = per.id
    LEFT JOIN ФилиалыОтделения f ON f.id = m.ФилиалыОтделения";

if ($search !== '') {
    $doctorsQuery .= " WHERE per.фио LIKE :search OR per.должность LIKE :search";
    $stmt = $conn->prepare($doctorsQuery);
    $stmt->execute([':search' => "%{$search}%"]);
} else {
    $stmt = $conn->query($doctorsQuery);
}

$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Врачи</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .doctors-search {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 32px;
            max-width: 760px;
            flex-wrap: wrap;
        }

        .doctors-search input {
            flex: 1;
            min-width: 220px;
            padding: 14px 16px;
            border: 1px solid #d2d6dc;
            border-radius: 10px;
            font-size: 14px;
            color: #1f2937;
            background: #fff;
        }

        .doctors-search button {
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            background: #2a7de1;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.25s ease;
        }

        .doctors-search button:hover {
            background: #1b5db3;
        }

        .btn-back {
            background: #17a2b8;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: #138496;
            color: white;
            text-decoration: none;
        }

        /* Doctors Grid */
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .doctor-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .doctor-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            padding: 25px;
            height: 100%;
        }

        .doctor-card-link:hover .doctor-card,
        .doctor-card-link:focus .doctor-card {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }

        .doctor-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2a7de1, #17a2b8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .doctor-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .doctor-position {
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
        }

        .doctor-specialty {
            font-size: 13px;
            color: #2a7de1;
            font-weight: 500;
            margin-bottom: 12px;
            background: #f0f8ff;
            padding: 6px 10px;
            border-radius: 4px;
            display: inline-block;
        }

        .doctor-info {
            font-size: 12px;
            color: #888;
            margin-bottom: 8px;
            line-height: 1.6;
        }

        .doctor-info strong {
            color: #333;
        }

        .cabinet {
            background: #e7f3f7;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            margin-top: 12px;
            font-weight: 500;
            color: #17a2b8;
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
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .footer-icon a:hover {
            background: #2a7de1;
        }

        .footer-icon img {
            width: 16px;
            height: 16px;
        }

        .footer-main {
            text-align: center;
            color: #888;
            font-size: 13px;
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

        @media (max-width: 768px) {
            .doctors-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .nav {
                gap: 15px;
                margin-left: 20px;
            }

            .footer-block {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo">
            <img src="images/icons/damumed_logo.png" alt="Логотип">
        </div>
        <nav class="nav">
            <a href="index.php">Главная</a>
            <a href="myDetails.php">Мои данные</a>
            <a href="#">Избранное</a>
            <a href="#">Заказы</a>
        </nav>
        <div class="header-right">
            <span class="user">EA</span>
            <a href="logout.php" class="logout">Выйти</a>
        </div>
    </div>
</header>

<div class="container">
    <div class="page-header">
        <div>
            <h1>Список врачей</h1>
            <p class="page-subtitle">Поиск по имени врача или должности</p>
        </div>
        <a href="index.php" class="btn-back">Вернуться к пациентам</a>
    </div>

    <form class="doctors-search" method="get" action="doctors.php">
        <input type="text" name="search" placeholder="Поиск по имени или должности" value="<?= htmlspecialchars($search) ?>" />
        <button type="submit">Найти</button>
    </form>

    <div class="doctors-grid">
        <?php foreach($doctors as $d): ?>
        <a href="doctor.php?id=<?= $d['id'] ?>" class="doctor-card-link">
            <div class="doctor-card">
                <div class="doctor-avatar">
                    <?= strtoupper(substr($d['фио'], 0, 1)) ?>
                </div>
                <div class="doctor-name"><?= $d['фио'] ?></div>
                <div class="doctor-position"><?= $d['должность'] ?></div>
                <?php if($d['Специальность']): ?>
                    <div class="doctor-specialty"><?= $d['Специальность'] ?></div>
                <?php endif; ?>
                <div class="doctor-info">
                    <?php if($d['Филиалы']): ?>
                        <div><strong>Филиал:</strong> <?= htmlspecialchars($d['Филиалы']) ?></div>
                    <?php endif; ?>
                    <?php if(!empty($d['филиал_адрес'])): ?>
                        <div><strong>Адрес:</strong> <?= htmlspecialchars($d['филиал_адрес']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if(isset($d['кабинет']) && $d['кабинет']): ?>
                    <div class="cabinet">Кабинет: <?= $d['кабинет'] ?></div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
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

</body>
</html>
