<?php
// includes/functions.php

// 1. Session Cookie Hardening
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// 2. Global Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");

require_once __DIR__ . '/logger.php';

// CSRF Protection Helpers
function getCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken()) . '">';
}


function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function checkRole($allowed_roles) {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        systemLog('UNAUTHORIZED_ACCESS_ATTEMPT', $_SESSION['user_id'], 'Attempted to access restricted page');
        die("Anda tidak memiliki akses ke halaman ini.");
    }
}

function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function generateBadgeStatus($status) {
    if ($status === 'PENDING') return '<span class="badge bg-warning text-dark">Pending</span>';
    if ($status === 'VERIFIED') return '<span class="badge bg-success">Verified</span>';
    if ($status === 'REJECTED') return '<span class="badge bg-danger">Rejected</span>';
    return '<span class="badge bg-secondary">'.$status.'</span>';
}
?>
