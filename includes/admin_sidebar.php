<?php
// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Toggle Button for Mobile -->
<button id="sidebarToggle" class="sidebar-toggle">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>MCQ System</h2>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-section">
            <h3>Main Menu</h3>
            <a href="index.php" class="menu-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="create_quiz.php" class="menu-item <?php echo $current_page === 'create_quiz.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Create Quiz</span>
            </a>
            <a href="manage_quizzes.php" class="menu-item <?php echo $current_page === 'manage_quizzes.php' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>Manage Quizzes</span>
            </a>
        </div>

        <div class="menu-section">
            <h3>Results & Users</h3>
            <a href="view_results.php" class="menu-item <?php echo $current_page === 'view_results.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>View Results</span>
            </a>
        </div>

        <div class="menu-section">
            <h3>System</h3>
            <a href="settings.php" class="menu-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                <div class="user-role">Administrator</div>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<style>
.sidebar {
    width: 280px;
    height: 100vh;
    background: #2c3e50;
    color: #ecf0f1;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    transition: all 0.3s ease;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    z-index: 20; /* Ensure sidebar is above other content but below modals */
}

.sidebar-header {
    padding: 25px 20px;
    background: #243342;
    border-bottom: 1px solid #34495e;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo i {
    font-size: 24px;
    color: #3498db;
}

.logo h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #ecf0f1;
}

.sidebar-menu {
    padding: 20px 0;
}

.menu-section {
    margin-bottom: 25px;
}

.menu-section h3 {
    padding: 0 20px;
    font-size: 12px;
    text-transform: uppercase;
    color: #95a5a6;
    margin-bottom: 10px;
    letter-spacing: 1px;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #ecf0f1;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.menu-item:hover {
    background: #34495e;
    color: #3498db;
}

.menu-item.active {
    background: #34495e;
    color: #3498db;
    border-left-color: #3498db;
}

.menu-item i {
    width: 20px;
    margin-right: 12px;
    font-size: 16px;
}

.menu-item span {
    font-size: 14px;
    font-weight: 500;
}

.sidebar-footer {
    position: absolute;
    bottom: 0;
    width: 100%;
    padding: 20px;
    background: #243342;
    border-top: 1px solid #34495e;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: #34495e;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.user-avatar i {
    font-size: 20px;
    color: #3498db;
}

.user-details {
    flex: 1;
}

.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #ecf0f1;
}

.user-role {
    font-size: 12px;
    color: #95a5a6;
}

.logout-btn {
    display: flex;
    align-items: center;
    padding: 10px 20px;
    color: #e74c3c;
    text-decoration: none;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.logout-btn:hover {
    background: #34495e;
}

.logout-btn i {
    margin-right: 10px;
    font-size: 16px;
}

.logout-btn span {
    font-size: 14px;
    font-weight: 500;
}

/* Sidebar toggle button */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    background: #3498db;
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 5px;
    cursor: pointer;
    z-index: 30;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

/* Mobile responsive styles */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}
</style>

<script>
// Add sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                event.target !== sidebarToggle) {
                sidebar.classList.remove('show');
            }
        });
    }
});
</script> 