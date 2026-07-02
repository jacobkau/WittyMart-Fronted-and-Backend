<header class="admin-top-header">
    <div class="header-left">
        <h1><?= htmlspecialchars($page_title) ?></h1>
    </div>

    <div class="header-right">
        <a href="profile.php" class="admin-user">
            <i class="fas fa-user-circle"></i>

            <div class="user-details">
                <span class="user-name">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'Administrator') ?>
                </span>
                <span class="user-badge">Administrator</span>
            </div>
        </a>
    </div>
</header>
<style>
    .admin-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: var(--white);
    border-radius: 8px;
    box-shadow: var(--shadow);
    text-decoration: none;
    color: inherit;
    transition: all .3s ease;
}

.admin-user:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,.12);
}

.admin-user i {
    font-size: 32px;
    color: var(--primary);
}

.user-details {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.user-name {
    font-weight: 600;
    color: var(--text);
}

.user-badge {
    font-size: 11px;
    background: var(--primary);
    color: #fff;
    padding: 2px 8px;
    border-radius: 20px;
    width: fit-content;
    margin-top: 3px;
}
</style>
