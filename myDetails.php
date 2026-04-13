<?php
session_start();
if(!isset($_SESSION["user"])){
    header("Location: reg_login/login.php");
    exit;
}

include 'db.php';

$userId = $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['user_email'] ?? '';
if (!$userId) {
    header('Location: reg_login/login.php');
    exit;
}

// Ensure email column exists for profile storage.
$columnCheck = $conn->prepare("SHOW COLUMNS FROM `Пациенты` LIKE 'email'");
$columnCheck->execute();
if ($columnCheck->rowCount() === 0) {
    $conn->exec("ALTER TABLE `Пациенты` ADD COLUMN `email` VARCHAR(255) NULL AFTER `контакты`");
}

$stmt = $conn->prepare("SELECT * FROM Пациенты WHERE user_id = ?");
$stmt->execute([$userId]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    $stmt = $conn->prepare("INSERT INTO Пациенты (user_id, фио, иин, дата_рождения, пол, адресс, контакты, email) VALUES (?, '', '', null, '', '', '', ?)");
    $stmt->execute([$userId, $userEmail]);
    $patientId = $conn->lastInsertId();
    $stmt = $conn->prepare("SELECT * FROM Пациенты WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (empty($patient['email']) && $userEmail !== '') {
    $stmt = $conn->prepare("UPDATE Пациенты SET email = ? WHERE id = ?");
    $stmt->execute([$userEmail, $patient['id']]);
    $patient['email'] = $userEmail;
}

$patient_id = $patient['id'];
$photoPath = $patient['photo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fio = $_POST['fio'] ?? '';
    $iin = $_POST['iin'] ?? '';
    $birth = $_POST['birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = $_POST['address'] ?? '';
    $contacts = $_POST['contacts'] ?? '';
    $clinic_id = $_POST['clinic_id'] ?? null;
    if ($clinic_id === '') $clinic_id = null;

    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed, true)) {
            $uploadDir = __DIR__ . '/images/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid('avatar_') . '.' . $ext;
            $destination = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photoPath = 'images/avatars/' . $fileName;
            }
        }
    }

    $email = $_POST['email'] ?? $userEmail;
    $sql = "UPDATE Пациенты SET фио = ?, иин = ?, дата_рождения = ?, пол = ?, адресс = ?, контакты = ?, photo = ?, clinic_id = ?, email = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fio, $iin, $birth, $gender, $address, $contacts, $photoPath, $clinic_id, $email, $patient_id]);
    $_SESSION['user_email'] = $email;

    if (trim($fio) !== '' && trim($email) !== '') {
        header("Location: index.php");
    } else {
        header("Location: myDetails.php");
    }
    exit;
}

$initials = mb_substr($patient['фио'], 0, 1);
$profileEmail = $patient['email'] ?? $userEmail;
$showProfileAlert = false;
if (!empty($_SESSION['profile_alert'])) {
    $showProfileAlert = true;
    unset($_SESSION['profile_alert']);
}

