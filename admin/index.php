<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../reg_login/login.php');
    exit;
}

include '../db.php';

$doctorCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();
$patientCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$adminCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$pendingAppointments = $conn->query("SELECT COUNT(*) FROM Запись WHERE статус = 'Запланировано'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef2ff; color: #0f172a; font-family: Arial, sans-serif; }
        .page-header { background: #334155; color: #f8fafc; padding: 30px 0; }
        .page-header h1 { margin: 0; font-size: 2.4rem; }
        .page-header p { margin: 12px 0 0; color: #cbd5e1; }
        .dashboard { max-width: 1180px; margin: 24px auto; padding: 0 16px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: #fff; padding: 24px; border-radius: 24px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); }
        .stat-card h3 { margin: 0; font-size: 2.2rem; }
        .stat-card p { margin: 8px 0 0; color: #64748b; }
        .card { border: none; border-radius: 20px; box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08); }
        .task-card { min-height: 190px; }
        .task-card h5 { color: #1d4ed8; }
        .task-card p { color: #475569; }
        .btn-primary { background: #2563eb; border: none; }
        .nav-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px; }
    </style>
</head>
<body>
    <header class="page-header text-center">
        <div class="container">
            <h1>Панель администратора</h1>
            <p>Привет, <?= htmlspecialchars($_SESSION['user_login']); ?>. Управляйте врачами, пациентами и расписанием.</p>
        </div>
    </header>
    <main class="dashboard">
        <section class="stats-grid">
            <div class="stat-card">
                <h3><?= intval($doctorCount) ?></h3>
                <p>Врачей в системе</p>
            </div>
            <div class="stat-card">
                <h3><?= intval($patientCount) ?></h3>
                <p>Пациентов в системе</p>
            </div>
            <div class="stat-card">
                <h3><?= intval($adminCount) ?></h3>
                <p>Администраторов</p>
            </div>
            <div class="stat-card">
                <h3><?= intval($pendingAppointments) ?></h3>
                <p>Ожидающих запись</p>
            </div>
        </section>

        <section class="nav-panel">
            <div class="card p-4 task-card">
                <h5>Добавить пользователя</h5>
                <p>Создавайте новых пациентов, врачей и администраторов.</p>
                <a href="register.php" class="btn btn-primary mt-3">Регистрация</a>
            </div>
            <div class="card p-4 task-card">
                <h5>Список врачей</h5>
                <p>Просматривайте всех врачей, их профили и связь с таблицей Персонал.</p>
                <a href="doctors.php" class="btn btn-primary mt-3">Все врачи</a>
            </div>
            <div class="card p-4 task-card">
                <h5>Список пациентов</h5>
                <p>Просматривайте всех пациентов и их пользовательские профили.</p>
                <a href="patients.php" class="btn btn-primary mt-3">Все пациенты</a>
            </div>
            <div class="card p-4 task-card">
                <h5>Управление расписанием</h5>
                <p>Добавляйте, архивируйте и удаляйте смены для врачей.</p>
                <a href="manage_schedule.php" class="btn btn-primary mt-3">Расписание</a>
            </div>
        </section>

        <div class="text-end mb-4">
            <a href="../logout.php" class="btn btn-outline-secondary">Выйти</a>
        </div>
    </main>
</body>
</html>
