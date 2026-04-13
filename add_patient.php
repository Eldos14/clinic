<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fio = $_POST['fio'];
    $iin = $_POST['iin'];
    $birth = $_POST['birth'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $contacts = $_POST['contacts'];

    $sql = "INSERT INTO Пациенты (фио, иин, дата_рождения, пол, адресс, контакты)
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$fio, $iin, $birth, $gender, $address, $contacts]);

    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Добавить пациента</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<h1>Добавить пациента</h1>

<form method="POST" class="form-patient">
    <input type="text" name="fio" placeholder="ФИО" required>
    <br>
   
    <input type="text" name="iin" placeholder="ИИН">
    <br>

    <input type="date" name="birth">
<br>
    <input type="text" name="gender" placeholder="Пол">
    <br>
    <input type="text" name="address" placeholder="Адрес">
   <br>
    <input type="text" name="contacts" placeholder="Контакты">
    <br>    
    <button type="submit">Сохранить</button>
</form>

</body>
</html>
