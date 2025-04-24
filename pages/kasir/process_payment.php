<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is kasir
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'kasir') {
    header("Location: ../../pages/auth/login.php");
    exit();
}

// Check session timeout
check_session_timeout();

// Get user data
$user = $_SESSION['user'];

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$order_id = $_GET['id'];

// Get order information and all related orders from the same table
$order_query = "SELECT p.*, m.Nomeja, m.id as meja_id
FROM pesanan p 
JOIN meja m ON p.meja_id = m.id 
LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan 
WHERE p.idpesanan = ? AND t.idtransaksi IS NULL";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();
$stmt->close();

// Check if order exists and is ready for payment
if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Get all unpaid orders from the same table
$table_orders_query = "SELECT p.idpesanan 
FROM pesanan p 
LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan 
WHERE p.meja_id = ? AND t.idtransaksi IS NULL";
$stmt = $conn->prepare($table_orders_query);
$stmt->bind_param("i", $order['meja_id']);
$stmt->execute();
$table_orders_result = $stmt->get_result();
$order_ids = [];
while ($row = $table_orders_result->fetch_assoc()) {
    $order_ids[] = $row['idpesanan'];
}
$stmt->close();

// Get all order items from the table
$items_query = "SELECT p.*, m.Namamenu as nama, m.Harga as harga, (p.jumlah * m.Harga) as subtotal 
FROM pesanan p 
JOIN menu m ON p.idmenu = m.idmenu 
WHERE p.idpesanan IN (" . implode(',', $order_ids) . ")";
$stmt = $conn->prepare($items_query);
$stmt->execute();
$items_result = $stmt->get_result();

// Calculate total
$total = 0;
while ($item = $items_result->fetch_assoc()) {
    $total += $item['subtotal'];
}
$items_result->data_seek(0); // Reset result pointer
$stmt->close();

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_amount = clean_input($_POST['payment_amount']);

    if ($payment_amount < $total) {
        $error = "Payment amount must be at least Rp " . number_format($total, 0, ',', '.');
    } else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // Create transaction records for all orders from the table
            foreach ($order_ids as $current_order_id) {
                // Get the total for this specific order
                $order_total_query = "SELECT SUM(p.jumlah * m.Harga) as order_total 
                    FROM pesanan p 
                    JOIN menu m ON p.idmenu = m.idmenu 
                    WHERE p.idpesanan = ?";
                $stmt = $conn->prepare($order_total_query);
                $stmt->bind_param("i", $current_order_id);
                $stmt->execute();
                $order_total_result = $stmt->get_result();
                $order_total = $order_total_result->fetch_assoc()['order_total'];

                // Create transaction record for this order
                $create_transaction_query = "INSERT INTO transaksi (idpesanan, total, bayar, created_at) VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($create_transaction_query);
                $stmt->bind_param("idd", $current_order_id, $order_total, $payment_amount);
                $stmt->execute();
            }
            
            // Update table status
            $update_table_query = "UPDATE meja SET status = 'kosong' WHERE id = ?";
            $stmt = $conn->prepare($update_table_query);
            $stmt->bind_param("i", $order['meja_id']);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to print receipt
            header("Location: print_receipt.php?id=" . $order_id);
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Error processing payment: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Payment - Kasir Resto</title>
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

    <div class="container py-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title mb-4">Process Payment</h2>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h5>Order Details</h5>
                            <p class="mb-1">Table: <?php echo htmlspecialchars($order['Nomeja']); ?></p>
                            <p class="mb-1">Order Code: <?php echo htmlspecialchars($order['kode_pesanan']); ?></p>
                        </div>

                        <div class="mb-4">
                            <h5>Items</h5>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $items_result->data_seek(0);
                                    while ($item = $items_result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                            <td class="text-end"><?php echo $item['jumlah']; ?></td>
                                            <td class="text-end">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                            <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <tr class="table-light">
                                        <td colspan="3" class="text-end"><strong>Total</strong></td>
                                        <td class="text-end"><strong>Rp <?php echo number_format($total, 0, ',', '.'); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="payment_amount" class="form-label">Payment Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount" required min="<?php echo $total; ?>">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Process Payment</button>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>