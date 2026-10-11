<?php
require_once __DIR__ . '/includes/auth/auth.php';
require_once __DIR__ . '/includes/auth/permissions.php';
require_once __DIR__ . '/includes/config/config.php';

$pageTitle = "View All Sales";

requireLogin();

requirePermission('sales.view');

$user = getCurrentUser();
$userId = (int) $user['id'];

$scope = getPermissionScope('sales.view');

if ($scope === 'none') {
  http_response_code(403);
  exit('403 - Access Denied');
}


$perPage = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
  $page = 1;
}

$start = ($page - 1) * $perPage;

$filters = [];
$params = [];

$query = "
    SELECT
        s.sale_id,
        s.invoice_no,
        s.sale_date,
        s.sale_price,
        s.payment_method,
        s.status AS sale_status,
        s.balance_due,
        s.total_refunded,
        s.total_revenue,

        v.vehicle_id,
        v.make,
        v.model,
        v.regnumber,
        v.year,

        c.customer_id,
        c.first_name,
        c.last_name,
        c.phone,

        u.full_name AS salesperson_name,

        s.includes_part_exchange,
        s.part_exchange_allowance

    FROM sales s

    LEFT JOIN vehicles v
        ON s.vehicle_id = v.vehicle_id

    LEFT JOIN customers c
        ON s.customer_id = c.customer_id

    LEFT JOIN users u
        ON s.sales_person = u.id

    WHERE 1 = 1
";

if ($scope === 'own') 
{
  $query .= " AND s.sales_person = ?";
  $params[] = $userId;
}

if (!empty($_GET['date_from'])) 
{
  $query .= " AND s.sale_date >= ?";
  $params[] = $_GET['date_from'];
  $filters['date_from'] = $_GET['date_from'];
}


if (!empty($_GET['date_to'])) 
{
  $query .= " AND s.sale_date <= ?";
  $params[] = $_GET['date_to'];
  $filters['date_to'] = $_GET['date_to'];
}


if (!empty($_GET['invoice_no'])) 
{
  $query .= " AND s.invoice_no LIKE ?";

  $params[] = '%' . $_GET['invoice_no'] . '%';

  $filters['invoice_no'] = $_GET['invoice_no'];
}

if (!empty($_GET['customer'])) 
{
  $query .= "
        AND (
            c.first_name LIKE ?
            OR c.last_name LIKE ?
            OR c.phone LIKE ?
        )
    ";

  $customerSearch = '%' . $_GET['customer'] . '%';

  $params[] = $customerSearch;
  $params[] = $customerSearch;
  $params[] = $customerSearch;

  $filters['customer'] = $_GET['customer'];
}


if (!empty($_GET['status']) && $_GET['status'] !== 'all') 
{
  $query .= " AND s.status = ?";
  $params[] = $_GET['status'];
  $filters['status'] = $_GET['status'];
}

if (!empty($_GET['salesperson'])) 
{
  $query .= " AND s.sales_person = ?";
  $params[] = (int) $_GET['salesperson'];
  $filters['salesperson'] = (int) $_GET['salesperson'];
}


$query .= "
    ORDER BY
        s.sale_date DESC,
        s.sale_id DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countQuery = "
    SELECT COUNT(*)
    FROM sales s

    LEFT JOIN vehicles v
        ON s.vehicle_id = v.vehicle_id

    LEFT JOIN customers c
        ON s.customer_id = c.customer_id

    WHERE 1 = 1
";
$countParams = [];

if ($scope === 'own') {
  $countQuery .= " AND s.sales_person = ?";
  $countParams[] = $userId;
}

if (isset($filters['date_from'])) 
{
  $countQuery .= " AND s.sale_date >= ?";
  $countParams[] = $filters['date_from'];
}

if (isset($filters['date_to'])) 
{
  $countQuery .= " AND s.sale_date <= ?";
  $countParams[] = $filters['date_to'];
}

if (isset($filters['invoice_no'])) 
{
  $countQuery .= " AND s.invoice_no LIKE ?";
  $countParams[] = '%' . $filters['invoice_no'] . '%';
}

if (isset($filters['customer'])) 
{
  $countQuery .= "
        AND (
            c.first_name LIKE ?
            OR c.last_name LIKE ?
            OR c.phone LIKE ?
        )
    ";
  $customerSearch = '%' . $filters['customer'] . '%';
  $countParams[] = $customerSearch;
  $countParams[] = $customerSearch;
  $countParams[] = $customerSearch;
}

if (isset($filters['status']) && $filters['status'] !== 'all') 
{
  $countQuery .= " AND s.status = ?";
  $countParams[] = $filters['status'];
}

if (isset($filters['salesperson'])) 
{
  $countQuery .= " AND s.sales_person = ?";
  $countParams[] = $filters['salesperson'];
}



$totalStmt = $db->prepare($countQuery);
$totalStmt->execute($countParams);
$totalSales = (int) $totalStmt->fetchColumn();
$totalPages = $totalSales > 0 ? (int) ceil($totalSales / $perPage) : 1;

