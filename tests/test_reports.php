<?php
$_GET['action'] = 'get_print_report';
$_GET['period'] = 'today';
$_SERVER['REQUEST_METHOD'] = 'GET';
// bypass auth check since it's CLI
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'management';
require 'reports_api.php';
