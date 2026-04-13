<?php
session_start();
if(isset($_SESSION["user"])){
  header("Location: ../index.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход</title>
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
    .auth-side {
      background: linear-gradient(180deg, #1e73d7 0%, #0d58aa 100%);
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 36px 30px;
      min-height: 420px;
    }
    .auth-side h4 {
      color: #ffffff;
      margin-top: 18px;
      margin-bottom: 16px;
    }
    .auth-side p {
      color: rgba(255,255,255,0.9);
      line-height: 1.7;
      font-size: 14px;
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
    .auth-form .form-label {
      font-size: 13px;
      color: #6c7a8f;
    }
    .alt-link {
      color: #1e73d7;
      text-decoration: none;
      font-weight: 600;
    }
    .alt-link:hover {
      text-decoration: underline;
    }
    .carousel-inner, .carousel-item {
      min-height: 520px;
    }
    .carousel-item img {
      object-fit: cover;
      width: 100%;
      height: 100%;
      border-radius: 0 24px 24px 0;
    }
    .carousel-caption {
      bottom: 24px;
      left: 24px;
      right: 24px;
      background: rgba(0, 0, 0, 0.4);
      border-radius: 16px;
      padding: 1rem 1.2rem;
    }
    .promo-caption h4 {
      font-size: 1.3rem;
      margin-bottom: 0.4rem;
    }
    .promo-caption p {
      font-size: 0.95rem;
      line-height: 1.5;
      margin-bottom: 0;
    }
  </style>
</head>
<body>
  <section class="auth-wrapper">
    <div class="container">
    <?php
      if(isset($_POST["login"])){
        $email = $_POST["email"];
        $password = $_POST["password"];
        $selectedRole = $_POST["role"] ?? 'patient';
        require_once "database.php";

        if(!function_exists('ensureRoleColumn')) {
          function ensureRoleColumn($conn) {
            $result = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
            if($result && mysqli_num_rows($result) === 0) {
              mysqli_query($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'patient'");
            }
          }
        }
        ensureRoleColumn($conn);

        $stmt = mysqli_prepare($conn, "SELECT id, login, password, email, role FROM users WHERE email = ? AND role = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $email, $selectedRole);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_array($result, MYSQLI_ASSOC);

        if($user){
            if(password_verify($password,$user["password"])){
              session_regenerate_id(true);
              $_SESSION["user"] = "yes";
              $_SESSION["user_id"] = $user["id"];
              $_SESSION["user_login"] = $user["login"];
              $_SESSION["user_email"] = $user["email"];
              $_SESSION["user_role"] = $user["role"];

              if($user["role"] === 'admin') {
                header("Location: ../admin/");
              } elseif($user["role"] === 'doctor') {
                header("Location: ../doctor/");
              } else {
                header("Location: ../index.php");
              }
              die();
            } else {
                echo "<div class='alert alert-danger'>Неверный email, пароль или роль</div>";
            }
        } else {
          echo "<div class='alert alert-danger'>Неверный email, пароль или роль</div>";
        }
      }
    ?>
    <div class="row justify-content-center">
      <div class="col-xl-10">
        <div class="card auth-card">
          <div class="row g-0">
            <div class="col-lg-6">
              <div class="card-body p-md-5 mx-md-4 auth-form">

                <div class="text-center">
                  <img src="../images/icons/damumed_logo.png"
                    style="width: 40px;" alt="logo">
                  <h4 class="mt-1 mb-3 pb-1">Damumed</h4>
                </div>

                <form method="POST" action="login.php">
                  <div class="mb-2">
                    <label class="form-label" for="loginRole">Я в системе</label>
                    <select class="form-select" name="role" id="loginRole">
                      <option value="patient">Пациент</option>
                      <option value="doctor">Врач</option>
                      <option value="admin">Администратор</option>
                    </select>
                  </div>

                  <div data-mdb-input-init class="form-outline mb-2">
                    <input type="email" name="email" id="form2Example11" class="form-control"
                      placeholder="Email" />
                    <label class="form-label" for="form2Example11">Email</label>
                  </div>

                  <div data-mdb-input-init class="form-outline mb-2">
                    <input type="password" name="password" id="form2Example22" class="form-control" placeholder="Пароль" />
                    <label class="form-label" for="form2Example22">Пароль</label>
                  </div>

                  <div class="text-center pt-1 mb-5 pb-1">
                    <button class="btn btn-primary btn-lg w-100 mb-3" type="submit" name="login">Войти</button>
                    <a class="text-muted" href="#!">Забыли пароль?</a>
                  </div>

                  <div class="d-flex align-items-center justify-content-center pb-4">
                    <p class="mb-0 me-2">Еще не зарегистрированы?</p>
                    <a href="registrator.php" class="btn btn-outline-primary">Зарегистрироваться</a>
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
                      <h4>Современная клиника</h4>
                      <p>Комфортное пространство для пациентов и врачей с технологичным подходом к медицине.</p>
                    </div>
                  </div>
                  <div class="carousel-item h-100">
                    <img src="https://images.unsplash.com/photo-1526256262350-7da7584cf5eb?auto=format&fit=crop&w=900&q=80" class="d-block w-100" alt="Medical team">
                    <div class="carousel-caption text-start promo-caption">
                      <h4>Профессиональная команда</h4>
                      <p>Надежная поддержка врачей и специалистов на каждом этапе лечения.</p>
                    </div>
                  </div>
                  <div class="carousel-item h-100">
                    <img src="https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=900&q=80" class="d-block w-100" alt="Healthcare technology">
                    <div class="carousel-caption text-start promo-caption">
                      <h4>Цифровой сервис</h4>
                      <p>Удобный доступ к медицинским данным, записи на приём и история пациента в одном приложении.</p>
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