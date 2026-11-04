<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Assume database has been updated with ALTER TABLE offers ADD currency VARCHAR(3) DEFAULT 'GHS';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            $title = sanitize($_POST['title']);
            $location = sanitize($_POST['location']);
            $duration = sanitize($_POST['duration']);
            $description = $_POST['description']; // Allow HTML
            $price = (float)$_POST['price'];
            $currency = sanitize($_POST['currency']);
            $featured = isset($_POST['featured']) ? 1 : 0;
            
            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $image = uploadImage($_FILES['image'], '../assets/images/');
                if (!$image) {
                    $error = "Failed to upload image.";
                }
            } elseif (isset($_POST['existing_image'])) {
                $image = $_POST['existing_image'];
            }
            
            if (!isset($error)) {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO offers (title, location, duration, description, price, currency, image, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $location, $duration, $description, $price, $currency, $image, $featured]);
                    $success = "Offer added successfully.";
                } else {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE offers SET title=?, location=?, duration=?, description=?, price=?, currency=?, image=?, featured=? WHERE id=?");
                    $stmt->execute([$title, $location, $duration, $description, $price, $currency, $image, $featured, $id]);
                    $success = "Offer updated successfully.";
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("SELECT image FROM offers WHERE id=?");
            $stmt->execute([$id]);
            $offer = $stmt->fetch();
            if ($offer && $offer['image']) {
                unlink('../assets/images/' . $offer['image']);
            }
            $stmt = $pdo->prepare("DELETE FROM offers WHERE id=?");
            $stmt->execute([$id]);
            $success = "Offer deleted successfully.";
        }
    }
}

// Fetch all offers
$stmt = $pdo->query("SELECT * FROM offers ORDER BY created_at DESC");
$offers = $stmt->fetchAll();

