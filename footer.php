<!-- <footer class="footer position-absolute">
      <div class="row g-0 justify-content-between align-items-center h-100">
        <div class="col-12 col-sm-auto text-center">
          <p class="mb-0 mt-2 mt-sm-0 text-body">Thank you for creating with Saqib Abbas<span class="d-none d-sm-inline-block"></span><span class="d-none d-sm-inline-block mx-1">|</span><br class="d-sm-none" />2025 &copy;<a class="mx-1" href="https://swiftcodershub.com">Swift Coders</a></p>
        </div>
        <div class="col-12 col-sm-auto text-center">
          <p class="mb-0 text-body-tertiary text-opacity-85">v1.20.1</p>
        </div>
      </div>
    </footer> -->
    <!-- Simpler version -->
<!-- Add this just before </body> -->
<div class="footer mobile-bottom-nav">
    <!-- Home/Dashboard -->
    <a href="index.php" class="nav-item">
        <div class="nav-icon">
            <i class="fas fa-home"></i>
        </div>
        <span class="nav-label">Home</span>
    </a>
    
    <!-- Vehicles -->
    <a href="view-all-vehicles.php" class="nav-item">
        <div class="nav-icon">
            <i class="fas fa-car"></i>
        </div>
        <span class="nav-label">Vehicles</span>
    </a>
    
    <!-- Add (Main Action) -->
    <a href="add-vehicle.php" class="nav-item nav-main-action" >
        <div class="nav-main-icon">
            <i class="fas fa-plus"></i>
        </div>
        <span class="nav-label">Add</span>
    </a>
    
    <!-- Job Cards -->
    <a href="view-all-job-cards.php" class="nav-item">
        <div class="nav-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <span class="nav-label">Job Cards</span>
    </a>
    
    <!-- More/Settings -->
    <a href="#" class="nav-item" data-bs-toggle="dropdown" >
        <div class="nav-icon">
            <i class="fas fa-ellipsis-h"></i>
        </div>
        <span class="nav-label">More</span>
    </a>
    
    <!-- More Options Dropdown -->
    <div class="dropdown-menu dropdown-menu-end" style="margin-bottom: 70px;">
        <a class="dropdown-item" href="expense-dashboard.php">
            <i class="fas fa-list me-2"></i> Expenses
        </a>
        <a class="dropdown-item" href="settings-dashboard.php">
            <i class="fas fa-cog me-2"></i> Settings
        </a>
        <a class="dropdown-item" href="reports.php">
            <i class="fas fa-chart-bar me-2"></i> Reports
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="logout.php">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>
    </div>
</div>

<!-- Quick Actions Modal -->
<div class="modal fade" id="quickActionsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Quick Actions</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-4">
                        <a href="add-vehicle.php" class="text-decoration-none">
                            <div class="text-center p-3">
                                <div class="icon-circle bg-success mb-2">
                                    <i class="fas fa-car-alt"></i>
                                </div>
                                <small class="d-block">Add Vehicle</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="add-expense.php" class="text-decoration-none">
                            <div class="text-center p-3">
                                <div class="icon-circle bg-primary mb-2">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <small class="d-block">Add Expense</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="add-repair.php" class="text-decoration-none">
                            <div class="text-center p-3">
                                <div class="icon-circle bg-warning mb-2">
                                    <i class="fas fa-wrench"></i>
                                </div>
                                <small class="d-block">Add Repair</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="add-new-job-card.php" class="text-decoration-none">
                            <div class="text-center p-3">
                                <div class="icon-circle bg-danger mb-2">
                                    <i class="far fa-clipboard"></i>
                                </div>
                                <small class="d-block">Job Card</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="add-auction.php" class="text-decoration-none">
                            <div class="text-center p-3">
                                <div class="icon-circle bg-info mb-2">
                                    <i class="fas fa-gavel"></i>
                                </div>
                                <small class="d-block">Auction</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="create-refund.php" class="text-decoration-none">
                            <div class="text-center p-3">
                                <div class="icon-circle bg-purple mb-2">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <small class="d-block">Refund</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Fixed Bottom Navigation */
.mobile-bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: space-around;
    padding: 8px 0 4px;
    z-index: 1030;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    display: none; /* Hidden by default, shown only on mobile */
}

.nav-item {
    text-decoration: none;
    text-align: center;
    flex: 1;
    padding: 4px 0;
    transition: all 0.2s;
}

.nav-item.active {
    color: #0d6efd;
}

.nav-item.active .nav-icon {
    background: rgba(13, 110, 253, 0.1);
}

.nav-icon {
    width: 32px;
    height: 32px;
    margin: 0 auto 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
    font-size: 1.3rem;
}

.nav-item i {
    font-size: 1.5rem;
    color: #6c757d;
}

.nav-item.active i {
    color: #0d6efd;
}

.nav-label {
    font-size: 0.7rem;
    color: #6c757d;
    display: block;
    line-height: 1.1;
}

.nav-item.active .nav-label {
    color: #0d6efd;
    font-weight: 500;
}

/* Main Action Button (Center Plus) */
.nav-main-action {
    position: relative;
    margin-top: -20px;
}

.nav-main-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    border-radius: 50%;
    margin: 0 auto 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    transition: all 0.3s;
    color:white;
    font-size: 1.5rem;
}

.nav-main-action .nav-label {
    color: #0d6efd;
    font-weight: 500;
}

.nav-main-icon i {
    font-size: 1.5rem;
    color: white !important;
}

.nav-main-action:active .nav-main-icon {
    transform: scale(0.95);
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
}

/* Modal icon circles */
.icon-circle {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.icon-circle i {
    font-size: 1.5rem;
    color: white !important;
}

/* Show only on mobile */
@media (max-width: 768px) {
    .mobile-bottom-nav {
        display: flex;
    }
    
    /* Add padding to page content to prevent overlap */
    body {
        padding-bottom: 70px !important;
    }
}

/* Active state for current page */
.nav-item[href*="<?php echo basename($_SERVER['PHP_SELF']); ?>"] {
    color: #0d6efd;
}

.nav-item[href*="<?php echo basename($_SERVER['PHP_SELF']); ?>"] i {
    color: #0d6efd;
}

.nav-item[href*="<?php echo basename($_SERVER['PHP_SELF']); ?>"] .nav-label {
    color: #0d6efd;
    font-weight: 500;
}
</style>

<script>
// Add active class based on current page
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href && href.includes(currentPage)) {
            item.classList.add('active');
        }
    });
    
    // Highlight "Home" for dashboard
    if (currentPage === 'dashboard.php' || currentPage === 'index.php') {
        document.querySelector('.nav-item[href*="dashboard"]').classList.add('active');
    }
    
    // Touch feedback
    navItems.forEach(item => {
        item.addEventListener('touchstart', function() {
            this.style.opacity = '0.7';
        });
        
        item.addEventListener('touchend', function() {
            this.style.opacity = '1';
        });
    });
});
</script>