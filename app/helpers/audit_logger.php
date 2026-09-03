<?php
/**
 * Structured Audit Trail Logging Helper
 */

function write_audit_log($action, $details = '') {
    $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'audit.log';
    
    // Fallback username if session not started or unauthenticated
    $username = 'anonymous';
    if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
    }
    
    $logData = [
        'timestamp' => date('c'),
        'user' => $username,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'action' => $action,
        'details' => $details
    ];
    
    @file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);
}
