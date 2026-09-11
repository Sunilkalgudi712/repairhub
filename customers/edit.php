<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $alt_phone = trim($_POST['alt_phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $gst_number = trim($_POST['gst_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($name === '') {
            $errors[] = "Name is required.";
        }
        if ($phone === '') {
            $errors[] = "Phone number is required.";
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE customers SET 
                        name = :name, email = :email, phone = :phone, alt_phone = :alt_phone,
                        address = :address, city = :city, state = :state, pincode = :pincode,
                        gst_number = :gst_number, notes = :notes, updated_at = NOW()
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':alt_phone' => $alt_phone,
                    ':address' => $address,
                    ':city' => $city,
                    ':state' => $state,
                    ':pincode' => $pincode,
                    ':gst_number' => $gst_number,
                    ':notes' => $notes,
                    ':id' => $id
                ]);
                
                if (function_exists('logActivity')) {
                    logActivity($pdo, $_SESSION['user_id'], "Updated customer: $name");
                }

                $_SESSION['flash_message'] = "Customer updated successfully.";
                $_SESSION['flash_type'] = "success";
                header('Location: view.php?id=' . $id);
                exit;
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Edit Customer';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Edit Customer: <?= htmlspecialchars($customer['name']) ?></h2>
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Profile</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <h5 class="mb-3 text-primary">Basic Information</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $customer['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $customer['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($_POST['phone'] ?? $customer['phone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alternate Phone</label>
                        <input type="text" name="alt_phone" class="form-control" value="<?= htmlspecialchars($_POST['alt_phone'] ?? $customer['alt_phone']) ?>">
                    </div>
                </div>

                <h5 class="mb-3 text-primary">Address & Billing</h5>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? $customer['address']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($_POST['city'] ?? $customer['city']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($_POST['state'] ?? $customer['state']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pincode</label>
                        <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($_POST['pincode'] ?? $customer['pincode']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">GST Number</label>
                        <input type="text" name="gst_number" class="form-control" value="<?= htmlspecialchars($_POST['gst_number'] ?? $customer['gst_number']) ?>">
                    </div>
                </div>

                <h5 class="mb-3 text-primary">Additional Info</h5>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? $customer['notes']) ?></textarea>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Customer</button>
                </div>
            </form>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
