<?php
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/auth/auth.php';
require_once __DIR__ . '/includes/auth/permissions.php';
// Existing VMS functions
// require_once __DIR__ . '/includes/functions.php';
// require_once __DIR__ . '/includes/dashboard_functions.php';
// require_once __DIR__ . '/includes/errors.php';
// Add Britdrive AI assistant
require_once __DIR__ . '/includes/ai/ui.php';
renderBritDriveAI();
// Get current authenticated user
$user = getCurrentUser();

requireLogin();

$scope = getPermissionScope('sales.view');

// Current user's role
$roleName = $user['role_name'] ?? '';
?>
<?php require __DIR__ . '/layout/header.php'; ?>
<!-- Content -->
<div class="content">
    <div class="d-flex flex-center content-min-h">
        <div class="text-center py-9"><img class="img-fluid mb-7 d-dark-none" src="<?= $URL ?>/assets/img/spot-illustrations/2.png" width="470" alt="" />
            <img class="img-fluid mb-7 d-light-none" src="<?= $URL ?>/assets/img/spot-illustrations/dark_2.png" width="470" alt="" />
            <h1 class="text-body-secondary fw-normal mb-5">Create Something Beautiful.</h1><a class="btn btn-lg btn-primary" href="../documentation/getting-started.html">Getting Started</a>
        </div>
    </div>
    <footer class="footer position-fixed" style="padding-left:300px; padding-right:300px; margin-bottom:15px; border-top: 0px;">

        <div class="britdrive-ai-footer">

            <div
                class="britdrive-ai-status-line"
                id="britdriveAiStatusLine"
                aria-hidden="true" style="margin-bottom: 0px;">
                <span class="britdrive-ai-status-track">
                    <span class="britdrive-ai-status-glow"></span>
                </span>
            </div>

            <form
                class="britdrive-ai-input-area"
                id="britdriveAiForm">

                <div class="britdrive-ai-input-shell">

                    <textarea
                        id="britdriveAiInput"
                        name="question"
                        rows="1"
                        placeholder="Ask BritDrive AI..."
                        autocomplete="off"></textarea>

                    <div class="britdrive-ai-input-actions">

                        <button
                            type="button"
                            class="britdrive-ai-action"
                            id="britdriveAiAction"
                            aria-label="Speak"
                            title="Speak">
                            🎤
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </footer>

</div>
</main>

<?php
require __DIR__ . '/layout/footer.php';
?>