$salespeopleStmt = $db->prepare("
    SELECT
        id,
        full_name
    FROM users
    WHERE is_active = 1
    ORDER BY full_name
");
$salespeopleStmt->execute();
$salespeople = $salespeopleStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require __DIR__ . '/layout/header.php'; ?>
<!-- Content -->
<div class="content">
  <div class="pb-5">
    <div class="row">
      <div class="col-12 col-xxl-12">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
          <h1 class="h2">All Sales</h1>
          <div class="btn-toolbar mb-2 mb-md-0">
            <a href="view-all-sales.php" class="btn btn-outline-primary me-2">
              <i class="fas fa-list"></i>View Sales
            </a>
            <?php if (hasPermission('sales.create')): ?>
              <a href="sale-vehicle.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                New Sale
              </a>
            <?php endif; ?>
          </div>
        </div>
        <!-- Filters Card -->
        <div class="card filter-card mb-4">
          <div class="card-header">
            <h6 class="mb-0">🔍 Filter Invoices</h6>
          </div>
          <div class="card-body">
            <form method="GET" class="row g-3">
              <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= $filters['date_from'] ?? '' ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= $filters['date_to'] ?? '' ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Invoice #</label>
                <input type="text" class="form-control" name="invoice_no" placeholder="Invoice number..." value="<?= $filters['invoice_no'] ?? '' ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                  <option value="all">All Statuses</option>
                  <option value="completed" <?= isset($filters['status']) && $filters['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                  <option value="pending" <?= isset($filters['status']) && $filters['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="refunded" <?= isset($filters['status']) && $filters['status'] == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                  <option value="partially_refunded" <?= isset($filters['status']) && $filters['status'] == 'partially_refunded' ? 'selected' : '' ?>>Partially Refunded</option>
                  <option value="cancelled" <?= isset($filters['status']) && $filters['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Customer Search</label>
                <input type="text" class="form-control" name="customer" placeholder="Name or phone..." value="<?= $filters['customer'] ?? '' ?>">
              </div>
              <!-- <div class="col-md-4">
                <label class="form-label">Salesperson</label>
                <select class="form-select" name="salesperson">
                  <option value="">All Salespeople</option>
                  <?php foreach ($salespeople as $sp): ?>
                    <option value="<?= $sp['id'] ?>"<?= isset($filters['salesperson']) && $filters['salesperson'] == $sp['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($sp['full_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div> -->
              <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-50 me-2">
                  <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="view-all-sales.php" class="btn btn-outline-secondary w-50">
                  <i class="fas fa-times"></i> Clear
                </a>
              </div>
            </form>
          </div>
        </div>

        <div class="row flex-between-center mb-4 g-3">
          <div id="tableExample3" data-list="{&quot;valueNames&quot;:[&quot;invoice_no&quot;,&quot;sale_date&quot;,&quot;vehicle&quot;,&quot;customer&quot;,&quot;price&quot;,&quot;status&quot;,&quot;sale_person&quot;,&quot;notes&quot;],&quot;page&quot;:20,&quot;pagination&quot;:true}">
            <div class="row">
              <div class="col-8">
                <h3>Sale Records (<?= number_format($totalSales) ?>)</h3>
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
                    <th class="sort border-top" data-sort="invoice_no">Invoice Number</th>
                    <th class="sort border-top border-translucent ps-3" data-sort="sale_date">Sale Date</th>
                    <th class="sort border-top " data-sort="vehicle">Vehicle</th>
                    <th class="sort border-top " data-sort="customer">Customer</th>
                    <th class="sort border-top " data-sort="price">Price</th>
                    <th class="sort border-top" data-sort="status">Staus</th>
                    <th class="sort border-top" data-sort="sale_person">Sale Person</th>
                    <th class="sort text-end align-middle pe-0 border-top" scope="col">ACTION</th>
                  </tr>
                </thead>
                <tbody class="list">
                  <?php if (empty($sales)): ?>
                    <tr>
                      <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-file-invoice fa-2x mb-2"></i><br>
                        No invoices found matching your criteria.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                      <tr>
                        <td class="align-middle ps-3 invoice_no">
                          <a style="text-decoration: none;" href="edit-invoice.php?inv=<?= $sale['invoice_no'] ?>">
                            <strong class="text-primary"><?= htmlspecialchars($sale['invoice_no']) ?></strong>
                          </a>
                          <?php if ($sale['includes_part_exchange']): ?>
                            <span class="badge badge-phoenix badge-phoenix-warning">P/X</span>
                          <?php endif; ?>
                        </td>
                        <td class="align-middle sale_date"><?= date('d-m-Y h:mA', strtotime($sale['sale_date'])) ?></td>
                        <td class="align-middle vehicle">
                          <div class="fw-bold"><?= htmlspecialchars($sale['make'] . ' ' . $sale['model']) ?></div>
                          <small class="text-muted"><?= htmlspecialchars($sale['regnumber']) ?> • <?= $sale['year'] ?></small>
                        </td>
                        <td class="align-middle customer">
                          <div><?= htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']) ?></div>
                          <small class="text-muted"><?= htmlspecialchars($sale['phone']) ?></small>
                        </td>
                        <td class="align-middle price">
                          <div class="fw-bold">£<?= number_format($sale['sale_price'], 2) ?></div>
                          <?php if ($sale['total_refunded'] > 0): ?>
                            <small class="text-danger">Refunded: £<?= number_format($sale['total_refunded'], 2) ?></small>
                          <?php elseif ($sale['balance_due'] > 0): ?>
                            <small class="text-warning">Balance: £<?= number_format($sale['balance_due'], 2) ?></small>
                            <?php //else: 
                            ?>
                            <!-- <small class="text-success">Paid</small> -->
                          <?php endif; ?>
                        </td>
                        <td class="align-middle status">
                          <?php
                          $statusConfig = [
                            'completed' => ['class' => 'badge-phoenix badge-phoenix-success', 'icon' => '✓', 'title' => 'Completed'],
                            'pending' => ['class' => 'badge-phoenix badge-phoenix-warning', 'icon' => '⏳', 'title' => 'Pending Payment'],
                            'refunded' => ['class' => 'badge-phoenix badge-phoenix-secondary', 'icon' => '↩️', 'title' => 'Fully Refunded'],
                            'partially_refunded' => ['class' => 'badge-phoenix badge-phoenix-info', 'icon' => '↪️', 'title' => 'Partially Refunded'],
                            'cancelled' => ['class' => 'badge-phoenix badge-phoenix-danger', 'icon' => '❌', 'title' => 'Cancelled']
                          ];
                          $status = $statusConfig[$sale['sale_status']] ?? $statusConfig['completed'];
                          ?>
                          <span class="badge status-badge <?= $status['class'] ?>" title="<?= $status['title'] ?>">
                            <?= $status['icon'] ?> <?= ucfirst($sale['sale_status']) ?>
                          </span>
                        </td>
                        <td class="align-middle sale_person"><?= htmlspecialchars($sale['salesperson_name']) ?></td>
                        <td class="align-right text-end">
                          <div class="btn-reveal-trigger position-static">
                            <button class="btn btn-sm dropdown-toggle dropdown-caret-none transition-none btn-reveal" type="button" data-bs-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false" data-bs-reference="parent"><span class="fas fa-ellipsis-h fs-8"></span></button>
                            <div class="dropdown-menu dropdown-menu-end py-2">

                              <a class="dropdown-item"
                                href="sale-invoice.php?inv=<?= urlencode($sale['invoice_no']) ?>"
                                target="_blank"
                                title="View Invoice">
                                <i class="fas fa-print"></i> View Invoice
                              </a>

                              <?php if (
                                hasPermission('sales.edit') &&
                                $sale['sale_status'] !== 'cancelled' &&
                                $sale['sale_status'] !== 'completed'
                              ): ?>
                                <a class="dropdown-item"
                                  href="edit-invoice.php?inv=<?= urlencode($sale['invoice_no']) ?>"
                                  title="Edit Invoice">
                                  <i class="fas fa-edit"></i> Edit Sale
                                </a>
                              <?php endif; ?>

                              <?php if (
                                hasPermission('finance.edit') &&
                                $sale['sale_status'] === 'pending'
                              ): ?>
                                <a class="dropdown-item"
                                  href="process-payment.php?inv=<?= urlencode($sale['invoice_no']) ?>"
                                  title="Process Payment">
                                  <i class="fas fa-money-bill"></i> Process Payment
                                </a>
                              <?php endif; ?>

                              <?php if (
                                hasPermission('sales.edit') &&
                                $sale['sale_status'] === 'pending'
                              ): ?>

                                <div class="dropdown-divider"></div>

                                <a class="dropdown-item text-danger"
                                  href="cancel-sale.php?inv=<?= urlencode($sale['invoice_no']) ?>"
                                  title="Cancel Sale">
                                  <i class="fas fa-times"></i> Cancel Sale
                                </a>

                              <?php elseif ($sale['sale_status'] === 'cancelled'): ?>

                                <!-- No actions -->

                              <?php elseif (hasPermission('refunds.create')): ?>

                                <a class="dropdown-item text-danger"
                                  href="create-refund.php?inv=<?= urlencode($sale['invoice_no']) ?>"
                                  title="Refund">
                                  <i class="fas fa-times"></i> Refund
                                </a>

                              <?php endif; ?>

                            </div>
                          </div>

                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-between mt-3">
              <span class="d-none d-sm-inline-block" data-list-info="data-list-info">1 to 5 <span class="text-body-tertiary"> Items of </span><?= $totalSales ?></span>
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
<?php
require __DIR__ . '/layout/footer.php';
?>