<?php
/**
 * Main Router / Entry Point for Hospital Portal (PHP Version)
 */

require_once dirname(__DIR__) . '/app/helpers/db.php';
require_once dirname(__DIR__) . '/app/middleware/auth.php';
require_once dirname(__DIR__) . '/app/helpers/template_parser.php';
require_once dirname(__DIR__) . '/app/helpers/audit_logger.php';

// Initialize DB on first run
init_db();

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Helper to send JSON response
function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ═══════════════════════════════════════════
// PAGE ROUTES
// ═══════════════════════════════════════════

if ($uri === '/' || $uri === '') {
    header('Location: /login');
    exit;
}

if ($uri === '/login') {
    if ($method === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf)) {
            $doctors = get_all_doctors(true);
            $total_boxes = count($doctors) + 3;
            echo TemplateParser::render(dirname(__DIR__) . '/templates/login.html', [
                'error' => 'Invalid CSRF token.',
                'doctor_cards' => '',
                'total_boxes' => $total_boxes
            ]);
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $conn = get_db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (is_ip_blocked($conn, $ip)) {
            write_audit_log('ACCOUNT_LOCKOUT', 'IP address temporarily blocked due to brute-force attempts: ' . $ip);
            $doctors = get_all_doctors(true);
            $total_boxes = count($doctors) + 3;
            echo TemplateParser::render(dirname(__DIR__) . '/templates/login.html', [
                'error' => 'Too many failed login attempts. Please try again in 15 minutes.',
                'doctor_cards' => '',
                'total_boxes' => $total_boxes
            ]);
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        $authenticated = false;
        if ($user && $user['is_active'] == 1) {
            if (password_verify($password, $user['password'])) {
                $authenticated = true;
            } elseif ($password === $user['password']) {
                $authenticated = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$newHash, $user['id']]);
                write_audit_log('PASSWORD_UPGRADE', 'Plaintext password upgraded to bcrypt hash for user: ' . $username);
            }
        }

        record_login_attempt($conn, $ip, $authenticated);
        
        if ($user && $user['is_active'] == 0) {
            $doctors = get_all_doctors(true);
            $total_boxes = count($doctors) + 3;
            echo TemplateParser::render(dirname(__DIR__) . '/templates/login.html', [
                'error' => 'Account is inactive. Please contact admin.', 
                'doctor_cards' => '', 
                'total_boxes' => $total_boxes
            ]);
            exit;
        }

        if ($authenticated) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['doctor_type'] = $user['doctor_type'];
            if ($user['role'] === 'doctor') {
                $_SESSION['doctor_id'] = $user['id'];
            }
            
            $display_name = $user['display_name'] ?: $user['username'];
            $_SESSION['display_name'] = $display_name;

            // Check if user is using a seed/default password
            $defaults = ['reception123', 'doctor123', 'pharma123', 'admin123', 'monitor123'];
            if (in_array($password, $defaults)) {
                $_SESSION['force_password_change'] = true;
                header('Location: /force_password_change');
                exit;
            }

            write_audit_log('LOGIN', 'User successfully logged in: ' . $username);

            $dest = [
                'receptionist' => '/receptionist',
                'doctor' => '/doctor',
                'pharmacist' => '/pharmacy',
                'management' => '/management',
                'monitor' => '/monitor'
            ];
            $redirect_url = $dest[$user['role']] ?? '/login';
            header('Location: ' . $redirect_url . ($redirect_url !== '/login' ? '?v=' . time() : ''));
            exit;
        } else {
            write_audit_log('LOGIN_FAILED', 'Failed login attempt for username: ' . $username);
            $doctors = get_all_doctors(true); // Only active doctors for login screen
            $doctor_cards = '';
            foreach ($doctors as $doc) {
                $dn = trim(str_ireplace(['Sir', 'Mam'], '', $doc['display_name']));
                if (stripos($dn, 'Dr.') !== 0) $dn = 'Dr. ' . $dn;
                
                $dt = htmlspecialchars($doc['doctor_type']);
                $dn_formatted = format_doctor_name($dn, $dt);
                $un = htmlspecialchars($doc['username']);
                $doctor_cards .= '<div class="modern-role-card" onclick="selectRole(\'doctor\', \''.$dn_formatted.'\', \''.$un.'\')">
                    <div class="role-icon-circle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 00-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 010 7.75"></path>
                        </svg>
                    </div>
                    <div class="role-content">
                        <span class="role-label">Doctor ('.$dt.')</span>
                        <span class="role-sublabel">'.$dn_formatted.'</span>
                    </div>
                </div>';
            }
            // Add Monitor Role Card
            $doctor_cards .= '<div class="modern-role-card" onclick="selectRole(\'monitor\', \'TV Monitor\', \'monitor\')">
                <div class="role-icon-circle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>
                </div>
                <div class="role-content">
                    <span class="role-label">TV Monitor</span>
                    <span class="role-sublabel">Waiting Hall Display</span>
                </div>
            </div>';
            
            $total_boxes = count($doctors) + 3;
            echo TemplateParser::render(dirname(__DIR__) . '/templates/login.html', [
                'error' => 'Invalid username or password', 
                'doctor_cards' => $doctor_cards,
                'total_boxes' => $total_boxes
            ]);
            exit;
        }
    }
    
    $doctors = get_all_doctors(true); // Only active doctors
    $doctor_cards = '';
    foreach ($doctors as $doc) {
        $dn = trim(str_ireplace(['Sir', 'Mam'], '', $doc['display_name']));
        if (stripos($dn, 'Dr.') !== 0) $dn = 'Dr. ' . $dn;

        $un = htmlspecialchars($doc['username']);
        $dt = htmlspecialchars($doc['doctor_type']);
        $dn_formatted = format_doctor_name($dn, $dt);
        $doctor_cards .= '<div class="modern-role-card" onclick="selectRole(\'doctor\', \''.$dn_formatted.'\', \''.$un.'\')">
            <div class="role-icon-circle">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4-4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 00-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 010 7.75"></path>
                </svg>
            </div>
            <div class="role-content">
                <span class="role-label">Doctor ('.$dt.')</span>
                <span class="role-sublabel">'.$dn_formatted.'</span>
            </div>
        </div>';
    }
    
    // Add Monitor Role Card
    $doctor_cards .= '<div class="modern-role-card" onclick="selectRole(\'monitor\', \'TV Monitor\', \'monitor\')">
        <div class="role-icon-circle">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>
        </div>
        <div class="role-content">
            <span class="role-label">TV Monitor</span>
            <span class="role-sublabel">Waiting Hall Display</span>
        </div>
    </div>';
    
    $total_boxes = count($doctors) + 3;
    echo TemplateParser::render(dirname(__DIR__) . '/templates/login.html', [
        'error' => '', 
        'doctor_cards' => $doctor_cards,
        'total_boxes' => $total_boxes
    ]);
    exit;
}

