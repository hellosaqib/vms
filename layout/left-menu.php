<?php

/**
 * BritDrive Phoenix Left Menu
 *
 * Responsibilities:
 * - Load menu configuration
 * - Check user permissions
 * - Remove unauthorized menu items
 * - Render Phoenix vertical navigation
 *
 * Security:
 * - Menu visibility is controlled by hasPermission()
 * - Actual page/action security must still be enforced on each page
 */


/*
|--------------------------------------------------------------------------
| Load Menu Configuration
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/auth/permissions.php';

$menuConfig = require __DIR__ . '/../includes/config/menu_config.php';

/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
|
| Used to highlight the currently active menu item.
|
*/

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

$currentPath = rtrim($currentPath, '/');


/*
|--------------------------------------------------------------------------
| Helper: Check Menu Permission
|--------------------------------------------------------------------------
|
| Logout and other public navigation items can omit a permission.
|
*/

if (!function_exists('menuHasPermission')) {

    function menuHasPermission(array $item): bool
    {
        // No permission means the item is allowed to be displayed.
        if (empty($item['permission'])) {
            return true;
        }

        return hasPermission($item['permission']);
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Check Active URL
|--------------------------------------------------------------------------
*/

if (!function_exists('menuIsActive')) {

    function menuIsActive(?string $url, string $currentPath): bool
    {
        if (empty($url)) {
            return false;
        }

        $menuPath = parse_url($url, PHP_URL_PATH);

        if (!$menuPath) {
            return false;
        }

        return rtrim($menuPath, '/') === $currentPath;
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Filter Menu
|--------------------------------------------------------------------------
|
| A parent menu is displayed when:
|
| 1. It has its own permission and that permission is granted
| OR
| 2. At least one submenu item is permitted.
|
| This means a user can see:
|
| Vehicles
|   └── View All Vehicles
|
| even when they do not have vehicles.create.
|
*/

if (!function_exists('filterMenuItems')) {

    function filterMenuItems(array $items): array
    {
        $filtered = [];

        foreach ($items as $key => $item) {

            /*
            |--------------------------------------------------------------------------
            | Submenu
            |--------------------------------------------------------------------------
            */

            if (!empty($item['submenu']) && is_array($item['submenu'])) {

                $visibleSubmenu = [];

                foreach ($item['submenu'] as $submenuKey => $submenu) {

                    if (menuHasPermission($submenu)) {
                        $visibleSubmenu[$submenuKey] = $submenu;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | If submenu exists but no child is permitted,
                | hide the parent.
                |--------------------------------------------------------------------------
                */

                if (empty($visibleSubmenu)) {
                    continue;
                }

                $item['submenu'] = $visibleSubmenu;

                /*
                |--------------------------------------------------------------------------
                | Parent permission is NOT required when at least
                | one child is available.
                |--------------------------------------------------------------------------
                */

                $filtered[$key] = $item;

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Normal menu item
            |--------------------------------------------------------------------------
            */

            if (!menuHasPermission($item)) {
                continue;
            }

            $filtered[$key] = $item;
        }

        return $filtered;
    }
}


/*
|--------------------------------------------------------------------------
| Filter Menu According To Current User
|--------------------------------------------------------------------------
*/

$visibleMenu = filterMenuItems($menuConfig);


/*
|--------------------------------------------------------------------------
| Phoenix Vertical Navigation
|--------------------------------------------------------------------------
*/

?>

<nav class="navbar navbar-vertical navbar-expand-lg">

    <div class="collapse navbar-collapse" id="navbarVerticalCollapse">

        <div class="navbar-vertical-content">

            <ul class="navbar-nav flex-column" id="navbarVerticalNav">

                <?php foreach ($visibleMenu as $menuKey => $item): ?>

                    <?php

                    $hasSubmenu = !empty($item['submenu']);

                    $menuId = 'nv-' . preg_replace(
                        '/[^a-zA-Z0-9_-]/',
                        '-',
                        $menuKey
                    );

                    $menuIsActive = false;

                    /*
                    |--------------------------------------------------------------------------
                    | Check Parent URL
                    |--------------------------------------------------------------------------
                    */

                    if (!empty($item['url'])) {
                        $menuIsActive = menuIsActive(
                            $item['url'],
                            $currentPath
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Check Submenu URLs
                    |--------------------------------------------------------------------------
                    */

                    if ($hasSubmenu) {

                        foreach ($item['submenu'] as $submenu) {

                            if (
                                !empty($submenu['url']) &&
                                menuIsActive(
                                    $submenu['url'],
                                    $currentPath
                                )
                            ) {
                                $menuIsActive = true;
                                break;
                            }
                        }
                    }

                    ?>

                    <li class="nav-item">

                        <?php if ($hasSubmenu): ?>

                            <!-- Parent menu -->

                            <div class="nav-item-wrapper">

                                <a
                                    class="nav-link dropdown-indicator label-1 <?= $menuIsActive ? 'active' : '' ?>"
                                    href="#<?= htmlspecialchars($menuId) ?>"
                                    role="button"
                                    data-bs-toggle="collapse"
                                    aria-expanded="<?= $menuIsActive ? 'true' : 'false' ?>"
                                    aria-controls="<?= htmlspecialchars($menuId) ?>"
                                >

                                    <div class="d-flex align-items-center">

                                        <div class="dropdown-indicator-icon-wrapper">

                                            <span class="fas fa-caret-right dropdown-indicator-icon"></span>

                                        </div>

                                        <?php if (!empty($item['icon'])): ?>

                                            <span class="nav-link-icon">
                                                <i class="fas fa-<?= $item['icon'] ?>"></i>
                                                <!-- <span
                                                    data-feather="<?= htmlspecialchars($item['icon']) ?>"
                                                >
                                              </span> -->

                                            </span>

                                        <?php endif; ?>

                                        <span class="nav-link-text">

                                            <?= htmlspecialchars($item['title']) ?>

                                        </span>

                                    </div>

                                </a>


                                <!-- Submenu -->

                                <div class="parent-wrapper label-1">

                                    <ul
                                        class="nav collapse parent <?= $menuIsActive ? 'show' : '' ?>"
                                        data-bs-parent="#navbarVerticalCollapse"
                                        id="<?= htmlspecialchars($menuId) ?>"
                                    >

                                        <li class="collapsed-nav-item-title d-none">

                                            <?= htmlspecialchars($item['title']) ?>

                                        </li>


                                        <?php foreach ($item['submenu'] as $submenuKey => $submenu): ?>

                                            <?php

                                            $submenuActive = menuIsActive(
                                                $submenu['url'] ?? null,
                                                $currentPath
                                            );

                                            ?>

                                            <li class="nav-item">

                                                <a
                                                    class="nav-link <?= $submenuActive ? 'active' : '' ?>"
                                                    href="<?= htmlspecialchars($submenu['url']) ?>"
                                                >

                                                    <div class="d-flex align-items-center">

                                                        <span class="nav-link-text">

                                                            <?= htmlspecialchars($submenu['title']) ?>

                                                        </span>

                                                    </div>

                                                </a>

                                            </li>

                                        <?php endforeach; ?>

                                    </ul>

                                </div>

                            </div>

                        <?php else: ?>

                            <!-- Single menu item -->

                            <div class="nav-item-wrapper">

                                <a
                                    class="nav-link label-1 <?= $menuIsActive ? 'active' : '' ?>"
                                    href="<?= htmlspecialchars($item['url']) ?>"
                                >

                                    <div class="d-flex align-items-center">

                                        <?php if (!empty($item['icon'])): ?>

                                            <span class="nav-link-icon">

                                                <span
                                                    data-feather="<?= htmlspecialchars($item['icon']) ?>"
                                                ></span>

                                            </span>

                                        <?php endif; ?>

                                        <span class="nav-link-text">

                                            <?= htmlspecialchars($item['title']) ?>

                                        </span>

                                    </div>

                                </a>

                            </div>

                        <?php endif; ?>

                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    </div>
    <div class="navbar-vertical-footer"><button class="btn navbar-vertical-toggle border-0 fw-semibold w-100 white-space-nowrap d-flex align-items-center"><span class="uil uil-left-arrow-to-left fs-8"></span><span class="uil uil-arrow-from-right fs-8"></span><span class="navbar-vertical-footer-text ms-2">Collapsed View</span></button></div>
      </nav>

</nav>
