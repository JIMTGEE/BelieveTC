<?php
// admin/print_receipt.php
// Printable Receipt for Believe Travel Consult

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Security: Only logged-in admin can access
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get receipt ID from URL
$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($receipt_id <= 0) {
    die("Invalid receipt ID.");
}

// Fetch receipt data
$stmt = $pdo->prepare("
    SELECT * FROM receipts 
    WHERE id = ?
");
$stmt->execute([$receipt_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Receipt not found.");
}

$items = json_decode($receipt['items'], true) ?? [];
$receipt_number = htmlspecialchars($receipt['receipt_number']);
$date = date('d/m/Y', strtotime($receipt['date']));
$client_name = htmlspecialchars($receipt['client_name']);
$client_phone = htmlspecialchars($receipt['client_phone']);
$client_email = htmlspecialchars($receipt['client_email']);
$payment_method = htmlspecialchars($receipt['payment_method'] ?? 'Cash');
$total = number_format($receipt['total'], 2);
$paid = number_format($receipt['paid'], 2);
$balance = number_format($receipt['balance'], 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $receipt_number ?> | Believe Travel Consult</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #033754;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }
        .receipt-container {
            width: 100%;
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            border: 2px solid #10b6d7;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            background: #ffffff;
        }
        .receipt-header {
            background: linear-gradient(135deg, #033754 0%, #10b6d7 100%);
            color: white;
            padding: 20px 30px;
            text-align: center;
            position: relative;
        }
        .receipt-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: #89d900;
            clip-path: polygon(0 0, 100% 0, 100% 100%, 0 50%);
        }
        .logo {
            max-width: 180px;
            margin-bottom: 10px;
        }
        .receipt-title {
            font-size: 2.2rem;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .receipt-number {
            font-size: 1.4rem;
            margin-top: 5px;
            font-weight: bold;
        }
        .receipt-body {
            padding: 30px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .info-label {
            font-weight: bold;
            color: #033754;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        .table th, .table td {
            border: 1px solid #10b6d7;
            padding: 12px;
            text-align: left;
        }
        .table th {
            background: #10b6d7;
            color: white;
            font-weight: bold;
        }
        .totals {
            text-align: right;
            font-size: 1.2rem;
            margin-top: 20px;
        }
        .total-line {
            border-top: 2px solid #89d900;
            padding-top: 10px;
            margin-top: 10px;
        }
        .thanks {
            text-align: center;
            font-size: 1.3rem;
            color: #89d900;
            font-weight: bold;
            margin: 40px 0 20px;
        }
        .signature {
            text-align: right;
            margin-top: 50px;
        }
        .signature-line {
            width: 250px;
            border-bottom: 2px solid #033754;
            margin-left: auto;
            margin-bottom: 10px;
        }
        .print-btn {
            display: block;
            margin: 30px auto;
            padding: 15px 40px;
            font-size: 1.3rem;
            background: #89d900;
            color: #033754;
            border: none;
            border-radius: 50px;
            cursor: pointer;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <!-- Header -->
    <div class="receipt-header">
        <img src="../assets/images/logo_new.png" alt="Believe Travel Consult" class="logo">
        <h1 class="receipt-title">OFFICIAL RECEIPT</h1>
        <div class="receipt-number">No: <?= $receipt_number ?></div>
    </div>

    <!-- Body -->
    <div class="receipt-body">
        <div class="info-row">
            <div>
                <span class="info-label">Received From:</span><br>
                <?= $client_name ?><br>
                <?= $client_phone ?><br>
                <?= $client_email ?>
            </div>
            <div style="text-align:right;">
                <span class="info-label">Date:</span><br>
                <?= $date ?><br><br>
                <span class="info-label">Payment Method:</span><br>
                <?= $payment_method ?>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price (GHS)</th>
                    <th>Total (GHS)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $index => $item): 
                    $item_total = $item['qty'] * $item['price'];
                ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td><?= $item['qty'] ?></td>
                    <td><?= number_format($item['price'], 2) ?></td>
                    <td><?= number_format($item_total, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div>Total Amount: <strong>GHS <?= $total ?></strong></div>
            <div>Amount Paid: <strong>GHS <?= $paid ?></strong></div>
            <div class="total-line">Balance Due: <strong style="color:#033754;">GHS <?= $balance ?></strong></div>
        </div>

        <div class="thanks">
            THANK YOU FOR YOUR PAYMENT!<br>
            Your journey to success starts with us.
        </div>

        <div class="signature">
            <div class="signature-line"></div>
            <strong>Authorized Signature</strong>
        </div>
    </div>
</div>

<button class="print-btn no-print" onclick="window.print()">🖨️ Print Receipt</button>

</body>
</html>