if ($uri === '/logout') {
    // Prevent browser prefetching from destroying the session
    $isPrefetch = (isset($_SERVER['HTTP_X_PURPOSE']) && $_SERVER['HTTP_X_PURPOSE'] === 'prefetch') ||
                  (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] === 'prefetch') ||
                  (isset($_SERVER['HTTP_SEC_PURPOSE']) && strpos($_SERVER['HTTP_SEC_PURPOSE'], 'prefetch') !== false) ||
                  (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && in_array($_SERVER['HTTP_SEC_FETCH_DEST'], ['empty', 'image'])) ||
                  (isset($_SERVER['HTTP_PURPOSE']) && $_SERVER['HTTP_PURPOSE'] === 'prefetch');
                  
    if ($isPrefetch) {
        http_response_code(200);
        exit;
    }

    write_audit_log('LOGOUT', 'User logged out');
    session_destroy();
    header('Location: /login');
    exit;
}

if ($uri === '/force_password_change') {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }

    if ($method === 'POST') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf)) {
            echo TemplateParser::render(dirname(__DIR__) . '/templates/force_password_change.html', [
                'error' => 'Invalid CSRF token.',
                'username' => $_SESSION['username']
            ]);
            exit;
        }

        $new_password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        
        $defaults = ['reception123', 'doctor123', 'pharma123', 'admin123', 'monitor123'];
        
        if (strlen($new_password) < 8) {
            echo TemplateParser::render(dirname(__DIR__) . '/templates/force_password_change.html', [
                'error' => 'Password must be at least 8 characters long.',
                'username' => $_SESSION['username']
            ]);
            exit;
        }
        
        if ($new_password !== $confirm_password) {
            echo TemplateParser::render(dirname(__DIR__) . '/templates/force_password_change.html', [
                'error' => 'Passwords do not match.',
                'username' => $_SESSION['username']
            ]);
            exit;
        }
        
        if (in_array($new_password, $defaults)) {
            echo TemplateParser::render(dirname(__DIR__) . '/templates/force_password_change.html', [
                'error' => 'You cannot use a default seed password. Please choose a new, secure password.',
                'username' => $_SESSION['username']
            ]);
            exit;
        }

        $conn = get_db();
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);

        // Clear force change flag
        unset($_SESSION['force_password_change']);
        
        write_audit_log('PASSWORD_CHANGED', 'Forced password change completed for user: ' . $_SESSION['username']);
        
        // Log the user in fully
        write_audit_log('LOGIN', 'User successfully logged in after password change: ' . $_SESSION['username']);

        $dest = [
            'receptionist' => '/receptionist',
            'doctor' => '/doctor',
            'pharmacist' => '/pharmacy',
            'management' => '/management',
            'monitor' => '/monitor'
        ];
        $redirect_url = $dest[$_SESSION['role']] ?? '/login';
        header('Location: ' . $redirect_url);
        exit;
    }

    echo TemplateParser::render(dirname(__DIR__) . '/templates/force_password_change.html', [
        'error' => '',
        'username' => $_SESSION['username']
    ]);
    exit;
}

