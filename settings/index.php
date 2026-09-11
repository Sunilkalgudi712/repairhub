<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash_message'] = "Invalid CSRF token.";
        $_SESSION['flash_type'] = "danger";
    } else {
        $allowedKeys = [
            'shop_name', 'shop_email', 'shop_phone', 'shop_address', 'shop_gst',
            'ticket_prefix', 'default_status',
            'invoice_prefix', 'tax_rate', 'invoice_terms',
            'theme_mode'
        ];

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            
            foreach ($_POST as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $stmt->execute([$key, $value, $value]);
                }
            }
            
            $pdo->commit();
            $_SESSION['flash_message'] = "Settings updated successfully.";
            $_SESSION['flash_type'] = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = "Error updating settings: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
        
        header('Location: index.php');
        exit;
    }
}

// Fetch current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Set defaults
$settings['ticket_prefix'] = $settings['ticket_prefix'] ?? 'RH';
$settings['invoice_prefix'] = $settings['invoice_prefix'] ?? 'INV';
$settings['tax_rate'] = $settings['tax_rate'] ?? '18';

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Settings</h2>
        </div>

        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="nav flex-column nav-pills" id="settings-tabs" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active text-start py-3 px-4 border-bottom rounded-0" data-bs-toggle="pill" data-bs-target="#shop-profile" type="button" role="tab"><i class="fas fa-store me-2"></i>Shop Profile</button>
                            <button class="nav-link text-start py-3 px-4 border-bottom rounded-0" data-bs-toggle="pill" data-bs-target="#ticket-settings" type="button" role="tab"><i class="fas fa-ticket-alt me-2"></i>Ticket Settings</button>
                            <button class="nav-link text-start py-3 px-4 border-bottom rounded-0" data-bs-toggle="pill" data-bs-target="#invoice-settings" type="button" role="tab"><i class="fas fa-file-invoice-dollar me-2"></i>Invoice Settings</button>
                            <button class="nav-link text-start py-3 px-4 rounded-0" data-bs-toggle="pill" data-bs-target="#theme-settings" type="button" role="tab"><i class="fas fa-palette me-2"></i>Theme</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="tab-content" id="settings-tabContent">
                                <!-- Shop Profile -->
                                <div class="tab-pane fade show active" id="shop-profile" role="tabpanel">
                                    <h4 class="mb-4">Shop Profile</h4>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Shop Name</label>
                                            <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($settings['shop_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Contact Email</label>
                                            <input type="email" name="shop_email" class="form-control" value="<?= htmlspecialchars($settings['shop_email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Phone Number</label>
                                            <input type="text" name="shop_phone" class="form-control" value="<?= htmlspecialchars($settings['shop_phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">GST Number</label>
                                            <input type="text" name="shop_gst" class="form-control" value="<?= htmlspecialchars($settings['shop_gst'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Shop Address</label>
                                        <textarea name="shop_address" class="form-control" rows="3"><?= htmlspecialchars($settings['shop_address'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <!-- Ticket Settings -->
                                <div class="tab-pane fade" id="ticket-settings" role="tabpanel">
                                    <h4 class="mb-4">Ticket Settings</h4>
                                    <div class="mb-3">
                                        <label class="form-label">Ticket ID Prefix</label>
                                        <input type="text" name="ticket_prefix" class="form-control" value="<?= htmlspecialchars($settings['ticket_prefix']) ?>">
                                        <div class="form-text">Example: 'RH' makes ticket IDs like 'RH-1001'.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Default New Ticket Status</label>
                                        <select name="default_status" class="form-select">
                                            <?php $ds = $settings['default_status'] ?? 'Pending'; ?>
                                            <option value="Pending" <?= $ds==='Pending'?'selected':'' ?>>Pending</option>
                                            <option value="In Progress" <?= $ds==='In Progress'?'selected':'' ?>>In Progress</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Invoice Settings -->
                                <div class="tab-pane fade" id="invoice-settings" role="tabpanel">
                                    <h4 class="mb-4">Invoice Settings</h4>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Invoice ID Prefix</label>
                                            <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($settings['invoice_prefix']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Default Tax Rate (%)</label>
                                            <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?= htmlspecialchars($settings['tax_rate']) ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Invoice Terms & Conditions</label>
                                        <textarea name="invoice_terms" class="form-control" rows="4"><?= htmlspecialchars($settings['invoice_terms'] ?? '') ?></textarea>
                                        <div class="form-text">This will be printed at the bottom of all invoices.</div>
                                    </div>
                                </div>

                                <!-- Theme -->
                                <div class="tab-pane fade" id="theme-settings" role="tabpanel">
                                    <h4 class="mb-4">Theme & Appearance</h4>
                                    <div class="mb-3">
                                        <label class="form-label d-block">Interface Mode</label>
                                        <?php $tm = $settings['theme_mode'] ?? 'light'; ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="theme_mode" id="themeLight" value="light" <?= $tm==='light'?'checked':'' ?>>
                                            <label class="form-check-label" for="themeLight"><i class="fas fa-sun me-1"></i> Light Mode</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="theme_mode" id="themeDark" value="dark" <?= $tm==='dark'?'checked':'' ?>>
                                            <label class="form-check-label" for="themeDark"><i class="fas fa-moon me-1"></i> Dark Mode</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save All Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
