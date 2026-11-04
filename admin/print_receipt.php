<?php
// admin/print_receipt.php
// Printable Receipt for Believe Travel Consult

session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Security: Only logged-in admin can access
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page title for header
$pageTitle = 'Print Receipt';

// Get receipt ID from URL
$receipt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle receipt generation (create new receipt)
$message = '';
$error = '';

// Create new receipt from POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_receipt'])) {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'Cash');
    $items_data = json_decode($_POST['items_data'] ?? '[]', true);
    
    if (empty($client_name) || empty($items_data)) {
        $error = 'Client name and at least one item are required.';
    } else {
        // Calculate totals
        $total = 0;
        foreach ($items_data as &$item) {
            $item['qty'] = (int)($item['qty'] ?? 1);
            $item['price'] = (float)($item['price'] ?? 0);
            $total += $item['qty'] * $item['price'];
        }
        
        $paid = (float)($_POST['paid_amount'] ?? 0);
        $balance = max(0, $total - $paid);
        
        // Generate receipt number
        $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert into database
        $stmt = $pdo->prepare("
            INSERT INTO receipts (receipt_number, client_name, client_phone, client_email, 
                                 payment_method, items, total, paid, balance, date, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $items_json = json_encode($items_data);
        $created_by = $_SESSION['admin_id'] ?? 0;
        
        $stmt->execute([
            $receipt_number, $client_name, $client_phone, $client_email,
            $payment_method, $items_json, $total, $paid, $balance, $created_by
        ]);
        
        $new_id = $pdo->lastInsertId();
        $message = 'Receipt created successfully!';
        
        // Redirect to the new receipt
        header("Location: print_receipt.php?id=" . $new_id . "&success=1");
        exit;
    }
}

// Handle receipt deletion
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM receipts WHERE id = ?");
    $stmt->execute([$delete_id]);
    $message = 'Receipt deleted successfully!';
    header("Location: print_receipt.php?message=" . urlencode($message));
    exit;
}

// Fetch receipt data for viewing
$receipt = null;
$items = [];
$receipt_number = '';
$date = '';
$client_name = '';
$client_phone = '';
$client_email = '';
$payment_method = 'Cash';
$total = 0;
$paid = 0;
$balance = 0;

if ($receipt_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ?");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch();
    
    if (!$receipt) {
        $error = 'Receipt not found.';
    } else {
        $items = json_decode($receipt['items'], true) ?? [];
        $receipt_number = htmlspecialchars($receipt['receipt_number']);
        $date = date('d/m/Y', strtotime($receipt['date']));
        $client_name = htmlspecialchars($receipt['client_name']);
        $client_phone = htmlspecialchars($receipt['client_phone'] ?? '');
        $client_email = htmlspecialchars($receipt['client_email'] ?? '');
        $payment_method = htmlspecialchars($receipt['payment_method'] ?? 'Cash');
        $total = number_format($receipt['total'], 2);
        $paid = number_format($receipt['paid'], 2);
        $balance = number_format($receipt['balance'], 2);
    }
}

