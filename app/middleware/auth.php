<?php
/**
 * Authentication and Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    ini_set('session.cookie_lifetime', 86400); // 24 hours
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    // Cookie secure flag enabled dynamically for HTTPS setups
    $secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) || 
              isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    ini_set('session.cookie_secure', $secure ? 1 : 0);
    
    session_start();
}

// Generate CSRF token and set cookie for client-side access
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$secure = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) || 
          isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
setcookie('CSRF-TOKEN', $_SESSION['csrf_token'], [
    'expires' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => false, // Accessible by script.js
    'samesite' => 'Lax'
]);

function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function login_required($role = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    
    if (!empty($_SESSION['force_password_change'])) {
        header('Location: /force_password_change');
        exit;
    }
    
    // Management/Admin has access to everything
    if ($_SESSION['role'] === 'management') {
        return;
    }

    if ($role && $_SESSION['role'] !== $role) {
        header('Location: /login');
        exit;
    }
}

function get_session_user() {
    return isset($_SESSION['user_id']) ? $_SESSION : null;
}

// IP-based Login Block for Brute Force Protection
function is_ip_blocked($conn, $ip) {
    try {
        $stmt = $conn->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        if ($row && $row['attempts'] >= 5) {
            $lastAttempt = strtotime($row['last_attempt']);
            if ((time() - $lastAttempt) < 900) { // Blocked for 15 minutes (900 seconds)
                return true;
            } else {
                // Block expired, reset count
                $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                $stmt->execute([$ip]);
            }
        }
    } catch (Exception $e) {}
    return false;
}

function record_login_attempt($conn, $ip, $success) {
    try {
        if ($success) {
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->execute([$ip]);
        } else {
            $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, datetime('now', 'localtime'))
                                    ON CONFLICT(ip_address) DO UPDATE SET attempts = attempts + 1, last_attempt = datetime('now', 'localtime')");
            $stmt->execute([$ip]);
        }
    } catch (Exception $e) {}
}

// API Authorization Middleware
function check_api_role($allowed_roles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthenticated access']);
        exit;
    }
    if (!empty($_SESSION['force_password_change'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Password change required before accessing APIs']);
        exit;
    }
    if ($_SESSION['role'] === 'management') {
        return; // Management has global access
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access Denied: Unauthorized role']);
        exit;
    }
}

