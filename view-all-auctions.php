<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/errors.php';

$pageTitle = "View All Auctions";
$user = $auth->getUser();
$roleName = $auth->getRoleName();
if (!hasPermission('manage_auctions')) {
  header("Location: access-denied.php");
  exit;
}

if (isset($_GET['delid'])) {
  $auction_id = (int)$_GET['delid'];

  $stmt = $db->prepare("DELETE FROM auctions WHERE auction_id = ?");
  $stmt->execute([$auction_id]);

  if ($stmt->rowCount() > 0) {
    setSuccess("Auction ID# " . $auction_id . " has been deleted");
  } else {
    setWarning("Auction not found");
  }
  
}
// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$start = ($page > 1) ? ($page * $perPage) - $perPage : 0;

// Get total auctions for pagination
$total = $db->query("SELECT COUNT(*) FROM auctions")->fetchColumn();

$pages = ceil($total / $perPage);

// Get auctions with pagination
$stmt = $db->prepare("
    SELECT * 
    FROM auctions
    ORDER BY auction_name  
");
$stmt->execute();
$auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  <title><?php echo ($pageTitle); ?></title>

  <!-- ===============================================-->
  <!--    Favicons-->
  <!-- ===============================================-->
  <link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
  <link rel="manifest" href="assets/img/favicons/manifest.json">
  <meta name="msapplication-TileImage" content="assets/img/favicons/mstile-150x150.png">
  <meta name="theme-color" content="#ffffff">
  <script src="vendors/simplebar/simplebar.min.js"></script>
  <script src="assets/js/config.js"></script>

  <!-- ===============================================-->
  <!--    Stylesheets-->
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
      //linkRTL.setAttribute('disabled', true);
      //userLinkRTL.setAttribute('disabled', true);
    }
  </script>
  <link href="vendors/leaflet/leaflet.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.css" rel="stylesheet">
  <link href="vendors/leaflet.markercluster/MarkerCluster.Default.css" rel="stylesheet">
</head>

<body>
  <!-- ===============================================-->
  <!--    Main Content-->
  <!-- ===============================================-->
  <main class="main" id="top">
    <!-- Left Menu -->
    <?php include("left-menu.php"); ?>
    <!-- Page Content -->

    <div class="content">

      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-12">

            <div class="row justify-content-between align-items-end mb-4 g-3">
              <div class="col-12">
                <?php displayMessage(); ?>
              </div>
            </div>

            <div class="row flex-between-center mb-4 g-3">
              <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;id&quot;,&quot;name&quot;,&quot;address&quot;,&quot;city&quot;,&quot;phone&quot;,&quot;email&quot;,&quot;website&quot;,&quot;notes&quot;],&quot;page&quot;:10,&quot;pagination&quot;:true}">
                <div class="row">
                  <div class="col-8">
                    <div class="row gx-6 gy-3 mb-4 align-items-center">
                      <div class="col-auto">
                        <h2 class="mb-0">All Auctions<span class="fw-normal text-body-tertiary ms-3">(<?= $total ?>)</span></h2>
                      </div>
                      <div class="col-auto">
                        <a class="btn btn-primary px-5" href="add-auction.php">
                          <span class="fa-solid fa-plus me-2"></span>Add Auction
                        </a>
                      </div>
                    </div>
                    <!-- <p class="text-body-tertiary lh-sm mb-0">Payment received across all channels</p> -->
                  </div>
                  <div class="col-4 search-box mb-3">
                    <form class="position-relative">
                      <input class="form-control search-input search form-control-sm" type="search" placeholder="Search" aria-label="Search">
                      <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                      </svg>
                    </form>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-striped table-sm fs-9 mb-0">
                    <thead>
                      <tr>
                        <th class="sort border-top" data-sort="id">ID</th>
                        <th class="sort border-top border-translucent ps-3" data-sort="name">Name</th>
                        <th class="sort border-top " data-sort="address">Address</th>
                        <th class="sort border-top " data-sort="city">City</th>
                        <th class="sort border-top " data-sort="phone">Phone</th>
                        <!-- <th class="sort border-top" data-sort="email">Email</th> -->
                        <!-- <th class="sort border-top" data-sort="website">Website</th> -->
                        <th class="sort border-top" data-sort="notes">Notes</th>
                        <th class="sort border-top" data-sort="created">Created at</th>
                        <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                      </tr>
                    </thead>
                    <tbody class="list">
                      <?php foreach ($auctions as $auction): ?>
                        <tr>
                          <td class="align-middle ps-3 id"><?= $auction['auction_id'] ?></td>
                          <td class="align-middle name"><?= htmlspecialchars($auction['auction_name']) ?></td>
                          <td class="align-middle address"> <?= htmlspecialchars($auction['address']) ?></td>
                          <td class="align-middle city"> <?= htmlspecialchars($auction['city']) ?></td>
                          <td class="align-middle phone"><?= $auction['contact_phone'] ?></td>
                          <!-- <td class="align-left email"><?= htmlspecialchars($auction['contact_email']) ?></td> -->
                          <!-- <td class="align-left website"><?= htmlspecialchars($auction['website']) ?></td> -->
                          <td class="align-middle notes"><?= htmlspecialchars($auction['notes']) ?></td>
                          <td class="align-middle created"><?= date('d-m-y h:mA', $auction['created']) ?></td>
                          <td class="text-end align-middle">
                            <a href="view-all-auctions.php?delid=<?= $auction['auction_id'] ?>" onclick="return confirm('Are you sure to Delete [<?php echo ($auction['auction_name']); ?>]')" class="btn btn-sm btn-danger">Del</a>
                            <a href="edit-auction.php?id=<?= $auction['auction_id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>

                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                  <span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 5 <span class="text-body-tertiary"> Items of </span>40</span>
                  <div class="d-flex"><button class="page-link disabled" data-list-pagination="prev" disabled=""><svg class="svg-inline--fa fa-chevron-left" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M9.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l192 192c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256 246.6 86.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-192 192z"></path>
                      </svg><!-- <span class="fas fa-chevron-left"></span> Font Awesome fontawesome.com --></button>
                    <ul class="mb-0 pagination">
                      <li class="active"><button class="page" type="button" data-i="1" data-page="5">1</button></li>
                      <li><button class="page" type="button" data-i="2" data-page="5">2</button></li>
                      <li><button class="page" type="button" data-i="3" data-page="5">3</button></li>
                      <li class="disabled"><button class="page" type="button">...</button></li>
                    </ul><button class="page-link pe-0" data-list-pagination="next"><svg class="svg-inline--fa fa-chevron-right" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="chevron-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" data-fa-i2svg="">
                        <path fill="currentColor" d="M310.6 233.4c12.5 12.5 12.5 32.8 0 45.3l-192 192c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3L242.7 256 73.4 86.6c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0l192 192z"></path>
                      </svg><!-- <span class="fas fa-chevron-right"></span> Font Awesome fontawesome.com --></button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Footer -->
    <?php include_once("footer.php"); ?>
    </div>

    <!-- Chat Now -->

  </main>
  <!-- ===============================================-->
  <!--    End of Main Content-->
  <!-- ===============================================-->


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
  <script src="vendors/leaflet/leaflet.js"></script>
  <script src="vendors/leaflet.markercluster/leaflet.markercluster.js"></script>
  <script src="vendors/leaflet.tilelayer.colorfilter/leaflet-tilelayer-colorfilter.min.js"></script>
  <script src="assets/js/phoenix.js"></script>
  <script src="vendors/echarts/echarts.min.js"></script>
  <script src="assets/js/ecommerce-dashboard.js"></script>
</body>

</html>