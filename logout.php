<?php
require_once __DIR__ . '/includes/auth.php';

$user = current_user($pdo);

if ($user) {
    log_activity($pdo, (int) $user['id'], 'logout', 'Logged out from CyberAware.');
}

logout_user();

redirect('index.php');