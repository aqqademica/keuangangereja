<?php
// includes/logger.php

function systemLog($action, $user_id = null, $details = '') {
    $logDir = __DIR__ . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/system_activity.log';
    
    $date = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userIdStr = $user_id ? "USER_ID:$user_id" : "SYSTEM/GUEST";
    
    $logMessage = "[$date] [$ip] [$userIdStr] ACTION: $action | DETAILS: $details" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>
