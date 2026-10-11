<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config/config.php';
require_once __DIR__ . '/includes/auth/auth.php';

//startAuthSession();

/*
 * If the user is already logged in,
 * send them to the dashboard.
 */
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {

        $error = 'Username and password are required.';

    } elseif (loginUser($username, $password)) {

        header('Location: dash.php');
        exit;

    } else {

        $error = 'Invalid username or password.';
    }
}

?>
<!DOCTYPE html>
<html lang="en-US" dir="ltr" data-navigation-type="default" data-navbar-horizontal-shape="default">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ===============================================-->
    <!--    Document Title-->
    <!-- ===============================================-->
    <title>Sign In - Britdrive Motors</title>

     <!-- PWA Meta Tags -->
  <meta name="application-name" content="BritDrive">
  <meta name="description" content="Vehicle Management System">
  <meta name="mobile-web-app-capable" content="yes">
  
  <!-- iOS Settings -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="BritDrive">
  <meta name="format-detection" content="telephone=no">
  <!-- Microsoft Settings -->
  <meta name="msapplication-TileColor" content="#0d6efd">
  <meta name="msapplication-tap-highlight" content="no">
  <meta name="msapplication-TileImage" content="uploads/images/britdrive.png">
  
  <!-- Theme Color (ONLY ONE - remove the duplicate below) -->
  <meta name="theme-color" content="#ffffff">

  <link rel="manifest" href="<?php echo $URL; ?>/manifest.json" crossorigin="use-credentials">


  <!-- Apple Touch Icons -->
  <link rel="apple-touch-icon" href="<?php echo $URL; ?>/assets/icons/icon-180x180.png">
  <link rel="apple-touch-icon" sizes="16x16" href="<?php echo $URL; ?>/assets/icons/icon-16x16.png">
  <link rel="apple-touch-icon" sizes="32x32" href="<?php echo $URL; ?>/assets/icons/icon-32x32.png">
  <link rel="apple-touch-icon" sizes="72x72" href="<?php echo $URL; ?>/assets/icons/icon-72x72.png">
  <link rel="apple-touch-icon" sizes="96x96" href="<?php echo $URL; ?>/assets/icons/icon-96x96.png">
  <link rel="apple-touch-icon" sizes="144x144" href="<?php echo $URL; ?>/assets/icons/icon-144x144.png">
  <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $URL; ?>/assets/icons/icon-152x152.png">
  <link rel="apple-touch-icon" sizes="167x167" href="<?php echo $URL; ?>/assets/icons/icon-167x167.png">
  <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $URL; ?>/assets/icons/icon-180x180.png">
  <link rel="apple-touch-icon" sizes="192x192" href="<?php echo $URL; ?>/assets/icons/icon-192x192.png">
  <link rel="apple-touch-icon" sizes="384x384" href="<?php echo $URL; ?>/assets/icons/icon-381x384.png">
  <link rel="apple-touch-icon" sizes="512x512" href="<?php echo $URL; ?>/assets/icons/icon-512x512.png">

  <!-- ===============================================-->
  <!--    Favicons-->
  <!-- ===============================================-->
