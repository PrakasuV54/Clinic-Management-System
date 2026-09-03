<?php
date_default_timezone_set('Asia/Kolkata');

require_once dirname(dirname(__DIR__)) . '/app/helpers/db.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/auth.php';

// Enforce management access
check_api_role(['management']);


$action = $_GET['action'] ?? 'get_reports';
$conn = get_db();

if ($action === 'plaintext_report') {
    $conn = get_db();
    $stmt = $conn->query("SELECT id, username, role, display_name, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $remaining_plaintext = [];
    foreach ($users as $u) {
        $pwd = $u['password'];
        $info = password_get_info($pwd);
        if ($info['algo'] === 0) {
            unset($u['password']);
            $remaining_plaintext[] = $u;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'remaining_plaintext_count' => count($remaining_plaintext),
        'accounts' => $remaining_plaintext
    ]);
    exit;
}

if ($action === 'get_reports') {
    $period = $_GET['period'] ?? 'today';
    $start_date = $_GET['start'] ?? '';
    $end_date = $_GET['end'] ?? '';
    
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $weekly_start = date('Y-m-d', strtotime('-7 days'));
    $this_month = date('Y-m');
    $last_month = date('Y-m', strtotime('first day of last month'));
    $this_year = date('Y');

    $date_filter_patients = "1=1";
    $date_filter_sales = "1=1";
    $date_filter_agency = "1=1";
    $date_filter_returns = "1=1";
    
    if ($period === 'today') {
        $date_filter_patients = "DATE(created_at) = '$today'";
        $date_filter_sales = "DATE(created_at) = '$today'";
        $date_filter_agency = "purchase_date = '$today'";
        $date_filter_returns = "return_date = '$today'";
    } elseif ($period === 'yesterday') {
        $date_filter_patients = "DATE(created_at) = '$yesterday'";
        $date_filter_sales = "DATE(created_at) = '$yesterday'";
        $date_filter_agency = "purchase_date = '$yesterday'";
        $date_filter_returns = "return_date = '$yesterday'";
    } elseif ($period === 'weekly') {
        $date_filter_patients = "DATE(created_at) >= '$weekly_start'";
        $date_filter_sales = "DATE(created_at) >= '$weekly_start'";
        $date_filter_agency = "purchase_date >= '$weekly_start'";
        $date_filter_returns = "return_date >= '$weekly_start'";
    } elseif ($period === 'monthly') {
        $date_filter_patients = "strftime('%Y-%m', created_at) = '$this_month'";
        $date_filter_sales = "strftime('%Y-%m', created_at) = '$this_month'";
        $date_filter_agency = "strftime('%Y-%m', purchase_date) = '$this_month'";
        $date_filter_returns = "strftime('%Y-%m', return_date) = '$this_month'";
    } elseif ($period === 'last_month') {
        $date_filter_patients = "strftime('%Y-%m', created_at) = '$last_month'";
        $date_filter_sales = "strftime('%Y-%m', created_at) = '$last_month'";
        $date_filter_agency = "strftime('%Y-%m', purchase_date) = '$last_month'";
        $date_filter_returns = "strftime('%Y-%m', return_date) = '$last_month'";
    } elseif ($period === 'yearly') {
        $date_filter_patients = "strftime('%Y', created_at) = '$this_year'";
        $date_filter_sales = "strftime('%Y', created_at) = '$this_year'";
        $date_filter_agency = "strftime('%Y', purchase_date) = '$this_year'";
        $date_filter_returns = "strftime('%Y', return_date) = '$this_year'";
    } elseif ($period === 'custom' && $start_date && $end_date) {
        $date_filter_patients = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
        $date_filter_sales = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
        $date_filter_agency = "purchase_date BETWEEN '$start_date' AND '$end_date'";
        $date_filter_returns = "return_date BETWEEN '$start_date' AND '$end_date'";
    }

    $response = [];

    // 1. Executive Summary & Financial Report
    // Patients
    $stmt = $conn->query("SELECT COUNT(*) as total_patients FROM patients WHERE $date_filter_patients");
    $total_patients = $stmt->fetchColumn() ?: 0;

    // Consultations & Revenue
    $stmt = $conn->query("SELECT 
        COUNT(*) as total_consultations,
        SUM(consultation_fee) as doctor_revenue,
        SUM(total_amount) as med_revenue,
        SUM(cost_amount) as med_cost,
        SUM(scan_fee) as scan_revenue,
        SUM(injection_cost) as inj_cost,
        SUM(iv_cost) as iv_cost,
        SUM(upt_cost) as upt_cost,
        SUM(cash_amount) as cash,
        SUM(gpay_amount) as gpay,
        SUM(phonepe_amount) as phonepe,
        SUM(paid_amount) as paid,
        SUM(balance_amount) as pending,
        SUM(prev_balance_cleared) as cleared_pending,
        SUM(discount_percent) as total_discount
        FROM prescriptions WHERE status='dispensed' AND $date_filter_patients");
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch Injection, IV, UPT revenue from custom logic where they are inside medicines JSON
    // We will extract them the robust way by re-fetching and parsing if needed, but wait,
    // injection_fee, iv_fee, upt_fee aren't natively columns. 
    // Wait, let's extract them just like api.php does.
    $stmt = $conn->query("SELECT id, medicines, injection_details, iv_details, upt_card FROM prescriptions WHERE status='dispensed' AND $date_filter_patients");
    $all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inj_revenue = 0;
    $iv_revenue = 0;
    $upt_revenue = 0;
    $total_medicine_revenue_only = 0;

    $medicine_stats = [];

    foreach ($all_prescriptions as $p) {
        $meds = json_decode($p['medicines'] ?: '[]', true);
        foreach ($meds as $m) {
            $name = $m['name'] ?? '';
            $qty = (float)($m['qty'] ?? 0) - (float)($m['returned_qty'] ?? 0);
            $amt = (float)($m['amount'] ?? 0) - (float)($m['returned_amount'] ?? 0);
            
            if ($name === 'Injection Fee') {
                $inj_revenue += $amt;
            } elseif ($name === 'IV Fluid Fee') {
                $iv_revenue += $amt;
            } elseif ($name === 'UPT Card Fee') {
                $upt_revenue += $amt;
            } else {
                $total_medicine_revenue_only += $amt;
            }

            if (!isset($medicine_stats[$name])) {
                $medicine_stats[$name] = ['qty' => 0, 'revenue' => 0];
            }
            $medicine_stats[$name]['qty'] += $qty;
            $medicine_stats[$name]['revenue'] += $amt;
        }
    }

    $doctor_revenue = (float)($pr['doctor_revenue'] ?? 0);
    $scan_revenue = (float)($pr['scan_revenue'] ?? 0);
    $medicine_revenue = $total_medicine_revenue_only + $inj_revenue + $iv_revenue;
    $treatment_revenue = $upt_revenue;

    $total_revenue = $doctor_revenue + $medicine_revenue + $scan_revenue + $treatment_revenue;
    $total_discount = (float)($pr['total_discount'] ?? 0);
    $total_revenue -= $total_discount;

    // Direct Sales
    $stmt = $conn->query("SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as direct_revenue,
        SUM(cost_amount) as direct_cost,
        SUM(cash_amount) as ds_cash,
        SUM(gpay_amount) as ds_gpay,
        SUM(phonepe_amount) as ds_phonepe,
        SUM(bank_amount) as ds_bank,
        SUM(paid_amount) as ds_paid,
        SUM(balance_amount) as ds_pending
        FROM direct_sales WHERE status='completed' AND $date_filter_sales");
    $ds = $stmt->fetch(PDO::FETCH_ASSOC);

    $direct_revenue = (float)($ds['direct_revenue'] ?? 0);
    $medicine_revenue += $direct_revenue;
    $total_revenue += $direct_revenue;

    // Agency Purchases (Expenses)
    $stmt = $conn->query("SELECT COUNT(*) as purchase_count, SUM(grand_total) as total_purchases FROM agency_purchases WHERE $date_filter_agency");
    $ap = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = (float)($ap['total_purchases'] ?? 0);

    // Inventory Value
    $stmt = $conn->query("SELECT COUNT(*) as count, SUM(stock * purchase_price) as inventory_value FROM inventory WHERE stock > 0");
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    $inventory_value = (float)($inv['inventory_value'] ?? 0);

    // Low stock
    $stmt = $conn->query("SELECT COUNT(*) FROM inventory WHERE stock <= min_stock AND stock > 0");
    $low_stock = $stmt->fetchColumn() ?: 0;

    $stmt = $conn->query("SELECT COUNT(*) FROM inventory WHERE stock = 0");
    $out_of_stock = $stmt->fetchColumn() ?: 0;

    // Suppliers
    $stmt = $conn->query("SELECT COUNT(*) FROM agency_suppliers");
    $total_suppliers = $stmt->fetchColumn() ?: 0;

    $net_profit = $total_revenue - $total_expenses;
    $pending_amount = (float)($pr['pending'] ?? 0) + (float)($ds['ds_pending'] ?? 0);
    $collection_amount = (float)($pr['paid'] ?? 0) + (float)($ds['ds_paid'] ?? 0);
    $cleared_pending = (float)($pr['cleared_pending'] ?? 0); // No cleared pending in ds currently

    $cash_collection = (float)($pr['cash'] ?? 0) + (float)($ds['ds_cash'] ?? 0);
    $gpay_collection = (float)($pr['gpay'] ?? 0) + (float)($ds['ds_gpay'] ?? 0);
    $phonepe_collection = (float)($pr['phonepe'] ?? 0) + (float)($ds['ds_phonepe'] ?? 0);
    $bank_collection = (float)($ds['ds_bank'] ?? 0);

    $response['executive'] = [
        'total_patients' => $total_patients,
        'total_consultations' => (int)($pr['total_consultations'] ?? 0),
        'direct_sales_count' => (int)($ds['total_sales'] ?? 0),
        'total_revenue' => $total_revenue,
        'doctor_revenue' => $doctor_revenue,
        'medicine_revenue' => $medicine_revenue,
        'treatment_revenue' => $treatment_revenue,
        'total_expenses' => $total_expenses,
        'net_profit' => $net_profit,
        'pending_amount' => $pending_amount,
        'cleared_pending' => $cleared_pending,
        'collection_amount' => $collection_amount,
        'total_purchases' => $total_expenses,
        'total_suppliers' => $total_suppliers,
        'inventory_value' => $inventory_value,
        'low_stock_count' => $low_stock,
        'out_of_stock_count' => $out_of_stock
    ];

        $response['financial'] = [
        'Cash' => $cash_collection,
        'Bank Transfer' => $bank_collection
    ];

    $upi_collections = [];
    $get_acc_label = function($acc_name) {
        return trim($acc_name) ? trim($acc_name) : "Legacy UPI";
    };

    // For prescriptions
    $stmt_upi = $conn->query("SELECT p.upi_account, u.account_name, SUM(p.gpay_amount) as amount, SUM(p.phonepe_amount) as phonepe 
        FROM prescriptions p 
        LEFT JOIN upi_accounts u ON p.upi_account = u.short_name 
        WHERE p.status='dispensed' AND $date_filter_patients 
        GROUP BY p.upi_account, u.account_name");
    foreach ($stmt_upi->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $label = $get_acc_label($row['account_name'] ?? '');
        if ($row['amount'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['amount'];
        if ($row['phonepe'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['phonepe'];
    }

    // For direct sales
    $stmt_upi = $conn->query("SELECT p.upi_account, u.account_name, SUM(p.gpay_amount) as amount, SUM(p.phonepe_amount) as phonepe 
        FROM direct_sales p 
        LEFT JOIN upi_accounts u ON p.upi_account = u.short_name 
        WHERE p.status='completed' AND $date_filter_sales 
        GROUP BY p.upi_account, u.account_name");
    foreach ($stmt_upi->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $label = $get_acc_label($row['account_name'] ?? '');
        if ($row['amount'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['amount'];
        if ($row['phonepe'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['phonepe'];
    }

    foreach ($upi_collections as $label => $amt) {
        if ($amt > 0) {
            $response['financial'][$label] = ($response['financial'][$label] ?? 0) + $amt;
        }
    }
    $response['financial']['Pending'] = $pending_amount;
    $response['financial']['Cleared Pending'] = $cleared_pending;

    // Doctors Report
    $stmt = $conn->query("SELECT doctor_name, doctor_type, COUNT(*) as p_count, SUM(consultation_fee) as doc_rev, SUM(total_amount) as med_rev FROM prescriptions WHERE status='dispensed' AND $date_filter_patients GROUP BY doctor_name, doctor_type");
    $response['doctors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gents_rev = 0;
    $ladies_rev = 0;
    foreach ($response['doctors'] as $d) {
        $type = strtolower($d['doctor_type'] ?? '');
        $rev = (float)($d['doc_rev'] ?? 0);
        if (strpos($type, 'gent') !== false) {
            $gents_rev += $rev;
        } else {
            $ladies_rev += $rev;
        }
    }
    $response['executive']['gents_doctor_revenue'] = $gents_rev;
    $response['executive']['ladies_doctor_revenue'] = $ladies_rev;

    // Pharmacy Report (Top meds)
    usort($medicine_stats, function($a, $b) { return $b['qty'] <=> $a['qty']; });
    $top_meds = [];
    $i = 0;
    foreach ($medicine_stats as $name => $stats) {
        if ($i++ >= 10) break;
        $top_meds[] = ['name' => $name, 'qty' => $stats['qty'], 'revenue' => $stats['revenue']];
    }
    $response['top_medicines'] = $top_meds;

    // Latest Patients List
    $date_filter_patients_join = str_replace("created_at", "p.created_at", $date_filter_patients);

    $stmt = $conn->query("SELECT p.name, p.phone, p.doctor_name, p.created_at, pr.consultation_fee, pr.total_amount, pr.paid_amount, pr.balance_amount, pr.medicines 
        FROM patients p LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
        WHERE $date_filter_patients_join ORDER BY p.id ASC LIMIT 10");
    $response['patients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Direct Sales List
    $stmt = $conn->query("SELECT customer_name, mobile_number, total_amount, paid_amount, balance_amount, created_at FROM direct_sales WHERE $date_filter_sales ORDER BY id DESC LIMIT 50");
    $response['direct_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Returns List
    $stmt = $conn->query("SELECT * FROM medicine_returns WHERE $date_filter_returns ORDER BY id DESC LIMIT 50");
    $response['returns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($action === 'get_print_report') {
    $period = $_GET['period'] ?? 'today';
    $start_date = $_GET['start'] ?? '';
    $end_date = $_GET['end'] ?? '';
    
    // Copy the same date filter logic
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $weekly_start = date('Y-m-d', strtotime('-7 days'));
    $this_month = date('Y-m');
    $last_month = date('Y-m', strtotime('first day of last month'));
    $this_year = date('Y');

    $date_filter_patients = "1=1";
    $date_filter_sales = "1=1";
    $date_filter_agency = "1=1";
    $date_filter_returns = "1=1";
    
    if ($period === 'today') {
        $date_filter_patients = "DATE(created_at) = '$today'";
        $date_filter_sales = "DATE(created_at) = '$today'";
        $date_filter_agency = "purchase_date = '$today'";
        $date_filter_returns = "return_date = '$today'";
    } elseif ($period === 'yesterday') {
        $date_filter_patients = "DATE(created_at) = '$yesterday'";
        $date_filter_sales = "DATE(created_at) = '$yesterday'";
        $date_filter_agency = "purchase_date = '$yesterday'";
        $date_filter_returns = "return_date = '$yesterday'";
    } elseif ($period === 'weekly') {
        $date_filter_patients = "DATE(created_at) >= '$weekly_start'";
        $date_filter_sales = "DATE(created_at) >= '$weekly_start'";
        $date_filter_agency = "purchase_date >= '$weekly_start'";
        $date_filter_returns = "return_date >= '$weekly_start'";
    } elseif ($period === 'monthly') {
        $date_filter_patients = "strftime('%Y-%m', created_at) = '$this_month'";
        $date_filter_sales = "strftime('%Y-%m', created_at) = '$this_month'";
        $date_filter_agency = "strftime('%Y-%m', purchase_date) = '$this_month'";
        $date_filter_returns = "strftime('%Y-%m', return_date) = '$this_month'";
    } elseif ($period === 'last_month') {
        $date_filter_patients = "strftime('%Y-%m', created_at) = '$last_month'";
        $date_filter_sales = "strftime('%Y-%m', created_at) = '$last_month'";
        $date_filter_agency = "strftime('%Y-%m', purchase_date) = '$last_month'";
        $date_filter_returns = "strftime('%Y-%m', return_date) = '$last_month'";
    } elseif ($period === 'yearly') {
        $date_filter_patients = "strftime('%Y', created_at) = '$this_year'";
        $date_filter_sales = "strftime('%Y', created_at) = '$this_year'";
        $date_filter_agency = "strftime('%Y', purchase_date) = '$this_year'";
        $date_filter_returns = "strftime('%Y', return_date) = '$this_year'";
    } elseif ($period === 'custom' && $start_date && $end_date) {
        $date_filter_patients = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
        $date_filter_sales = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
        $date_filter_agency = "purchase_date BETWEEN '$start_date' AND '$end_date'";
        $date_filter_returns = "return_date BETWEEN '$start_date' AND '$end_date'";
    }

    $response = [];

    // Include original get_reports data but without LIMITs
    // Executive Summary & Financial Report
    $stmt = $conn->query("SELECT COUNT(*) as total_patients FROM patients WHERE $date_filter_patients");
    $total_patients = $stmt->fetchColumn() ?: 0;

    $stmt = $conn->query("SELECT 
        COUNT(*) as total_consultations,
        SUM(consultation_fee) as doctor_revenue,
        SUM(total_amount) as med_revenue,
        SUM(cost_amount) as med_cost,
        SUM(scan_fee) as scan_revenue,
        SUM(injection_cost) as inj_cost,
        SUM(iv_cost) as iv_cost,
        SUM(upt_cost) as upt_cost,
        SUM(cash_amount) as cash,
        SUM(gpay_amount) as gpay,
        SUM(paid_amount) as paid,
        SUM(balance_amount) as pending,
        SUM(discount_percent) as total_discount
        FROM prescriptions WHERE status='dispensed' AND $date_filter_patients");
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT id, medicines, injection_details, iv_details, upt_card FROM prescriptions WHERE status='dispensed' AND $date_filter_patients");
    $all_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $inj_revenue = 0; $iv_revenue = 0; $upt_revenue = 0; $total_medicine_revenue_only = 0;
    $medicine_stats = [];

    foreach ($all_prescriptions as $p) {
        $meds = json_decode($p['medicines'] ?: '[]', true);
        foreach ($meds as $m) {
            $name = $m['name'] ?? '';
            $qty = (float)($m['qty'] ?? 0) - (float)($m['returned_qty'] ?? 0);
            $amt = (float)($m['amount'] ?? 0) - (float)($m['returned_amount'] ?? 0);
            
            if ($name === 'Injection Fee') $inj_revenue += $amt;
            elseif ($name === 'IV Fluid Fee') $iv_revenue += $amt;
            elseif ($name === 'UPT Card Fee') $upt_revenue += $amt;
            else $total_medicine_revenue_only += $amt;

            if (!isset($medicine_stats[$name])) $medicine_stats[$name] = ['qty' => 0, 'revenue' => 0];
            $medicine_stats[$name]['qty'] += $qty;
            $medicine_stats[$name]['revenue'] += $amt;
        }
    }

    $doctor_revenue = (float)($pr['doctor_revenue'] ?? 0);
    $scan_revenue = (float)($pr['scan_revenue'] ?? 0);
    $medicine_revenue = $total_medicine_revenue_only + $inj_revenue + $iv_revenue;
    $treatment_revenue = $upt_revenue;

    $total_revenue = $doctor_revenue + $medicine_revenue + $scan_revenue + $treatment_revenue;
    $total_discount = (float)($pr['total_discount'] ?? 0);
    $total_revenue -= $total_discount;

    // Direct Sales
    $stmt = $conn->query("SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as direct_revenue,
        SUM(cost_amount) as direct_cost,
        SUM(cash_amount) as ds_cash,
        SUM(gpay_amount) as ds_gpay,
        SUM(paid_amount) as ds_paid,
        SUM(balance_amount) as ds_pending
        FROM direct_sales WHERE status='completed' AND $date_filter_sales");
    $ds = $stmt->fetch(PDO::FETCH_ASSOC);

    $direct_revenue = (float)($ds['direct_revenue'] ?? 0);
    $medicine_revenue += $direct_revenue;
    $total_revenue += $direct_revenue;

    // Agency Purchases (Expenses)
    $stmt = $conn->query("SELECT COUNT(*) as purchase_count, SUM(grand_total) as total_purchases FROM agency_purchases WHERE $date_filter_agency");
    $ap = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = (float)($ap['total_purchases'] ?? 0);

    // Inventory Value
    $stmt = $conn->query("SELECT COUNT(*) as count, SUM(stock * purchase_price) as inventory_value FROM inventory WHERE stock > 0");
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    $inventory_value = (float)($inv['inventory_value'] ?? 0);

    $stmt = $conn->query("SELECT COUNT(*) FROM inventory WHERE stock <= min_stock AND stock > 0");
    $low_stock = $stmt->fetchColumn() ?: 0;

    $stmt = $conn->query("SELECT COUNT(*) FROM inventory WHERE stock = 0");
    $out_of_stock = $stmt->fetchColumn() ?: 0;

    $stmt = $conn->query("SELECT COUNT(*) FROM agency_suppliers");
    $total_suppliers = $stmt->fetchColumn() ?: 0;

    $net_profit = $total_revenue - $total_expenses;
    $pending_amount = (float)($pr['pending'] ?? 0) + (float)($ds['ds_pending'] ?? 0);
    $collection_amount = (float)($pr['paid'] ?? 0) + (float)($ds['ds_paid'] ?? 0);

    $cash_collection = (float)($pr['cash'] ?? 0) + (float)($ds['ds_cash'] ?? 0);
    $gpay_collection = (float)($pr['gpay'] ?? 0) + (float)($ds['ds_gpay'] ?? 0);
    $phonepe_collection = (float)($pr['phonepe'] ?? 0) + (float)($ds['ds_phonepe'] ?? 0);
    $bank_collection = (float)($ds['ds_bank'] ?? 0);

    $response['executive'] = [
        'total_patients' => $total_patients,
        'total_consultations' => (int)($pr['total_consultations'] ?? 0),
        'direct_sales_count' => (int)($ds['total_sales'] ?? 0),
        'total_revenue' => $total_revenue,
        'doctor_revenue' => $doctor_revenue,
        'medicine_revenue' => $medicine_revenue,
        'treatment_revenue' => $treatment_revenue,
        'total_expenses' => $total_expenses,
        'net_profit' => $net_profit,
        'pending_amount' => $pending_amount,
        'collection_amount' => $collection_amount,
        'total_purchases' => $total_expenses,
        'total_suppliers' => $total_suppliers,
        'inventory_value' => $inventory_value,
        'low_stock_count' => $low_stock,
        'out_of_stock_count' => $out_of_stock
    ];

    $response['financial'] = [
        'Cash' => $cash_collection,
        'Bank Transfer' => $bank_collection
    ];

    $upi_collections = [];
    $get_acc_label = function($acc_name) {
        return trim($acc_name) ? trim($acc_name) : "Legacy UPI";
    };

    // For prescriptions
    $stmt_upi = $conn->query("SELECT p.upi_account, u.account_name, SUM(p.gpay_amount) as amount, SUM(p.phonepe_amount) as phonepe 
        FROM prescriptions p 
        LEFT JOIN upi_accounts u ON p.upi_account = u.short_name 
        WHERE p.status='dispensed' AND $date_filter_patients 
        GROUP BY p.upi_account, u.account_name");
    foreach ($stmt_upi->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $label = $get_acc_label($row['account_name'] ?? '');
        if ($row['amount'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['amount'];
        if ($row['phonepe'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['phonepe'];
    }

    // For direct sales
    $stmt_upi = $conn->query("SELECT p.upi_account, u.account_name, SUM(p.gpay_amount) as amount, SUM(p.phonepe_amount) as phonepe 
        FROM direct_sales p 
        LEFT JOIN upi_accounts u ON p.upi_account = u.short_name 
        WHERE p.status='completed' AND $date_filter_sales 
        GROUP BY p.upi_account, u.account_name");
    foreach ($stmt_upi->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $label = $get_acc_label($row['account_name'] ?? '');
        if ($row['amount'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['amount'];
        if ($row['phonepe'] > 0) $upi_collections[$label] = ($upi_collections[$label] ?? 0) + $row['phonepe'];
    }

    foreach ($upi_collections as $label => $amt) {
        if ($amt > 0) {
            $response['financial'][$label] = ($response['financial'][$label] ?? 0) + $amt;
        }
    }

    $stmt = $conn->query("SELECT doctor_name, doctor_type, COUNT(*) as p_count, SUM(consultation_fee) as doc_rev, SUM(total_amount) as med_rev FROM prescriptions WHERE status='dispensed' AND $date_filter_patients GROUP BY doctor_name, doctor_type");
    $response['doctors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    usort($medicine_stats, function($a, $b) { return $b['qty'] <=> $a['qty']; });
    $top_meds = [];
    $i = 0;
    foreach ($medicine_stats as $name => $stats) {
        if ($i++ >= 50) break; // Limit top meds in print to 50
        $top_meds[] = ['name' => $name, 'qty' => $stats['qty'], 'revenue' => $stats['revenue']];
    }
    $response['top_medicines'] = $top_meds;

    $date_filter_patients_join = str_replace("created_at", "p.created_at", $date_filter_patients);
    
    // NO LIMIT FOR PRINT
    $stmt = $conn->query("SELECT p.name, p.phone, p.doctor_name, p.created_at, pr.consultation_fee, pr.total_amount, pr.paid_amount, pr.balance_amount, pr.medicines 
        FROM patients p LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
        WHERE $date_filter_patients_join ORDER BY p.id ASC");
    $response['patients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // NO LIMIT FOR PRINT
    $stmt = $conn->query("SELECT customer_name, mobile_number, total_amount, paid_amount, balance_amount, created_at FROM direct_sales WHERE $date_filter_sales ORDER BY id ASC");
    $response['direct_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // AGENCY PURCHASES
    $stmt = $conn->query("SELECT a.invoice_number, a.purchase_date, a.payment_mode, a.grand_total, s.name as supplier_name 
        FROM agency_purchases a LEFT JOIN agency_suppliers s ON a.supplier_id = s.id 
        WHERE $date_filter_agency ORDER BY a.purchase_date ASC");
    $response['agency_purchases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // INVENTORY STOCK (ALL)
    $stmt = $conn->query("SELECT * FROM inventory");
    $response['inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Medicine Returns for the period
    $stmt = $conn->query("SELECT * FROM medicine_returns WHERE $date_filter_returns ORDER BY id DESC");
    $response['returns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Return Totals
    $total_returned_amount = 0;
    foreach ($response['returns'] as $r) {
        $total_returned_amount += (float)($r['return_amount'] ?? 0);
    }
    $response['executive']['total_returns'] = $total_returned_amount;
    
    // Parse patient medicines for cleaner frontend use
    foreach ($response['patients'] as &$p) {
        $meds_arr = [];
        $p_meds = json_decode($p['medicines'] ?: '[]', true);
        foreach ($p_meds as $m) {
            $name = $m['name'] ?? '';
            if ($name === 'Injection Fee' || $name === 'IV Fluid Fee' || $name === 'UPT Card Fee') continue;
            $meds_arr[] = $name . ' (' . ($m['qty'] ?? 1) . ')';
        }
        $p['parsed_medicines'] = implode(', ', $meds_arr);
    }
    unset($p);

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($action === 'export_csv') {
    $period = $_GET['period'] ?? 'today';
    $start_date = $_GET['start'] ?? '';
    $end_date = $_GET['end'] ?? '';
    
    $date_filter_patients = "1=1";
    $date_filter_sales = "1=1";
    $date_filter_agency = "1=1";
    
    if ($period === 'today') {
        $date_filter_patients = "DATE(created_at) = DATE('now', 'localtime')";
        $date_filter_sales = "DATE(created_at) = DATE('now', 'localtime')";
        $date_filter_agency = "purchase_date = strftime('%Y-%m-%d', 'now', 'localtime')";
    } elseif ($period === 'yesterday') {
        $date_filter_patients = "DATE(created_at) = DATE('now', '-1 day', 'localtime')";
        $date_filter_sales = "DATE(created_at) = DATE('now', '-1 day', 'localtime')";
        $date_filter_agency = "purchase_date = strftime('%Y-%m-%d', 'now', '-1 day', 'localtime')";
    } elseif ($period === 'weekly') {
        $date_filter_patients = "DATE(created_at) >= DATE('now', '-7 days', 'localtime')";
        $date_filter_sales = "DATE(created_at) >= DATE('now', '-7 days', 'localtime')";
        $date_filter_agency = "purchase_date >= strftime('%Y-%m-%d', 'now', '-7 days', 'localtime')";
    } elseif ($period === 'monthly') {
        $date_filter_patients = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'localtime')";
        $date_filter_sales = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'localtime')";
        $date_filter_agency = "strftime('%Y-%m', purchase_date) = strftime('%Y-%m', 'now', 'localtime')";
    } elseif ($period === 'last_month') {
        $date_filter_patients = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'start of month', '-1 month', 'localtime')";
        $date_filter_sales = "strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now', 'start of month', '-1 month', 'localtime')";
        $date_filter_agency = "strftime('%Y-%m', purchase_date) = strftime('%Y-%m', 'now', 'start of month', '-1 month', 'localtime')";
    } elseif ($period === 'yearly') {
        $date_filter_patients = "strftime('%Y', created_at) = strftime('%Y', 'now', 'localtime')";
        $date_filter_sales = "strftime('%Y', created_at) = strftime('%Y', 'now', 'localtime')";
        $date_filter_agency = "strftime('%Y', purchase_date) = strftime('%Y', 'now', 'localtime')";
    } elseif ($period === 'custom' && $start_date && $end_date) {
        $date_filter_patients = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
        $date_filter_sales = "DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
        $date_filter_agency = "purchase_date BETWEEN '$start_date' AND '$end_date'";
    }

    $filename = "Hospital_Full_Report_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    
    // PATIENTS
    fputcsv($out, ['--- PATIENTS REPORT ---']);
    fputcsv($out, ['Patient Name', 'Phone', 'Doctor', 'Visit Date', 'Consultation Fee', 'Medicine Bill', 'Total Bill', 'Status']);
    
    $date_filter_patients_join = str_replace("created_at", "p.created_at", $date_filter_patients);
    $stmt = $conn->query("SELECT p.name, p.phone, p.doctor_name, DATE(p.created_at), pr.consultation_fee, pr.total_amount, (pr.consultation_fee + pr.total_amount), pr.status 
        FROM patients p LEFT JOIN prescriptions pr ON p.id = pr.patient_id 
        WHERE $date_filter_patients_join ORDER BY p.id DESC");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
    fputcsv($out, []);
    
    // DIRECT SALES
    fputcsv($out, ['--- DIRECT MEDICINE SALES ---']);
    fputcsv($out, ['Customer Name', 'Phone', 'Total Amount', 'Paid Amount', 'Pending Amount', 'Date']);
    $stmt = $conn->query("SELECT customer_name, mobile_number, total_amount, paid_amount, balance_amount, DATE(created_at) FROM direct_sales WHERE $date_filter_sales ORDER BY id DESC");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
    fputcsv($out, []);
    
    // INVENTORY
    fputcsv($out, ['--- CURRENT INVENTORY ---']);
    fputcsv($out, ['Item Code', 'Name', 'Category', 'Batch', 'Stock', 'MRP', 'Purchase Price', 'Selling Price']);
    $stmt = $conn->query("SELECT item_code, name, category, batch_number, stock, mrp, purchase_price, selling_price FROM inventory ORDER BY name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
    fputcsv($out, []);

    // AGENCY PURCHASES
    fputcsv($out, ['--- AGENCY PURCHASES ---']);
    fputcsv($out, ['Invoice', 'Supplier ID', 'Purchase Date', 'Payment Mode', 'Sub Total', 'Discount', 'GST Total', 'Grand Total', 'Status']);
    $stmt = $conn->query("SELECT invoice_number, supplier_id, purchase_date, payment_mode, sub_total, discount_total, gst_total, grand_total, payment_status FROM agency_purchases WHERE $date_filter_agency ORDER BY id DESC");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { fputcsv($out, $row); }
    
    fclose($out);
    exit;
}

if ($action === 'backup') {
    // Generate full database backup or dump
    $filename = "Hospital_System_Backup_" . date('Y-m-d_H-i-s') . ".sql";
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Simplest backup: dump sqlite db contents
    // Or just provide the DB file for download directly
    readfile($db_path);
    exit;
}