// Get recent receipts for dropdown/list
$recent_receipts = $pdo->query("
    SELECT id, receipt_number, client_name, total, date 
    FROM receipts 
    ORDER BY date DESC 
    LIMIT 10
")->fetchAll();

include 'admin_header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div data-aos="fade-right">
                <h1 class="fw-bold mb-2">
                    <i class="fas fa-file-invoice me-2 text-primary"></i>Print Receipt
                </h1>
                <p class="text-muted">Generate and manage receipts for your clients</p>
            </div>
            <div data-aos="fade-left">
                <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#newReceiptModal">
                    <i class="fas fa-plus me-2"></i>New Receipt
                </button>
                <?php if ($receipt_id > 0 && $receipt): ?>
                    <button class="btn btn-success rounded-pill" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <button class="btn btn-danger rounded-pill" onclick="deleteReceipt(<?= $receipt_id ?>)">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Messages -->
<?php if ($message): ?>
    <div class="alert alert-success rounded-4 d-flex align-items-center" data-aos="fade-up">
        <i class="fas fa-check-circle fa-2x me-3"></i>
        <div><?= htmlspecialchars($message) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 d-flex align-items-center" data-aos="fade-up">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div><?= htmlspecialchars($error) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Receipt Display -->
<?php if ($receipt_id > 0 && $receipt): ?>
    <div class="receipt-wrapper" data-aos="fade-up">
        <div class="receipt-container" id="receiptContent">
            <!-- Header -->
            <div class="receipt-header">
                <div class="text-center">
                    <img src="../assets/images/logo_new.png" alt="Believe Travel Consult" class="logo">
                    <h1 class="receipt-title">OFFICIAL RECEIPT</h1>
                    <div class="receipt-number">No: <?= $receipt_number ?></div>
                </div>
            </div>

            <!-- Body -->
            <div class="receipt-body">
                <div class="row mb-4">
                    <div class="col-7">
                        <p class="fw-bold mb-1">Received From:</p>
                        <p class="mb-1"><strong><?= $client_name ?></strong></p>
                        <p class="mb-1"><?= $client_phone ?></p>
                        <p class="mb-0"><?= $client_email ?></p>
                    </div>
                    <div class="col-5 text-end">
                        <p class="fw-bold mb-1">Date:</p>
                        <p class="mb-1"><?= $date ?></p>
                        <p class="fw-bold mb-1 mt-2">Payment Method:</p>
                        <p class="mb-0"><?= $payment_method ?></p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table receipt-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price (GHS)</th>
                                <th class="text-end">Total (GHS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): 
                                $item_total = $item['qty'] * $item['price'];
                            ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td class="text-center"><?= $item['qty'] ?></td>
                                <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                                <td class="text-end"><?= number_format($item_total, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total Amount</td>
                                <td class="text-end fw-bold">GHS <?= $total ?></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end">Amount Paid</td>
                                <td class="text-end">GHS <?= $paid ?></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="4" class="text-end fw-bold" style="font-size:1.1rem;">Balance Due</td>
                                <td class="text-end fw-bold" style="font-size:1.1rem; color:var(--primary-dark);">
                                    GHS <?= $balance ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="thanks text-center my-4">
                    <p class="fw-bold" style="color:var(--accent-green); font-size:1.2rem;">
                        <i class="fas fa-heart me-2"></i>THANK YOU FOR YOUR PAYMENT!<i class="fas fa-heart ms-2"></i>
                    </p>
                    <p class="text-muted">Your journey to success starts with us.</p>
                </div>

                <div class="signature text-end mt-4">
                    <div class="signature-line mx-0 ms-auto"></div>
                    <p class="fw-bold">Authorized Signature</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Receipts Sidebar -->
    <div class="row mt-4" data-aos="fade-up" data-aos-delay="100">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-history me-2"></i>Recent Receipts</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Client</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_receipts as $rr): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($rr['receipt_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($rr['client_name']) ?></td>
                                        <td>GHS <?= number_format($rr['total'], 2) ?></td>
                                        <td><?= date('d/m/Y', strtotime($rr['date'])) ?></td>
                                        <td>
                                            <a href="print_receipt.php?id=<?= $rr['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($receipt_id > 0 && !$receipt): ?>
    <div class="text-center py-5" data-aos="fade-up">
        <i class="fas fa-file-invoice fa-5x text-muted mb-4 opacity-25"></i>
        <h4 class="text-muted">Receipt not found</h4>
        <p class="text-muted">The receipt you're looking for doesn't exist or has been deleted.</p>
        <a href="print_receipt.php" class="btn btn-primary rounded-pill">
            <i class="fas fa-arrow-left me-2"></i>Go Back
        </a>
    </div>

<?php else: ?>
    <div class="text-center py-5" data-aos="fade-up">
        <i class="fas fa-file-invoice fa-5x text-muted mb-4 opacity-25"></i>
        <h4 class="text-muted">No Receipt Selected</h4>
        <p class="text-muted">Select an existing receipt or create a new one.</p>
        <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#newReceiptModal">
            <i class="fas fa-plus me-2"></i>Create New Receipt
        </button>
    </div>
    
    <!-- Show recent receipts -->
    <?php if (!empty($recent_receipts)): ?>
    <div class="row mt-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fas fa-history me-2"></i>Recent Receipts</h6>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Client</th>
                                    <th>Total</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_receipts as $rr): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($rr['receipt_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($rr['client_name']) ?></td>
                                        <td>GHS <?= number_format($rr['total'], 2) ?></td>
                                        <td><?= date('d/m/Y', strtotime($rr['date'])) ?></td>
                                        <td>
                                            <a href="print_receipt.php?id=<?= $rr['id'] ?>" class="btn btn-sm btn-primary rounded-pill">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- New Receipt Modal -->
<div class="modal fade" id="newReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2 text-primary"></i>Create New Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="receiptForm">
                <div class="modal-body">
                    <input type="hidden" name="create_receipt" value="1">
                    <input type="hidden" name="items_data" id="itemsData" value='[]'>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Client Name *</label>
                            <input type="text" name="client_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="client_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="client_email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Credit/Debit Card">Credit/Debit Card</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount Paid</label>
                            <input type="number" name="paid_amount" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="fas fa-list me-2"></i>Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick="addItemRow()">
                            <i class="fas fa-plus me-1"></i>Add Item
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="itemsTable">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th style="width:100px;">Qty</th>
                                    <th style="width:150px;">Price (GHS)</th>
                                    <th style="width:100px;">Total</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr>
                                    <td><input type="text" class="form-control form-control-sm item-desc" placeholder="Item description"></td>
                                    <td><input type="number" class="form-control form-control-sm item-qty" value="1" min="1"></td>
                                    <td><input type="number" class="form-control form-control-sm item-price" step="0.01" value="0"></td>
                                    <td><span class="item-total">0.00</span></td>
                                    <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)"><i class="fas fa-times"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill">
                        <i class="fas fa-save me-2"></i>Generate Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.receipt-wrapper {
    background: #f8f9fa;
    padding: 2rem;
    border-radius: 16px;
}

.receipt-container {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(3,55,84,0.15);
    max-width: 210mm;
    margin: 0 auto;
}

.receipt-header {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-light) 100%);
    color: white;
    padding: 25px 30px;
    position: relative;
}

