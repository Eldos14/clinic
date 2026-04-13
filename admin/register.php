<?php
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: ../reg_login/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Регистрация врача / администратора</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="../css/login.css">
  <style>
    body {
      min-height: 100vh;
      background: linear-gradient(180deg, #eef4ff 0%, #f8fbff 100%);
    }
    .auth-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 0;
    }
    .auth-card {
      overflow: hidden;
      border: none;
      border-radius: 24px;
      box-shadow: 0 30px 75px rgba(35, 67, 126, 0.12);
    }
    .auth-form {
      padding: 36px 32px;
    }
    .form-control {
      border-radius: 14px;
      box-shadow: none;
      border: 1px solid #d7dee7;
      min-height: 52px;
    }
    .form-control:focus {
      border-color: #1e73d7;
      box-shadow: 0 0 0 0.15rem rgba(30,115,215,0.15);
    }
    .btn-primary {
      border-radius: 14px;
      padding: 14px 24px;
      font-weight: 600;
    }
    .form-label {
      font-size: 13px;
      color: #6c7a8f;
    }
  </style>
</head>
<body>
  <section class="auth-wrapper">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-xl-10">
          <div class="card auth-card">
            <div class="row g-0">
              <div class="col-lg-6">
                <div class="card-body p-md-5 mx-md-4 auth-form">
                  <div class="text-center">
                    <img src="../images/icons/damumed_logo.png" style="width: 40px;" alt="logo">
                    <h4 class="mt-1 mb-5 pb-1">Damumed</h4>
                    <p class="mb-4">Зарегистрируйте нового врача или администратора.</p>
                  </div>
                  <?php
                  if (isset($_POST['submit'])) {
                      $login = trim($_POST['login'] ?? '');
                      $email = trim($_POST['email'] ?? '');
                      $password = $_POST['password'] ?? '';
                      $repeat_password = $_POST['repeat_password'] ?? '';
                      $role = $_POST['role'] ?? 'patient';
                      $allowedRoles = ['patient', 'doctor', 'admin'];
                      if (!in_array($role, $allowedRoles, true)) {
                          $role = 'patient';
                      }

                      $errors = [];
                      if (empty($login) || empty($email) || empty($password) || empty($repeat_password)) {
                          $errors[] = 'Заполните все поля';
                      }
                      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                          $errors[] = 'Email не верный';
                      }
                      if (strlen($password) < 8) {
                          $errors[] = 'Пароль должен быть не меньше 8 символов';
                      }
                      if ($password !== $repeat_password) {
                          $errors[] = 'Пароли должны совпадать';
                      }

                      require_once '../reg_login/database.php';
                      if (!function_exists('ensureRoleColumn')) {
                          function ensureRoleColumn($conn) {
                              $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
                              if ($result && mysqli_num_rows($result) === 0) {
                                  mysqli_query($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'patient'");
                              }
                          }
                      }
                      ensureRoleColumn($conn);

                      $sql = 'SELECT * FROM users WHERE email = ?';
                      $stmtCheck = mysqli_prepare($conn, $sql);
                      mysqli_stmt_bind_param($stmtCheck, 's', $email);
                      mysqli_stmt_execute($stmtCheck);
                      $result = mysqli_stmt_get_result($stmtCheck);
                      if (mysqli_num_rows($result) > 0) {
                          $errors[] = 'Пользователь с таким email уже существует';
                      }

                      if (count($errors) > 0) {
                          foreach ($errors as $error) {
                              echo "<div class='alert alert-danger'>$error</div>";
                          }
                      } else {
                          $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                          $sql = 'INSERT INTO users(login,email,password,role) VALUES (?,?,?,?)';
                          $stmt = mysqli_stmt_init($conn);
                          if (mysqli_stmt_prepare($stmt, $sql)) {
                              mysqli_stmt_bind_param($stmt, 'ssss', $login, $email, $passwordHash, $role);
                              mysqli_stmt_execute($stmt);
                              $userId = mysqli_insert_id($conn);

                              if ($role === 'patient') {
                                  $sqlPatient = "INSERT INTO Пациенты (user_id, фио, иин, дата_рождения, пол, адресс, контакты) VALUES (?, '', '', null, '', '', '')";
                                  $stmtPatient = mysqli_stmt_init($conn);
                                  mysqli_stmt_prepare($stmtPatient, $sqlPatient);
                                  mysqli_stmt_bind_param($stmtPatient, 'i', $userId);
                                  mysqli_stmt_execute($stmtPatient);
                              }

                              if ($role === 'doctor') {
                                  mysqli_query($conn, "CREATE TABLE IF NOT EXISTS doctor_profiles (
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
                                  $sqlDoctor = "INSERT INTO doctor_profiles (user_id, email) VALUES (?, ?)";
                                  $stmtDoctor = mysqli_stmt_init($conn);
                                  mysqli_stmt_prepare($stmtDoctor, $sqlDoctor);
                                  mysqli_stmt_bind_param($stmtDoctor, 'is', $userId, $email);
                                  mysqli_stmt_execute($stmtDoctor);
                              }

                              echo "<div class='alert alert-success'>Пользователь зарегистрирован успешно</div>";
                          } else {
                              echo "<div class='alert alert-danger'>Ошибка при регистрации</div>";
                          }
                      }
                  }
                  ?>
                  <form action="register.php" method="post">
                    <div class="form-outline mb-4">
                      <input type="text" name="login" id="formLogin" class="form-control" placeholder="Логин" />
                      <label class="form-label" for="formLogin">Логин</label>
                    </div>

                    <div class="mb-4">
                      <label class="form-label" for="registerRole">Роль</label>
                      <select class="form-select" name="role" id="registerRole">
                        <option value="patient">Пациент</option>
                        <option value="doctor">Врач</option>
                        <option value="admin">Администратор</option>
                      </select>
                    </div>

                    <div class="form-outline mb-4">
                      <input type="email" name="email" id="formEmail" class="form-control" placeholder="Email" />
                      <label class="form-label" for="formEmail">Email</label>
                    </div>

                    <div class="form-outline mb-4">
                      <input type="password" name="password" id="formPassword" class="form-control" placeholder="Пароль" />
                      <label class="form-label" for="formPassword">Пароль</label>
                    </div>

                    <div class="form-outline mb-4">
                      <input type="password" name="repeat_password" id="formRepeatPassword" class="form-control" placeholder="Повторный пароль" />
                      <label class="form-label" for="formRepeatPassword">Повторный пароль</label>
                    </div>

                    <div class="text-center pt-1 mb-5 pb-1">
                      <button class="btn btn-primary btn-lg w-100" type="submit" name="submit">Зарегистрировать</button>
                    </div>

                    <div class="d-flex align-items-center justify-content-center pb-4">
                      <p class="mb-0 me-2">Назад к панели администратора?</p>
                      <a href="../admin/index.php" class="btn btn-outline-primary">Вернуться</a>
                    </div>
                  </form>
                </div>
              </div>
              <div class="col-lg-6">
                <div id="promoCarousel" class="carousel slide h-100" data-bs-ride="carousel" data-bs-interval="2000">
                  <div class="carousel-inner h-100 rounded-end">
                    <div class="carousel-item active h-100">
                      <img src="https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=900&q=80" class="d-block w-100" alt="Clinic interior">
                      <div class="carousel-caption text-start promo-caption">
                        <h4>Управление персоналом</h4>
                        <p>Добавляйте врачей и администраторов для быстрого запуска системы.</p>
                      </div>
                    </div>
                    <div class="carousel-item h-100">
                      <img src="https://images.unsplash.com/photo-1526256262350-7da7584cf5eb?auto=format&fit=crop&w=900&q=80" class="d-block w-100" alt="Medical team">
                      <div class="carousel-caption text-start promo-caption">
                        <h4>Надежность системы</h4>
                        <p>Каждый новый сотрудник сразу получает нужную роль и доступ.</p>
                      </div>
                    </div>
                    <div class="carousel-item h-100">
                      <img src="https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=900&q=80" class="d-block w-100" alt="Healthcare technology">
                      <div class="carousel-caption text-start promo-caption">
                        <h4>Стабильная работа</h4>
                        <p>Удобная админ-панель для контроля регистраторов и врачей.</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</body>
</html>
