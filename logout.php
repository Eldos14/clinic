<?php
session_start();
session_destroy();
header("Location: clinic/../reg_login/login.php");
?>