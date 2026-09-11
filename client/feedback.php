<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'Feedback & Reviews';

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer_id = $customer['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $rating = $_POST['rating'] ?? 0;
    $review = trim($_POST['review'] ?? '');

    if ($ticket_id && $rating > 0 && $rating <= 5) {
        // Verify ticket belongs to customer and is completed
        $stmt = $pdo->prepare("SELECT id, assigned_to FROM repair_tickets WHERE id = ? AND customer_id = ? AND status IN ('Completed', 'Delivered')");
        $stmt->execute([$ticket_id, $customer_id]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            // Check if already reviewed
            $stmt = $pdo->prepare("SELECT id FROM feedback WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO feedback (ticket_id, customer_id, technician_id, rating, review, is_visible, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$ticket_id, $customer_id, $ticket['assigned_to'], $rating, $review]);
                
                $_SESSION['flash_message'] = 'Thank you for your feedback!';
                $_SESSION['flash_type'] = 'success';
            }
        }
    }
    header('Location: feedback.php');
    exit;
}

// Find completed tickets without feedback
$stmt = $pdo->prepare("
    SELECT t.* 
    FROM repair_tickets t
    LEFT JOIN feedback f ON t.id = f.ticket_id
    WHERE t.customer_id = ? 
      AND t.status IN ('Completed', 'Delivered') 
      AND f.id IS NULL
    ORDER BY t.created_at DESC
");
$stmt->execute([$customer_id]);
$pending_feedback = $stmt->fetchAll();

// Get past feedback
$stmt = $pdo->prepare("
    SELECT f.*, t.device_brand, t.device_model, t.ticket_id as t_ref 
    FROM feedback f
    JOIN repair_tickets t ON f.ticket_id = t.id
    WHERE f.customer_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$customer_id]);
$past_feedback = $stmt->fetchAll();

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<style>
.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.star-rating input { display: none; }
.star-rating label {
    font-size: 2rem;
    color: #ccc;
    cursor: pointer;
    transition: color 0.2s;
    margin-right: 5px;
}
.star-rating input:checked ~ label,
.star-rating label:hover,
.star-rating label:hover ~ label {
    color: #ffc107;
}
.static-stars { color: #ffc107; }
.static-stars .far { color: #ccc; }
</style>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Feedback & Reviews</h4>
        </div>
        
        <?php if(!empty($pending_feedback)): ?>
            <div class="alert alert-info border-0 shadow-sm mb-4">
                <h6 class="alert-heading fw-bold"><i class="fas fa-info-circle me-2"></i>Pending Feedback</h6>
                <p class="mb-0">You have <?= count($pending_feedback) ?> completed repair(s) awaiting your review. We value your feedback!</p>
            </div>
            
            <div class="row g-4 mb-5">
                <?php foreach($pending_feedback as $ticket): ?>
                <div class="col-md-6 col-lg-6">
                    <div class="card border-0 shadow-sm h-100 border-top border-4 border-primary">
                        <div class="card-body">
                            <h6 class="fw-bold mb-1">Ticket #<?= htmlspecialchars($ticket['ticket_id']) ?></h6>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($ticket['device_brand'] . ' ' . $ticket['device_model']) ?></p>
                            
                            <form method="POST" action="">
                                <?= csrfField() ?>
                                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label d-block fw-medium">Rate the service:</label>
                                    <div class="star-rating">
                                        <input type="radio" id="star5_<?= $ticket['id'] ?>" name="rating" value="5" required/><label for="star5_<?= $ticket['id'] ?>" class="fas fa-star"></label>
                                        <input type="radio" id="star4_<?= $ticket['id'] ?>" name="rating" value="4"/><label for="star4_<?= $ticket['id'] ?>" class="fas fa-star"></label>
                                        <input type="radio" id="star3_<?= $ticket['id'] ?>" name="rating" value="3"/><label for="star3_<?= $ticket['id'] ?>" class="fas fa-star"></label>
                                        <input type="radio" id="star2_<?= $ticket['id'] ?>" name="rating" value="2"/><label for="star2_<?= $ticket['id'] ?>" class="fas fa-star"></label>
                                        <input type="radio" id="star1_<?= $ticket['id'] ?>" name="rating" value="1"/><label for="star1_<?= $ticket['id'] ?>" class="fas fa-star"></label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-medium">Your Review (Optional):</label>
                                    <textarea name="review" class="form-control" rows="2" placeholder="Tell us about your experience..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">Submit Review</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h5 class="fw-bold mb-3">Your Past Reviews</h5>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if(empty($past_feedback)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comment-slash fa-3x mb-3 text-light"></i>
                        <h5>No reviews found</h5>
                        <p>You haven't submitted any feedback yet.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($past_feedback as $feedback): ?>
                            <div class="list-group-item p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1 fw-bold">Ticket #<?= htmlspecialchars($feedback['t_ref']) ?> - <?= htmlspecialchars($feedback['device_brand'] . ' ' . $feedback['device_model']) ?></h6>
                                        <div class="static-stars fs-6 mb-2">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <i class="fa<?= $i <= $feedback['rating'] ? 's' : 'r' ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <span class="text-muted small"><?= date('M d, Y', strtotime($feedback['created_at'])) ?></span>
                                </div>
                                <?php if(!empty($feedback['review'])): ?>
                                    <p class="mb-0 text-dark">"<?= nl2br(htmlspecialchars($feedback['review'])) ?>"</p>
                                <?php else: ?>
                                    <p class="mb-0 text-muted fst-italic">No written review provided.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