// Fetch single offer for edit
$editOffer = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM offers WHERE id=?");
    $stmt->execute([$id]);
    $editOffer = $stmt->fetch();
    if (!isset($editOffer['currency'])) {
        $editOffer['currency'] = 'GHS';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Offers | Believe Travel Consult</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #033754;
            --primary-light: #10b6d7;
            --accent: #89d900;
            --white: #ffffff;
            --text-dark: #033754;
            --bg-light: #f8f9fa;
        }
        body {
            background: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        .sidebar {
            background: var(--white);
            color: var(--primary-dark);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            padding: 2.5rem 1.5rem;
            box-shadow: 4px 0 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-right: 1px solid #dee2e6;
        }
        .sidebar:hover {
            box-shadow: 4px 0 25px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: var(--primary-dark);
            padding: 1.2rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            background: var(--primary-light);
            color: var(--white);
        }
        .sidebar .nav-link.active {
            background: var(--accent);
            color: var(--primary-dark);
        }
        .main-content {
            margin-left: 280px;
            padding: 4rem 3rem;
        }
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.4s, box-shadow 0.4s;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary-light), var(--primary-dark));
            color: var(--white);
            font-weight: 600;
            padding: 1.8rem;
            border-bottom: none;
        }
        .btn-primary {
            background: var(--accent);
            border: none;
            color: var(--primary-dark);
            font-weight: 600;
            padding: 0.8rem 1.8rem;
            border-radius: 50px;
            transition: all 0.4s;
        }
        .btn-primary:hover {
            background: var(--primary-light);
            color: var(--white);
            box-shadow: 0 5px 15px rgba(16,182,215,0.4);
        }
        .btn-danger {
            background: #ff4d4f;
            border: none;
            color: var(--white);
            border-radius: 50px;
            padding: 0.6rem 1.2rem;
            transition: all 0.4s;
        }
        .btn-danger:hover {
            background: #f52225;
            box-shadow: 0 5px 15px rgba(255,77,79,0.4);
        }
        .btn-secondary {
            background: var(--primary-dark);
            border: none;
            color: var(--white);
            border-radius: 50px;
            padding: 0.8rem 1.8rem;
        }
        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .table thead {
            background: var(--primary-dark);
            color: var(--white);
        }
        .table tbody tr {
            transition: background 0.4s;
        }
        .table tbody tr:hover {
            background: rgba(137,217,0,0.1);
        }
        .form-control {
            border-radius: 12px;
            border: 1px solid #ced4da;
            padding: 0.8rem;
            transition: all 0.4s;
        }
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.25rem rgba(137,217,0,0.25);
        }
        .form-select {
            border-radius: 12px;
            padding: 0.8rem;
        }
        .form-check-input:checked {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
        .alert {
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .hover-scale {
            transition: transform 0.4s;
        }
        .hover-scale:hover {
            transform: scale(1.08);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeinup {
            animation: fadeInUp 0.8s ease-out;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column">
        <div class="text-center mb-5">
            <img src="../assets/images/logo_new.png" alt="Logo" style="height: 90px; filter: drop-shadow(0 3px 10px rgba(0,0,0,0.2));">
            <h4 class="mt-3 fw-bold">Admin Dashboard</h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt me-3"></i> Dashboard</a></li>
            <li class="nav-item"><a href="offers.php" class="nav-link active"><i class="fas fa-briefcase me-3"></i> Manage Offers</a></li>
            <li class="nav-item"><a href="blog.php" class="nav-link"><i class="fas fa-blog me-3"></i> Manage Blog</a></li>
            <li class="nav-item"><a href="messages.php" class="nav-link"><i class="fas fa-envelope me-3"></i> Messages</a></li>
            <li class="nav-item mt-auto"><a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt me-3"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="mb-5 fw-bold" style="color: var(--primary-dark);" data-aos="fade-down">Manage Job Offers</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success animate-fadeinup" data-aos="fade-up"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger animate-fadeinup" data-aos="fade-up"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <div class="card mb-5" data-aos="zoom-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> <?php echo $editOffer ? 'Edit Offer' : 'Add New Offer'; ?></h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $editOffer ? 'edit' : 'add'; ?>">
                    <?php if ($editOffer): ?>
                        <input type="hidden" name="id" value="<?php echo $editOffer['id']; ?>">
                        <input type="hidden" name="existing_image" value="<?php echo $editOffer['image']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Title</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-heading text-muted"></i></span>
                                <input type="text" name="title" class="form-control" value="<?php echo $editOffer ? htmlspecialchars($editOffer['title']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Location</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                <input type="text" name="location" class="form-control" value="<?php echo $editOffer ? htmlspecialchars($editOffer['location']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Duration/Salary Info</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-clock text-muted"></i></span>
                                <input type="text" name="duration" class="form-control" value="<?php echo $editOffer ? htmlspecialchars($editOffer['duration']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label fw-bold">Currency</label>
                            <select name="currency" id="currency" class="form-select">
                                <option value="GHS" <?php echo ($editOffer && $editOffer['currency'] === 'GHS') ? 'selected' : ''; ?>>GHS (₵)</option>
                                <option value="AED" <?php echo ($editOffer && $editOffer['currency'] === 'AED') ? 'selected' : ''; ?>>AED (د.إ)</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-4">
                            <label class="form-label fw-bold">Price (Service Fee)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="price" id="price" class="form-control" value="<?php echo $editOffer ? $editOffer['price'] : ''; ?>" required>
                            </div>
                            <small class="form-text text-muted">Converted value: <span id="converted-price"></span></small>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Description (HTML allowed)</label>
                        <textarea name="description" class="form-control" rows="10"><?php echo $editOffer ? $editOffer['description'] : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if ($editOffer && $editOffer['image']): ?>
                            <small class="d-block mt-2 text-muted">Current Image: <img src="../assets/images/<?php echo $editOffer['image']; ?>" alt="Current" style="height: 60px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input type="checkbox" name="featured" class="form-check-input" <?php echo $editOffer && $editOffer['featured'] ? 'checked' : ''; ?> id="featured">
                        <label class="form-check-label" for="featured">Featured (Urgent Hiring)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary me-3"><i class="fas fa-save me-2"></i> Save Offer</button>
                    <?php if ($editOffer): ?>
                        <a href="offers.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i> Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Offers List -->
        <div class="card" data-aos="zoom-in">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i> Existing Offers</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Featured</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offers as $key => $offer): ?>
                                <tr data-aos="fade-up" data-aos-delay="<?php echo $key * 100; ?>">
                                    <td><?php echo $offer['id']; ?></td>
                                    <td><?php echo htmlspecialchars($offer['title']); ?></td>
                                    <td><?php echo htmlspecialchars($offer['location']); ?></td>
                                    <td><?php echo ($offer['currency'] ?? 'GHS') . ' ' . number_format($offer['price'], 2); ?></td>
                                    <td><?php echo $offer['featured'] ? '<span class="badge bg-success rounded-pill">Yes</span>' : '<span class="badge bg-secondary rounded-pill">No</span>'; ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $offer['id']; ?>" class="btn btn-sm btn-primary me-1 hover-scale"><i class="fas fa-edit"></i></a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $offer['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger hover-scale" onclick="return confirm('Are you sure you want to delete this offer?');"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <a href="dashboard.php" class="btn btn-secondary me-3"><i class="fas fa-arrow-left me-2"></i> Back to Dashboard</a>
        </div>
    </div>
    
    <script src="../assets/js/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });
        
        const AED_TO_GHS = 3.4305; // Updated exchange rate for December 22, 2025
        
        function updateConvertedPrice() {
            const currency = $('#currency').val();
            const price = parseFloat($('#price').val()) || 0;
            let converted = 0;
            let convertedCurrency = '';
            
            if (currency === 'GHS') {
                converted = price / AED_TO_GHS;
                convertedCurrency = 'AED';
            } else {
                converted = price * AED_TO_GHS;
                convertedCurrency = 'GHS';
            }
            
            $('#converted-price').text(converted.toFixed(2) + ' ' + convertedCurrency);
        }
        
        $('#currency').data('prev', $('#currency').val());
        
        $('#currency').on('change', function() {
            const prev = $(this).data('prev');
            const current = $(this).val();
            let price = parseFloat($('#price').val()) || 0;
            
            if (prev !== current && price > 0) {
                if (prev === 'GHS' && current === 'AED') {
                    price = price / AED_TO_GHS;
                } else if (prev === 'AED' && current === 'GHS') {
                    price = price * AED_TO_GHS;
                }
                $('#price').val(price.toFixed(2));
            }
            
            $(this).data('prev', current);
            updateConvertedPrice();
        });
        
        $('#price').on('input', updateConvertedPrice);
        
        // Initial update
        updateConvertedPrice();
    </script>
</body>
</html>