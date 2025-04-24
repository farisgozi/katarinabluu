<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is waiter
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'waiter') {
    header("Location: ../../pages/auth/login.php");
    exit();
}

// Check session timeout
check_session_timeout();

// Get user data
$user = $_SESSION['user'];

// Include sidebar
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Order - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>

    <div class="content-wrapper">
        <style>
            .content-wrapper {
                margin-left: var(--sidebar-width);
                padding: var(--content-padding);
                padding-top: var(--content-padding-top);
                min-height: 100vh;
                transition: all var(--transition-speed) var(--transition-type);
                position: relative;
                z-index: 1;
            }
            
            .content-wrapper.shifted {
                margin-left: 0;
            }
            
            .content-wrapper.collapsed-sidebar {
                margin-left: var(--sidebar-collapsed-width);
            }
            
            @media (max-width: 768px) {
                .content-wrapper {
                    margin-left: 0;
                    padding: 1rem;
                    padding-top: var(--content-padding-top);
                }
            }
        </style>

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$order_id = $_GET['id'];

// Get order information
$order_query = "SELECT p.*, m.id 
FROM pesanan p 
JOIN meja m ON p.meja_id = m.id 
WHERE p.idpesanan = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

// Check if order exists
if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Calculate total amount
    $total_query = "SELECT SUM(p.jumlah * m.Harga) as total FROM pesanan p JOIN menu m ON p.idmenu = m.idmenu WHERE p.idpesanan = ?";
    $stmt = $conn->prepare($total_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $total_result = $stmt->get_result()->fetch_assoc();
    $total_amount = $total_result['total'];

    // Create transaction record to mark order as completed
    $create_transaction_query = "INSERT INTO transaksi (idpesanan, total, bayar, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($create_transaction_query);
    $stmt->bind_param("idd", $order_id, $total_amount, $total_amount);
    $stmt->execute();

    // Update table status
    $update_table_query = "UPDATE meja SET status = 'kosong' WHERE id = ?";
    $stmt = $conn->prepare($update_table_query);
    $stmt->bind_param("i", $order['id']);
    $stmt->execute();

    $conn->commit();
    header("Location: dashboard.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    die("Error completing order: " . $e->getMessage());
}