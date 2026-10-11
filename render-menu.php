<?php
require_once 'includes/config.php';
$menuItems = require 'includes/menu_config.php';
require_once 'includes/functions.php';
function checkPermission($permission, $auth)
{
    // Admin has access to everything
    if ($auth->getUser()['role_id'] == 1) return true;
    return $auth->hasPermission($permission);
}

function isActiveMenu($url, $currentPage)
{
    return basename($currentPage) == basename($url);
}

function renderMenu($items, $auth)
{
    $currentPage = $_SERVER['PHP_SELF'];

    foreach ($items as $key => $item) {
        // Skip if user doesn't have permission
        if (!checkPermission($item['permission'], $auth)) continue;
        // echo($item['permission']);
        // if (hasPermission($item['permission'])){

        $hasSubmenu = isset($item['submenu']);
        $isActive = $hasSubmenu ? false : isActiveMenu($item['url'], $currentPage);
?>

        <div class="nav-item-wrapper">
            <?php if ($hasSubmenu): ?>
                <a class="nav-link dropdown-indicator label-1" href="#<?= $key ?>"
                    role="button" data-bs-toggle="collapse" aria-expanded="false"
                    aria-controls="nv-<?= $key ?>">
                    <div class="d-flex align-items-center">
                        <div class="dropdown-indicator-icon-wrapper">
                            <span class="fas fa-caret-right dropdown-indicator-icon"></span>
                        </div>
                        <span class="nav-link-icon">
                            <i class="fas fa-<?= $item['icon'] ?>"></i>
                            <!-- <span data-feather="<?= $item['icon'] ?>"></span> -->
                        </span>
                        <span class="nav-link-text"><?= $item['title'] ?></span>
                    </div>
                </a>

                <div class="parent-wrapper label-1">
                    <ul class="nav collapse parent" data-bs-parent="#navbarVerticalCollapse" id="<?= $key ?>">
                        <li class="collapsed-nav-item-title d-none"><?= $item['title'] ?></li>
                        <?php foreach ($item['submenu'] as $subKey => $subItem): ?>
                            <?php // if (checkPermission($subItem['permission'], $auth)): 
                            ?>
                            <li class="nav-item">
                                <a class="nav-link <?= isActiveMenu($subItem['url'], $currentPage) ? 'active' : '' ?>"
                                    href="<?= $subItem['url'] ?>">
                                    <div class="d-flex align-items-center">
                                        <span class="nav-link-text"><?= $subItem['title'] ?></span>
                                    </div>
                                </a>
                            </li>
                            <?php // endif; 
                            ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <a class="nav-link label-1 <?= $isActive ? 'active' : '' ?>"
                    href="<?= $item['url'] ?>" role="button" data-bs-toggle="" aria-expanded="false">
                    <div class="d-flex align-items-center">
                        <span class="nav-link-icon">
                            <span data-feather="<?= $item['icon'] ?>"></span>
                        </span>
                        <span class="nav-link-text-wrapper">
                            <span class="nav-link-text"><?= $item['title'] ?></span>
                        </span>
                    </div>
                </a>
            <?php endif; ?>
        </div>
<?php
        //}
    }
}
?>

<li class="nav-item">
    <!-- label-->
    <div class="card border-bottom" style="border: 0px;">
        <div class="card-body " style="padding: 0px 15px 15px 15px;">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="avatar avatar-xl">
                            <div class="avatar-name rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <span><?= strtoupper(substr($user['full_name'], 0, 1)) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <p class="mb-0"><?= htmlspecialchars($roleName) ?></p>
                    </div>
                </div>

                <!-- <div class="text-end">
                    <h6 class="mb-1">Created at</h6>
                    <small class="mb-0 text-danger">
                      <?php
                        // Format the date nicely
                        $date = new DateTime($vehicle['created_at']);
                        echo $date->format('d M Y, h:i A');
                        ?>
                    </small>
                  </div> -->
            </div>
        </div>
    </div>
    
    <hr class="navbar-vertical-line" />
    <!-- parent pages-->

    <?php renderMenu($menuItems, $auth); ?>
</li>
<li>
    <hr>
    <!-- Install Button (hidden by default) -->
   <!-- <button id="installButton" class="btn btn-outline-primary btn-sm d-block">
        <i class="fas fa-download me-1"></i> Install App
    </button> -->
       <!--         <button id="installButton" class="btn btn-subtle-success btn-block mb-2 fs-9 d-block" style="box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
                    <i class="fab fa-android me-1"></i> Install Android App
                </button> -->
    <!-- Or as a floating button -->
    <!--<div class="pwa-install-floating d-none" id="pwaInstallFloating">
        <button class="btn btn-primary btn-circle shadow-lg" id="installFloatingBtn">
            <i class="fas fa-plus"></i>
        </button>
        <div class="pwa-install-tooltip">
            Install BritDrive for better experience
            <button class="btn btn-sm btn-light ms-2" id="installTooltipBtn">Install</button>
        </div>
    </div> -->

    <style>
        .pwa-install-floating {
            position: fixed;
            bottom: 80px;
            right: 20px;
            z-index: 1050;
        }

        .btn-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .pwa-install-tooltip {
            position: absolute;
            bottom: 70px;
            right: 0;
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 220px;
            display: none;
        }

        .pwa-install-floating:hover .pwa-install-tooltip {
            display: block;
        }
    </style>
</li>