<link rel="icon" href="<?php echo $URL; ?>/assets/icons/favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="<?php echo $URL; ?>/assets/icons/favicon.ico" type="image/x-icon">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $URL; ?>/assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo $URL; ?>/assets/icons/favicon-16x16.png">
  
  <!-- ===============================================-->
  <!--    Service Worker Registration-->
  <!-- ===============================================-->
  <script>
    // Register Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        // Get base path dynamically
        const basePath = '<?php echo $URL; ?>';
        
        navigator.serviceWorker.register(basePath + '/service-worker.js', {
          scope: basePath + '/'
        })
        .then(function(registration) {
          
          // Check for updates
          registration.addEventListener('updatefound', function() {
          });
        })
        .catch(function(error) {
        });
      });
    } else {
    }
    
    // Detect if app is running as PWA
    window.addEventListener('load', function() {
      // Check if running in standalone mode
      if (window.matchMedia('(display-mode: standalone)').matches) {
        document.documentElement.setAttribute('data-pwa', 'true');
      }
      
      // Listen for install prompt
      let deferredPrompt;
      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // You can show an install button here
        // showInstallPromotion();
      });
    });
  </script>

    <!-- ===============================================-->
  <!--    Stylesheets   -->
  <!-- ===============================================-->
  <link rel="preconnect" href="https://fonts.googleapis.com/">
  <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="">
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@300;400;600;700;800;900&amp;display=swap" rel="stylesheet">
  <link href="vendors/simplebar/simplebar.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/line.css">
  <!-- <link href="assets/css/theme-rtl.min.css" type="text/css" rel="stylesheet" id="style-rtl"> -->
  <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
  <!-- <link href="assets/css/user-rtl.min.css" type="text/css" rel="stylesheet" id="user-style-rtl"> -->
  <link href="assets/css/user.min.css" type="text/css" rel="stylesheet" id="user-style-default">
  <!-- In your head section -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script src="vendors/simplebar/simplebar.min.js"></script>
  <script src="assets/js/config.js"></script>
  
  <!-- Chart.js -->


  <script>
    var phoenixIsRTL = window.config.config.phoenixIsRTL;
    if (phoenixIsRTL) {
      var linkDefault = document.getElementById('style-default');
      var userLinkDefault = document.getElementById('user-style-default');
      linkDefault.setAttribute('disabled', true);
      userLinkDefault.setAttribute('disabled', true);
      document.querySelector('html').setAttribute('dir', 'rtl');
    } else {
      var linkRTL = document.getElementById('style-rtl');
      var userLinkRTL = document.getElementById('user-style-rtl');
      // linkRTL.setAttribute('disabled', true);
      // userLinkRTL.setAttribute('disabled', true);
    }
  </script>
  <link href="vendors/leaflet/leaflet.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
  <link href="custom.css" rel="stylesheet">  </head>

  <body>
  <form method="post" id="form1">

    <!-- ===============================================-->
    <!--    Main Content-->
    <!-- ===============================================-->
    <main class="main" id="top">
      <div class="row vh-100 g-0">
        <div class="col-lg-6 position-relative d-none d-lg-block">
          <div class="bg-holder" style="background-image:url(assets/img/bg/30.png); width:100%;"></div>
          <!--/.bg-holder-->
        </div>

        <div class="col-lg-6">
          <div class="row flex-center h-100 g-0 px-4 px-sm-0">
            <div class="col col-sm-6 col-lg-7 col-xl-6">
              <a class="d-flex flex-center text-decoration-none mb-4" href="<?= $URL ?>">
                <div class="d-flex align-items-center fw-bolder fs-3 d-inline-block">
                  <img src="uploads/images/britdrive-01.svg" alt="Britdrive Motors" width="258" /></div>
              </a>
              <div class="text-center mb-7">
                <h3 class="text-body-highlight">Sign In</h3>
                <p class="text-body-tertiary">Get access to your Admin Account</p>
              </div>
              <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
              <!-- <button class="btn btn-phoenix-secondary w-100 mb-3"><span class="fab fa-google text-danger me-2 fs-9"></span>Sign in with google</button><button class="btn btn-phoenix-secondary w-100"><span class="fab fa-facebook text-primary me-2 fs-9"></span>Sign in with facebook</button> -->
              <!-- <div class="position-relative">
                <hr class="bg-body-secondary mt-5 mb-4" />
                <div class="divider-content-center">or use email</div>
              </div> -->
              <div class="mb-3 text-start"><label class="form-label" for="username">Username</label>
                <div class="form-icon-container">
                  <input class="form-control form-icon-input" id="username" type="text" placeholder="username" name="username" autocomplete="false" /><span class="fas fa-user text-body fs-9 form-icon"></span></div>
              </div>
              <div class="mb-3 text-start"><label class="form-label" for="password">Password</label>
                <div class="form-icon-container" data-password="data-password">
                  <input class="form-control form-icon-input pe-6" id="password" type="password" name="password" autocomplete="false" placeholder="Password" data-password-input="data-password-input" /><span class="fas fa-key text-body fs-9 form-icon"></span><button class="btn px-3 py-0 h-100 position-absolute top-0 end-0 fs-7 text-body-tertiary" data-password-toggle="data-password-toggle"><span class="uil uil-eye show"></span><span class="uil uil-eye-slash hide"></span></button></div>
              </div>
              <div class="row flex-between-center mb-7">
                <div class="col-auto">
                  <div class="form-check mb-0"><input class="form-check-input" name="remember_me" id="basic-checkbox" type="checkbox" checked="checked" /><label class="form-check-label mb-0" for="basic-checkbox">Remember me</label></div>
                </div>
                <!-- <div class="col-auto"><a class="fs-9 fw-semibold" href="forgot-password.php">Forgot Password?</a></div> -->
              </div><button class="btn btn-primary w-100 mb-3">Sign In</button>
              <!-- <div class="text-center"><a class="fs-9 fw-bold" href="sign-up.html">Create an account</a></div> -->
            </div>
          </div>
        </div>
      </div>
      <div class="support-chat-container">
        <div class="container-fluid support-chat">
          <div class="card bg-body-emphasis">
            <div class="card-header d-flex flex-between-center px-4 py-3 border-bottom border-translucent">
              <h5 class="mb-0 d-flex align-items-center gap-2">Demo widget<span class="fa-solid fa-circle text-success fs-11"></span></h5>
              <div class="btn-reveal-trigger"><button class="btn btn-link p-0 dropdown-toggle dropdown-caret-none transition-none d-flex" type="button" id="support-chat-dropdown" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h text-body"></span></button>
                <div class="dropdown-menu dropdown-menu-end py-2" aria-labelledby="support-chat-dropdown"><a class="dropdown-item" href="#!">Request a callback</a><a class="dropdown-item" href="#!">Search in chat</a><a class="dropdown-item" href="#!">Show history</a><a class="dropdown-item" href="#!">Report to Admin</a><a class="dropdown-item btn-support-chat" href="#!">Close Support</a></div>
              </div>
            </div>
            <div class="card-body chat p-0">
              <div class="d-flex flex-column-reverse scrollbar h-100 p-3">
                <div class="text-end mt-6"><a class="mb-2 d-inline-flex align-items-center text-decoration-none text-body-emphasis bg-body-hover rounded-pill border border-primary py-2 ps-4 pe-3" href="#!">
                    <p class="mb-0 fw-semibold fs-9">I need help with something</p><span class="fa-solid fa-paper-plane text-primary fs-9 ms-3"></span>
                  </a><a class="mb-2 d-inline-flex align-items-center text-decoration-none text-body-emphasis bg-body-hover rounded-pill border border-primary py-2 ps-4 pe-3" href="#!">
                    <p class="mb-0 fw-semibold fs-9">I can’t reorder a product I previously ordered</p><span class="fa-solid fa-paper-plane text-primary fs-9 ms-3"></span>
                  </a><a class="mb-2 d-inline-flex align-items-center text-decoration-none text-body-emphasis bg-body-hover rounded-pill border border-primary py-2 ps-4 pe-3" href="#!">
                    <p class="mb-0 fw-semibold fs-9">How do I place an order?</p><span class="fa-solid fa-paper-plane text-primary fs-9 ms-3"></span>
                  </a><a class="false d-inline-flex align-items-center text-decoration-none text-body-emphasis bg-body-hover rounded-pill border border-primary py-2 ps-4 pe-3" href="#!">
                    <p class="mb-0 fw-semibold fs-9">My payment method not working</p><span class="fa-solid fa-paper-plane text-primary fs-9 ms-3"></span>
                  </a></div>
                <div class="text-center mt-auto">
                  <div class="avatar avatar-3xl status-online"><img class="rounded-circle border border-3 border-light-subtle" src="assets/img/team/30.webp" alt="" /></div>
                  <h5 class="mt-2 mb-3">Eric</h5>
                  <p class="text-center text-body-emphasis mb-0">Ask us anything – we’ll get back to you here or by email within 24 hours.</p>
                </div>
              </div>
            </div>
            <div class="card-footer d-flex align-items-center gap-2 border-top border-translucent ps-3 pe-4 py-3">
              <div class="d-flex align-items-center flex-1 gap-3 border border-translucent rounded-pill px-4"><input class="form-control outline-none border-0 flex-1 fs-9 px-0" type="text" placeholder="Write message" /><label class="btn btn-link d-flex p-0 text-body-quaternary fs-9 border-0" for="supportChatPhotos"><span class="fa-solid fa-image"></span></label><input class="d-none" type="file" accept="image/*" id="supportChatPhotos" /><label class="btn btn-link d-flex p-0 text-body-quaternary fs-9 border-0" for="supportChatAttachment"> <span class="fa-solid fa-paperclip"></span></label><input class="d-none" type="file" id="supportChatAttachment" /></div><button class="btn p-0 border-0 send-btn"><span class="fa-solid fa-paper-plane fs-9"></span></button>
            </div>
          </div>
        </div>
        <!-- <button class="btn btn-support-chat p-0 border border-translucent"><span class="fs-8 btn-text text-primary text-nowrap">Chat demo</span><span class="ping-icon-wrapper mt-n4 ms-n6 mt-sm-0 ms-sm-2 position-absolute position-sm-relative"><span class="ping-icon-bg"></span><span class="fa-solid fa-circle ping-icon"></span></span><span class="fa-solid fa-headset text-primary fs-8 d-sm-none"></span><span class="fa-solid fa-chevron-down text-primary fs-7"></span></button> -->
      </div>
    </main><!-- ===============================================-->
    <!--    End of Main Content-->
    <!-- ===============================================-->

              </form>
    <!-- ===============================================-->
    <!--    JavaScripts-->
    <!-- ===============================================-->
    <script src="vendors/popper/popper.min.js"></script>
    <script src="vendors/bootstrap/bootstrap.min.js"></script>
    <script src="vendors/anchorjs/anchor.min.js"></script>
    <script src="vendors/is/is.min.js"></script>
    <script src="vendors/fontawesome/all.min.js"></script>
    <script src="vendors/lodash/lodash.min.js"></script>
    <script src="vendors/list.js/list.min.js"></script>
    <script src="vendors/feather-icons/feather.min.js"></script>
    <script src="vendors/dayjs/dayjs.min.js"></script>
    <script src="assets/js/phoenix.js"></script>
  </body>


</html>