.receipt-header .logo {
    max-width: 180px;
    margin-bottom: 10px;
    filter: brightness(0) invert(1);
}

.receipt-title {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 2px;
    margin: 0;
}

.receipt-number {
    font-size: 1.2rem;
    font-weight: 600;
    margin-top: 5px;
    opacity: 0.9;
}

.receipt-body {
    padding: 30px;
}

.receipt-table th {
    background: var(--primary-dark);
    color: white;
    font-weight: 600;
}

.receipt-table tfoot tr:last-child td {
    font-size: 1.1rem;
    font-weight: 700;
}

.thanks {
    border-top: 2px solid rgba(137,217,0,0.3);
    border-bottom: 2px solid rgba(137,217,0,0.3);
    padding: 20px 0;
}

.signature-line {
    width: 250px;
    border-bottom: 2px solid var(--primary-dark);
    margin-left: auto;
    margin-bottom: 10px;
}

/* Print Styles */
@media print {
    .no-print, .admin-topbar, .sidebar, .hamburger-toggle, .alert {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .receipt-wrapper {
        background: white;
        padding: 0;
    }
    
    .receipt-container {
        box-shadow: none;
        border-radius: 0;
    }
    
    .container-fluid {
        padding: 0 !important;
    }
    
    .receipt-table {
        page-break-inside: avoid;
    }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .receipt-header {
        padding: 15px;
    }
    
    .receipt-title {
        font-size: 1.5rem;
    }
    
    .receipt-body {
        padding: 15px;
    }
    
    .receipt-table {
        font-size: 0.9rem;
    }
}

/* Modal styles */
#itemsBody tr td {
    vertical-align: middle;
    padding: 5px;
}

#itemsBody .item-total {
    font-weight: 600;
    color: var(--primary-dark);
}
</style>

<script>
// Handle item rows in receipt form
let itemCounter = 1;

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="form-control form-control-sm item-desc" placeholder="Item description"></td>
        <td><input type="number" class="form-control form-control-sm item-qty" value="1" min="1"></td>
        <td><input type="number" class="form-control form-control-sm item-price" step="0.01" value="0"></td>
        <td><span class="item-total">0.00</span></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)"><i class="fas fa-times"></i></button></td>
    `;
    tbody.appendChild(row);
    
    // Add event listeners for calculations
    row.querySelectorAll('.item-qty, .item-price').forEach(input => {
        input.addEventListener('input', function() {
            updateItemTotal(this.closest('tr'));
        });
    });
}

function removeItemRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        updateAllTotals();
    } else {
        showToast('At least one item is required', 'warning');
    }
}

function updateItemTotal(row) {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const total = qty * price;
    row.querySelector('.item-total').textContent = total.toFixed(2);
}

function updateAllTotals() {
    document.querySelectorAll('#itemsBody tr').forEach(row => {
        updateItemTotal(row);
    });
}

// Auto-calculate on input
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-qty, .item-price').forEach(input => {
        input.addEventListener('input', function() {
            updateItemTotal(this.closest('tr'));
        });
    });
    
    // Form submit handler
    document.getElementById('receiptForm').addEventListener('submit', function(e) {
        const items = [];
        const rows = document.querySelectorAll('#itemsBody tr');
        
        rows.forEach(row => {
            const desc = row.querySelector('.item-desc').value.trim();
            const qty = parseInt(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            
            if (desc && qty > 0 && price > 0) {
                items.push({ description: desc, qty: qty, price: price });
            }
        });
        
        if (items.length === 0) {
            e.preventDefault();
            showToast('Please add at least one valid item', 'danger');
            return false;
        }
        
        document.getElementById('itemsData').value = JSON.stringify(items);
    });
});

function deleteReceipt(id) {
    if (confirm('Are you sure you want to delete this receipt permanently?')) {
        window.location.href = 'print_receipt.php?delete=' + id + '&confirm=1';
    }
}

// Toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.style.maxWidth = '400px';
    toast.innerHTML = `
        <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'check-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}
</script>

<?php include 'admin_footer.php'; ?>