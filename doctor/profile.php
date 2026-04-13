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

function ensureDoctorProfileTable($conn) {
    $conn->exec("CREATE TABLE IF NOT EXISTS doctor_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        doctor_id INT DEFAULT NULL,
        fio VARCHAR(255) DEFAULT '',
        position VARCHAR(255) DEFAULT '',
        specialization VARCHAR(255) DEFAULT '',
        experience VARCHAR(255) DEFAULT '',
        office VARCHAR(255) DEFAULT '',
        contacts VARCHAR(255) DEFAULT '',
        email VARCHAR(255) DEFAULT '',
        note TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensureDoctorProfileTable($conn);

$stmt = $conn->prepare('SELECT * FROM doctor_profiles WHERE user_id = ?');
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    $insert = $conn->prepare('INSERT INTO doctor_profiles (user_id, email) VALUES (?, ?)');
    $insert->execute([$userId, $_SESSION['user_email'] ?? '']);
    $profileId = $conn->lastInsertId();
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
}

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fio = trim($_POST['fio'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $office = trim($_POST['office'] ?? '');
    $contacts = trim($_POST['contacts'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $doctorId = trim($_POST['doctor_id'] ?? '');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }

    if ($doctorId !== '' && !ctype_digit($doctorId)) {
        $errors[] = 'Идентификатор врача должен быть числом.';
    }

    if (empty($errors)) {
        $update = $conn->prepare('UPDATE doctor_profiles SET fio = ?, position = ?, specialization = ?, experience = ?, office = ?, contacts = ?, email = ?, note = ?, doctor_id = ? WHERE user_id = ?');
        $update->execute([$fio, $position, $specialization, $experience, $office, $contacts, $email, $note, $doctorId !== '' ? intval($doctorId) : null, $userId]);
        $success = 'Профиль успешно сохранён.';
        $_SESSION['user_email'] = $email;
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$displayName = $profile['fio'] ?: $_SESSION['user_login'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль врача</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; color: #1f2937; font-family: Arial, sans-serif; }
        header { background: #fff; border-bottom: 1px solid #e5e7eb; padding: 18px 0; }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 18px; padding: 28px; box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
        .profile-header { display: flex; align-items: center; gap: 18px; margin-bottom: 24px; }
        .avatar { width: 84px; height: 84px; border-radius: 50%; background: #2563eb; display: grid; place-items: center; color: #fff; font-size: 32px; font-weight: 700; }
        .profile-title { margin: 0; font-size: 28px; }
        .profile-subtitle { color: #64748b; margin: 6px 0 0; }
        label { font-weight: 600; color: #334155; }
        .form-control { border-radius: 14px; border: 1px solid #d1d5db; }
        .btn-primary { border-radius: 14px; padding: 12px 22px; }
        .alert { border-radius: 14px; }
    </style>
</head>
<body>
<header>
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <h1 style="font-size:22px; margin:0;">Профиль врача</h1>
            <p style="margin:4px 0 0; color:#64748b;">Персональная страница врача с должностью, специализацией и опытом.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">← Назад</a>
    </div>
</header>
<main class="container">
    <div class="card">
        <div class="profile-header">
            <div class="avatar"><?= htmlspecialchars(mb_substr($displayName, 0, 1)) ?></div>
            <div>
                <h2 class="profile-title"><?= htmlspecialchars($displayName) ?></h2>
                <p class="profile-subtitle"><?= htmlspecialchars($profile['position'] ?: 'Врач') ?></p>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if (!empty($errors)): ?><div class="alert alert-danger"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>

        <form method="post" action="profile.php">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="fio" class="form-label">ФИО</label>
                    <input type="text" id="fio" name="fio" class="form-control" value="<?= htmlspecialchars($profile['fio']) ?>" placeholder="Иван Иванов">
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email'] ?? $_SESSION['user_email'] ?? '') ?>" placeholder="example@mail.ru">
                </div>
                <div class="col-md-6">
                    <label for="position" class="form-label">Должность</label>
                    <input type="text" id="position" name="position" class="form-control" value="<?= htmlspecialchars($profile['position']) ?>" placeholder="Врач-терапевт">
                </div>
                <div class="col-md-6">
                    <label for="specialization" class="form-label">Специализация</label>
                    <input type="text" id="specialization" name="specialization" class="form-control" value="<?= htmlspecialchars($profile['specialization']) ?>" placeholder="Гастроэнтеролог">
                </div>
                <div class="col-md-6">
                    <label for="experience" class="form-label">Стаж</label>
                    <input type="text" id="experience" name="experience" class="form-control" value="<?= htmlspecialchars($profile['experience']) ?>" placeholder="10 лет">
                </div>
                <div class="col-md-6">
                    <label for="office" class="form-label">Кабинет / отделение</label>
                    <input type="text" id="office" name="office" class="form-control" value="<?= htmlspecialchars($profile['office']) ?>" placeholder="Кабинет 302">
                </div>
                <div class="col-md-6">
                    <label for="contacts" class="form-label">Контакты</label>
                    <input type="text" id="contacts" name="contacts" class="form-control" value="<?= htmlspecialchars($profile['contacts']) ?>" placeholder="+7 707 123 45 67">
                </div>
                <div class="col-md-6">
                    <label for="doctor_id" class="form-label">ID в таблице Персонал</label>
                    <input type="text" id="doctor_id" name="doctor_id" class="form-control" value="<?= htmlspecialchars($profile['doctor_id']) ?>" placeholder="123">
                    <div class="form-text">Укажите ID вашей записи в таблице <code>Персонал</code>, чтобы отображались пациенты и записи.</div>
                </div>
                <div class="col-12">
                    <label for="note" class="form-label">О себе</label>
                    <textarea id="note" name="note" class="form-control" rows="5" placeholder="Кратко опишите ваш опыт и методы работы."><?= htmlspecialchars($profile['note']) ?></textarea>
                </div>
            </div>
            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary">Сохранить профиль</button>
            </div>
        </form>
    </div>
</main>
</body>
</html>
