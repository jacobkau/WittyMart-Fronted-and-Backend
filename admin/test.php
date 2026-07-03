<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/auth.php';

try {
    // Get all activities
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch activities error: ' . $e->getMessage());
    $activities = [];
}
?>

<!-- Display Activities -->
<div class="admin-card">
    <div class="card-header">
        <h2><i class="fas fa-history"></i> Recent Activities</h2>
        <a href="activity_logs.php" class="btn-link">View All</a>
    </div>
    <div class="card-body">
        <?php if (!empty($activities)): ?>
            <div class="activity-feed">
                <?php foreach ($activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <?php echo getActivityIcon($activity['action']); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">
                                <strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong>
                                <?php echo formatActivityMessage($activity); ?>
                            </div>
                            <div class="activity-time">
                                <i class="fas fa-clock"></i>
                                <?php echo timeAgo($activity['created_at']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-center" style="padding: 40px 0;">
                <i class="fas fa-history" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                No activities found.
            </p>
        <?php endif; ?>
    </div>
</div>

<style>
.activity-feed {
    max-height: 500px;
    overflow-y: auto;
}

.activity-feed::-webkit-scrollbar {
    width: 4px;
}

.activity-feed::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 10px;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
    transition: all 0.3s ease;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: rgba(5, 87, 60, 0.03);
    margin: 0 -15px;
    padding: 12px 15px;
    border-radius: 6px;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    color: #fff;
}

.activity-icon.login { background: #17a2b8; }
.activity-icon.logout { background: #6c757d; }
.activity-icon.add_admin { background: #28a745; }
.activity-icon.update_admin { background: #ffc107; color: #333; }
.activity-icon.delete_admin { background: #dc3545; }
.activity-icon.reset_password { background: #fd7e14; }
.activity-icon.failed_login { background: #dc3545; }
.activity-icon.add_product { background: #28a745; }
.activity-icon.update_product { background: #ffc107; color: #333; }
.activity-icon.delete_product { background: #dc3545; }
.activity-icon.default { background: #6c757d; }

.activity-content {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.activity-text {
    font-size: 14px;
    color: var(--text);
}

.activity-text strong {
    color: var(--primary);
}

.activity-time {
    font-size: 12px;
    color: var(--text-muted);
    white-space: nowrap;
}

.activity-time i {
    margin-right: 4px;
}
</style>