$stmtClinics = $conn->query("SELECT * FROM ФилиалыОтделения ORDER BY Филиалы ASC");
$clinics = $stmtClinics->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои данные</title>
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

        .nav a:hover,
        .nav a.active {
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
            max-width: 1200px;
            margin: 32px auto 24px;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            font-size: 28px;
        }

        .page-title .subtitle {
            color: #6e7a8a;
            font-size: 14px;
        }

        .content {
            max-width: 1200px;
            margin: 0 auto 60px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 16px 35px rgba(69, 104, 147, 0.08);
            padding: 28px;
        }

        .profile-card {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 2px dashed rgba(31, 126, 209, 0.3);
            display: grid;
            place-items: center;
            font-size: 40px;
            color: #1f7ed1;
            margin: 0 auto;
        }

        .profile-photo {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(31, 126, 209, 0.25);
            margin: 0 auto;
        }

        .profile-name {
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #242f44;
        }

        .profile-status {
            text-align: center;
            color: #6e7a8a;
            font-size: 14px;
            line-height: 1.7;
        }

        .contact-block {
            background: #f8fbff;
            border-radius: 14px;
            padding: 18px 20px;
            display: grid;
            gap: 12px;
        }

        .contact-block small {
            color: #7f8a9a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .contact-block p {
            color: #1f3d7a;
            font-weight: 600;
            margin: 0;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            background: #1f7ed1;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(31, 126, 209, 0.18);
        }

        .form-card {
            display: grid;
            gap: 24px;
        }

        .form-section {
            background: #f8fbff;
            border-radius: 16px;
            padding: 24px;
        }

        .form-section h2 {
            font-size: 18px;
            margin-bottom: 18px;
            color: #2c3e58;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }
        .form-gender{
            margin-top: 10px;
            
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-field label {
            font-size: 13px;
            color: #5f6c7b;
        }

        .form-field input,
        .form-field select,
        .form-field textarea {
            width: 100%;
            min-height: 50px;
            padding: 14px 16px;
            border: 1px solid #d7dde7;
            border-radius: 12px;
            background: #fff;
            font-size: 14px;
            color: #333;
            line-height: 1.4;
        }

        .form-field textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }

        .form-actions .btn-secondary {
            border: 1px solid #d7dde7;
            background: #fff;
            color: #5f6c7b;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .form-actions .btn-secondary:hover {
            background: #f3f6fb;
        }

        @media (max-width: 960px) {
            .content {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .profile-header {
            background: linear-gradient(135deg, #1f7ed1, #4b9cff);
            border-radius: 24px;
            color: white;
            padding: 30px 28px;
            margin-bottom: 24px;
            display: grid;
            gap: 20px;
        }

        .profile-header h1 {
            margin: 0;
            font-size: 34px;
        }

        .profile-header p {
            margin: 0;
            color: rgba(255,255,255,0.9);
        }

        .profile-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .profile-meta div {
            background: rgba(255,255,255,0.14);
            padding: 18px;
            border-radius: 16px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.14);
        }

        .profile-meta .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
            margin-bottom: 6px;
        }

        .profile-meta .value {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }

        @media (max-width: 820px) {
            .profile-meta {
                grid-template-columns: 1fr;
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
            <a href="myDetails.php" class="active">Мои данные</a>
            <a href="#">Избранное</a>
            <a href="#">Заказы</a>
        </nav>
        <div class="header-right">
            <div class="user"><?= htmlspecialchars($initials) ?></div>
            <a class="logout" href="logout.php">Выйти</a>
        </div>
    </div>
</header>

<div class="page-title">
    <div>
        <h1>Профиль</h1>
        <div class="subtitle">Редактируйте свои персональные данные и контакты</div>
    </div>
</div>

<?php if ($showProfileAlert): ?>
    <script>
        window.addEventListener('load', function() {
            alert('Пожалуйста, заполните ваш профиль, чтобы получить доступ ко всем функциям Damumed!');
        });
    </script>
<?php endif; ?>

<div class="content">
    <div class="card profile-card">
        <?php if (!empty($photoPath)): ?>
            <img src="<?= htmlspecialchars($photoPath) ?>" alt="Фото профиля" class="profile-photo">
        <?php else: ?>
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <?php endif; ?>
        <div class="profile-name"><?= htmlspecialchars($patient['фио']) ?></div>
        <div class="profile-status">Профиль пациента Damumed. Здесь вы можете обновить свои данные.</div>

        <div class="contact-block">
            <p><?= htmlspecialchars($profileEmail) ?></p>
        </div>
        
        <div class="contact-block">
            <p><?= htmlspecialchars($patient['контакты']) ?></p>
        </div>
       
    </div>

    <div class="form-card">
        <div class="form-section">
            <h2>Профиль в Damumed</h2>
            <form method="post" action="myDetails.php?id=<?= $patient_id ?>" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-field">
                        <label for="fio">ФИО</label>
                        <input type="text" id="fio" name="fio" value="<?= htmlspecialchars($patient['фио']) ?>" required>
                    </div>
                    <div class="form-field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($profileEmail) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="photo">Фото профиля</label>
                        <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp">
                    </div>
                    <div class="form-field">
                        <label for="iin">ИИН</label>
                        <input type="text" id="iin" name="iin" value="<?= htmlspecialchars($patient['иин'] ?? $patient['iin'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="birth">Дата рождения</label>
                        <input type="date" id="birth" name="birth" value="<?= htmlspecialchars($patient['дата_рождения']) ?>" required>
                    </div>
                    <div class="form-field">
                        
                        <label class="form-gender" for="gender">Пол</label>

                        <select id="gender" name="gender">
                            <option value="" <?= ($patient['пол'] ?? '') === '' ? 'selected' : '' ?>>Не указан</option>
                            <option value="Мужской" <?= ($patient['пол'] ?? '') === 'Мужской' ? 'selected' : '' ?>>Мужской</option>
                            <option value="Женский" <?= ($patient['пол'] ?? '') === 'Женский' ? 'selected' : '' ?>>Женский</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="clinic">Моя поликлиника</label>
                        <select id="clinic" name="clinic_id">
                            <option value="">Не выбрана</option>
                            <?php foreach($clinics as $clinic): ?>
                            <option value="<?= $clinic['id'] ?>" <?= ($patient['clinic_id'] ?? '') == $clinic['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($clinic['Филиалы'] ?? $clinic['Отделения'] ?? '') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field">
                        <label for="address">Адрес</label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($patient['адресс'] ?? $patient['address'] ?? '') ?>">
                    </div>
                    <div class="form-field">
                        <label for="contacts">Контакты</label>
                        <input type="text" id="contacts" name="contacts" value="<?= htmlspecialchars($patient['контакты'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn-secondary">Назад</a>
                    <button type="submit" class="btn-primary">Сохранить изменения</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>