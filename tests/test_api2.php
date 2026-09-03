<?php session_start(); $_SESSION['user_id']=1; $_SESSION['role']='admin'; $_GET['action']='get_reports'; $_GET['period']='today'; require 'reports_api.php'; ?>
