<?php
if (!isset($activePage)) {
    $activePage = '';
}
$headerInitial = $headerInitial ?? strtoupper(substr($patient['фио'] ?? ($currentPatient['фио'] ?? 'EA'), 0, 1));
?>
<header>
    <div class="header-container">
        <div class="logo">
            <a href="index.php"><img src="images/icons/damumed_logo.png" alt="Логотип"></a>
        </div>
        <nav class="nav">
            <a href="index.php" class="<?= $activePage === 'home' ? 'active' : '' ?>">Главная</a>
            <a href="myDetails.php" class="<?= $activePage === 'details' ? 'active' : '' ?>">Мои данные</a>
            <a href="#">Избранное</a>
            <a href="notifications.php" class="<?= $activePage === 'notifications' ? 'active' : '' ?>">Уведомления</a>
        </nav>
        <div class="header-right">
            <?php if (!empty($headerPhoto)): ?>
                <img src="<?= htmlspecialchars($headerPhoto) ?>" alt="Аватар" class="header-avatar">
            <?php else: ?>
                <span class="user"><?= htmlspecialchars($headerInitial) ?></span>
            <?php endif; ?>
            <a href="logout.php" class="logout">Выйти</a>
        </div>
    </div>
</header>
