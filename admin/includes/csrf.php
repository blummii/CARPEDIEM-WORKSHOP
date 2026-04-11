<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input() {
    $t = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"_csrf\" value=\"$t\">";
}

function check_csrf($token) {
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], (string)$token);
}
