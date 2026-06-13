<?php
// logout.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE users SET active_session_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    systemLog('LOGOUT', $_SESSION['user_id'], "User logged out");
}

session_destroy();
header("Location: login.php");
exit;
?>
