<?php
require_once 'includes/config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Guest';
$userEmail = $_SESSION['user_email'] ?? '';

// Handle testimonial submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_testimonial'])) {
    if (!$isLoggedIn) {
        $message = 'You must be logged in to submit a testimonial.';
        $messageType = 'error';
    } else {
        $content = sanitize($_POST['content'] ?? '');
        $rating = intval($_POST['rating'] ?? 5);
        
        if (empty($content)) {
            $message = 'Please write your testimonial.';
            $messageType = 'error';
        } else {
            try {
                // Check if user already submitted a testimonial
                $stmt = $pdo->prepare("SELECT id FROM testimonials WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $message = 'You have already submitted a testimonial. Thank you!';
                    $messageType = 'warning';
                } else {
                    // Insert testimonial
                    $stmt = $pdo->prepare("
                        INSERT INTO testimonials (user_id, customer_name, content, rating, status) 
                        VALUES (?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([$_SESSION['user_id'], $userName, $content, $rating]);
                    
                    $message = 'Thank you for your testimonial! It will be reviewed and published soon.';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                error_log('Testimonial error: ' . $e->getMessage());
                $message = 'Failed to submit testimonial. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Fetch existing testimonials
try {
    $stmt = $pdo->prepare("
        SELECT * FROM testimonials 
        WHERE status = 'active' 
        ORDER BY display_order ASC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Get testimonials error: ' . $e->getMessage());
    $testimonials = [];
}

// Check if user already submitted
$hasSubmitted = false;
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM testimonials WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $hasSubmitted = $stmt->fetch() !== false;
    } catch (PDOException $e) {
        // Ignore
    }
}

function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star" style="color: #ffc107;"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color: #ddd;"></i>';
        }
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials - WittyMart</title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .testimonials-page {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .testimonial-form-container {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid #e9ecef;
        }
        
        .testimonial-form-container h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .testimonial-form-container h2 i {
            color: #05573c;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #05573c;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #05573c;
        }
        
        .star-rating {
            display: flex;
            gap: 5px;
            font-size: 30px;
            cursor: pointer;
        }
        
        .star-rating i {
            color: #ddd;
            transition: color 0.3s ease;
        }
        
        .star-rating i.active {
            color: #ffc107;
        }
        
        .star-rating i:hover {
            transform: scale(1.1);
        }
        
        .btn-submit {
            background: #05573c;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover:not(:disabled) {
            background: #03402c;
            transform: translateY(-2px);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-submit i {
            margin-right: 8px;
        }
        
        .testimonials-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .testimonial-item {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #05573c;
            transition: transform 0.3s ease;
        }
        
        .testimonial-item:hover {
            transform: translateY(-5px);
        }
        
        .testimonial-item .stars {
            margin-bottom: 10px;
        }
        
        .testimonial-item .stars i {
            font-size: 16px;
        }
        
        .testimonial-item .content {
            font-size: 15px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 15px;
            font-style: italic;
        }
        
        .testimonial-item .author {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .testimonial-item .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #05573c;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        
        .testimonial-item .author-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .testimonial-item .author-date {
            font-size: 12px;
            color: #999;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 60px;
            display: block;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .message {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        
        .message i {
            margin-right: 8px;
        }
        
        .login-prompt {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .login-prompt i {
            font-size: 48px;
            color: #ccc;
            display: block;
            margin-bottom: 15px;
        }
        
        .login-prompt a {
            color: #05573c;
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
        }
        
        /* Dark mode */
        body.dark-mode .testimonial-form-container {
            background: #1a1a2e;
            border-color: #2a2a3e;
        }
        
        body.dark-mode .testimonial-form-container h2 {
            color: #eee;
        }
        
        body.dark-mode .form-group label {
            color: #bbb;
        }
        
        body.dark-mode .form-group textarea {
            background: #2a2a3e;
            border-color: #3a3a5e;
            color: #eee;
        }
        
        body.dark-mode .form-group textarea:focus {
            border-color: #05573c;
        }
        
        body.dark-mode .testimonial-item {
            background: #1a1a2e;
        }
        
        body.dark-mode .testimonial-item .content {
            color: #bbb;
        }
        
        body.dark-mode .testimonial-item .author-name {
            color: #eee;
        }
        
        body.dark-mode .login-prompt {
            background: #1a1a2e;
        }
        
        @media (max-width: 768px) {
            .testimonials-list {
                grid-template-columns: 1fr;
            }
            
            .testimonial-form-container {
                padding: 20px;
            }
            
            .star-rating {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>
    <?php include "sidebar.php"; ?>

    <main>
        <div class="testimonials-page">
            <h1><i class="fas fa-comment-dots" style="color:#05573c;"></i> Customer <span>Testimonials</span></h1>
            <p style="color: #888; margin-bottom: 30px;">See what our customers are saying about WittyMart</p>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Testimonial Form -->
            <?php if ($isLoggedIn): ?>
                <?php if ($hasSubmitted): ?>
                    <div class="message warning">
                        <i class="fas fa-check-circle"></i> You have already submitted a testimonial. Thank you for your feedback!
                    </div>
                <?php else: ?>
                    <div class="testimonial-form-container">
                        <h2><i class="fas fa-pen"></i> Share Your Experience</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label><i class="fas fa-star"></i> Your Rating</label>
                                <div class="star-rating" id="starRating">
                                    <i class="fas fa-star" data-value="1"></i>
                                    <i class="fas fa-star" data-value="2"></i>
                                    <i class="fas fa-star" data-value="3"></i>
                                    <i class="fas fa-star" data-value="4"></i>
                                    <i class="fas fa-star" data-value="5"></i>
                                </div>
                                <input type="hidden" name="rating" id="ratingInput" value="5">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-comment"></i> Your Testimonial</label>
                                <textarea name="content" placeholder="Share your experience with WittyMart... What did you love? How was the service?" required></textarea>
                            </div>
                            
                            <button type="submit" name="submit_testimonial" class="btn-submit">
                                <i class="fas fa-paper-plane"></i> Submit Testimonial
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="login-prompt">
                    <i class="fas fa-lock"></i>
                    <h3>Please Login to Submit a Testimonial</h3>
                    <p>Share your experience with our community!</p>
                    <a href="home.php">Login / Register</a>
                </div>
            <?php endif; ?>
            
            <!-- Existing Testimonials -->
            <h2 style="margin-top: 40px;"><i class="fas fa-comments" style="color:#05573c;"></i> What Our Customers Say</h2>
            
            <?php if (!empty($testimonials)): ?>
                <div class="testimonials-list">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="testimonial-item">
                            <div class="stars">
                                <?php echo renderStars($testimonial['rating'] ?? 5); ?>
                            </div>
                            <div class="content">"<?php echo htmlspecialchars($testimonial['content']); ?>"</div>
                            <div class="author">
                                <div class="avatar">
                                    <?php 
                                    $name = $testimonial['customer_name'];
                                    $initials = '';
                                    $words = explode(' ', $name);
                                    foreach ($words as $word) {
                                        $initials .= strtoupper(substr($word, 0, 1));
                                    }
                                    echo substr($initials, 0, 2);
                                    ?>
                                </div>
                                <div>
                                    <div class="author-name"><?php echo htmlspecialchars($testimonial['customer_name']); ?></div>
                                    <div class="author-date"><?php echo date('M d, Y', strtotime($testimonial['created_at'] ?? 'now')); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-dots"></i>
                    <h3>No Testimonials Yet</h3>
                    <p>Be the first to share your experience with WittyMart!</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include "footer.php"; ?>
    
    <script>
        // ============================================
        // STAR RATING
        // ============================================
        const stars = document.querySelectorAll('.star-rating i');
        const ratingInput = document.getElementById('ratingInput');
        let selectedRating = 5;
        
        stars.forEach(star => {
            star.addEventListener('mouseenter', function() {
                const value = parseInt(this.dataset.value);
                highlightStars(value);
            });
            
            star.addEventListener('mouseleave', function() {
                highlightStars(selectedRating);
            });
            
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.dataset.value);
                ratingInput.value = selectedRating;
                highlightStars(selectedRating);
            });
        });
        
        function highlightStars(value) {
            stars.forEach(star => {
                const starValue = parseInt(star.dataset.value);
                if (starValue <= value) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }
        
        // Initialize with 5 stars
        highlightStars(5);
    </script>
</body>
</html>