if ($uri === '/receptionist') {
    login_required('receptionist');
    echo TemplateParser::render(dirname(__DIR__) . '/templates/receptionist.html', ['display_name' => $_SESSION['display_name']]);
    exit;
}

if ($uri === '/doctor') {
    login_required('doctor');
    $formatted_name = format_doctor_name($_SESSION['display_name'], $_SESSION['doctor_type']);
    echo TemplateParser::render(dirname(__DIR__) . '/templates/doctor.html', [
        'display_name' => $formatted_name,
        'doctor_name' => $formatted_name,
        'doctor_type' => $_SESSION['doctor_type']
    ]);
    exit;
}

if ($uri === '/pharmacy') {
    login_required('pharmacist');
    echo TemplateParser::render(dirname(__DIR__) . '/templates/pharmacy.html', ['display_name' => $_SESSION['display_name']]);
    exit;
}

if ($uri === '/management') {
    login_required('management');
    echo TemplateParser::render(dirname(__DIR__) . '/templates/management.html', ['display_name' => $_SESSION['display_name']]);
    exit;
}

if ($uri === '/monitor') {
    login_required('monitor');
    echo TemplateParser::render(dirname(__DIR__) . '/templates/monitor.html', []);
    exit;
}

if ($uri === '/portfolio') {
    echo TemplateParser::render(dirname(__DIR__) . '/templates/portfolio.html', []);
    exit;
}

// ═══════════════════════════════════════════
// API ROUTES — Dispatch to api.php
// ═══════════════════════════════════════════

if ($uri === '/control_access') {
    login_required('management');
    $module = $_GET['module'] ?? '';

    if ($module === 'receptionist') {
        echo TemplateParser::render(dirname(__DIR__) . '/templates/receptionist.html', ['display_name' => $_SESSION['display_name'] ?? 'Reception']);
        exit;
    }

    if ($module === 'doctor') {
        $type = $_GET['type'] ?? 'Gent';
        $id = $_GET['id'] ?? null;
        $conn = get_db();
        
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id=? AND role='doctor' AND is_active=1");
            $stmt->execute([$id]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE role='doctor' AND doctor_type=? AND is_active=1 LIMIT 1");
            $stmt->execute([$type]);
        }
        
        $doc = $stmt->fetch();
        if ($doc) {
            $_SESSION['doctor_id'] = $doc['id'];
            $_SESSION['doctor_type'] = $doc['doctor_type'];
            $formatted_name = format_doctor_name($doc['display_name'], $doc['doctor_type']);
            echo TemplateParser::render(dirname(__DIR__) . '/templates/doctor.html', [
                'display_name' => $formatted_name,
                'doctor_name' => $formatted_name,
                'doctor_type' => $doc['doctor_type']
            ]);
        } else {
            echo "<h2>Doctor Not Found</h2>";
        }
        exit;
    }

    if ($module === 'pharmacy') {
        echo TemplateParser::render(dirname(__DIR__) . '/templates/pharmacy.html', ['display_name' => $_SESSION['display_name'] ?? 'Pharmacy']);
        exit;
    }

    if ($module === 'monitor') {
        $conn = get_db();
        $stmt = $conn->prepare("SELECT * FROM users WHERE role='monitor' AND is_active=1 LIMIT 1");
        $stmt->execute();
        if ($stmt->fetch()) {
            echo TemplateParser::render(dirname(__DIR__) . '/templates/monitor.html', []);
        } else {
            echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Access Denied</h2><p>Monitor module is currently disabled. Please enable it in the Manage Staff section.</p></div>";
        }
        exit;
    }
}

if (strpos($uri, '/api/') === 0) {
    require_once __DIR__ . '/api/api.php';
    exit;
}

if (strpos($uri, '/reports_api') === 0) {
    require_once __DIR__ . '/api/reports_api.php';
    exit;
}

// 404
http_response_code(404);
echo "404 Not Found";
