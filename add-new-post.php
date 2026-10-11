<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/check_auth.php';

// $requiredPermission = 'view_dashboard';
$pageTitle = 'Add New Post - Smartways';

// Check if user has permission to create posts
if (!$auth->hasPermission('manage_posts') && !$auth->hasPermission('manage_own_posts')) {
  header('Location: 403.php');
  exit;
}

$error = '';
$success = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {



  $title = trim($_POST['title']);
  $content = trim($_POST['content']);
  $excerpt = trim($_POST['excerpt']);
  $meta_title = trim($_POST['meta_title']);
  $meta_description = trim($_POST['meta_description']);
  $meta_keywords = trim($_POST['meta_keywords']);
  $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
  $status = in_array($_POST['status'], ['draft', 'published']) ? $_POST['status'] : 'draft';

    // Handle featured image upload
    $featuredImage = null;
    if (!empty($_FILES['featured_image']['tmp_name'])) {
        try {
            $featuredImage = uploadFeaturedImage($_FILES['featured_image'], $title);
        } catch (Exception $e) {
            $error = 'Image upload failed: ' . $e->getMessage();
        }
    }    
  // Validate required fields
  if (empty($title) || empty($content)) {
    $error = 'Title and content are required';
  } else {
    // Create the post
    if (createPost($title, $content, $auth->getUser()['id'], $categoryId, $featuredImage, $meta_title, $meta_description, $meta_keywords,  $status, $excerpt)) {
      $success = 'Post created successfully!';
      // After successful post creation:
      $postId = $db->lastInsertId(); // Get the new post's ID
      $tags = explode(',', $_POST['meta_keywords']); // From form input "php,web-dev,tutorial"
      addPostTags($postId, $tags);
      // Clear form if needed
      $_POST = [];
    } else {
      $error = 'Failed to create post. Please try again.';
    }
  }
}

// Get categories for dropdown
$categories = getCategoriesHierarchy();
?>

<?php


// $user = $auth->getUser();
// $roleName = $auth->getRoleName();
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
  <link href="assets/css/theme-rtl.min.css" type="text/css" rel="stylesheet" id="style-rtl">
  <link href="assets/css/theme.min.css" type="text/css" rel="stylesheet" id="style-default">
  <link href="assets/css/user-rtl.min.css" type="text/css" rel="stylesheet" id="user-style-rtl">
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
      linkRTL.setAttribute('disabled', true);
      userLinkRTL.setAttribute('disabled', true);
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
    <?php include("left-menu.php"); ?>
    <!-- Main Content -->
    <div class="content">
      <div class="pb-5">
        <div class="row g-4">
          <div class="col-12 col-xxl-6">
            <div class="mb-8">
              <h2 class="mb-2"><?= $pageTitle ?></h2>
              <!-- <h5 class="text-body-tertiary fw-semibold">Here’s what’s going on at your business right now</h5> -->

            </div>
            <hr>
            <div class="row align-items-center  mt-0">
              <div class="col-lg-9 col-md-auto" style="border: 0px solid red;">
              <form method="POST" enctype="multipart/form-data" name="form1">
                       
                <div class="row">
                      <!-- <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Add New Post</h1>
                      </div> -->

                      <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                      <?php endif; ?>

                      <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                      <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-body">
                              <div class="row">
                                <div class="mb-3">
                                  <label for="title" class="form-label">Title</label>
                                  <input type="text" class="form-control" id="title" name="title"
                                    value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                                </div>
                              </div>
                              <div class="row">
                                <div class="mb-3">
                                  <label for="content" class="form-label">Content</label>
                                  <textarea class="form-control" id="content" name="content" rows="10" required>
                                    <?=  htmlspecialchars($_POST['content'] ?? '')  ?>
                                  </textarea>
                                </div>
                              </div>
                              <div class="row">
                                <div class="mb-3">
                                    <label for="featured_image" class="form-label">Featured Image</label>
                                    <input type="file" class="form-control" name="featured_image" id="featuredImageInput" accept="image/*">
                                    <div class="form-text">
                                        Will be used for:<br>
                                        - Post thumbnail (382×200px)<br>
                                        - Open Graph/Twitter Cards<br>
                                        - Email newsletters<br>
                                        Recommended: 1200×630px (1.91:1 ratio)
                                    </div>
                                    <!-- Thumbnail preview -->
                                    <div id="thumbnailPreview" class="mt-2" style="display: none;">
                                        <p class="small mb-1">Thumbnail Preview (382×200):</p>
                                        <img id="previewImage" src="#" alt="Thumbnail preview" class="img-thumbnail" style="max-width: 382px; max-height: 200px; object-fit: cover;">
                                    </div>
                                </div>
                            </div>

                            <script>
                            // Live thumbnail preview
                            document.getElementById('featuredImageInput').addEventListener('change', function(e) {
                                const file = e.target.files[0];
                                if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(event) {
                                        const preview = document.getElementById('previewImage');
                                        preview.src = event.target.result;
                                        document.getElementById('thumbnailPreview').style.display = 'block';
                                    }
                                    reader.readAsDataURL(file);
                                }
                            });
                            </script>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                      <label for="meta_title" class="form-label">Meta Title</label>
                                      <input type="text" class="form-control" id="meta_title" name="meta_title" 
                                            maxlength="60" 
                                            value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>">
                                      <div class="text-muted small mt-1">
                                          <span id="title_counter">0</span> characters
                                          <span id="title_warning" class="text-danger d-none">(Over limit!)</span>
                                      </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Meta Keywords</label>
                                        <input type="text" class="form-control" name="meta_keywords"
                                              value="<?= htmlspecialchars($_POST['meta_keywords'] ?? '') ?>"
                                              placeholder="comma,separated,keywords">
                                    </div>
                                </div>
                                <div class="mb-3">
                                  <label for="meta_description" class="form-label">Meta Description</label>
                                  <textarea class="form-control" id="meta_description" name="meta_description" 
                                            rows="3" maxlength="160"><?= 
                                      htmlspecialchars($_POST['meta_description'] ?? '') 
                                  ?></textarea>
                                  <div class="text-muted small mt-1">
                                      <span id="desc_counter">0</span> characters
                                      <span id="desc_warning" class="text-danger d-none">(Over limit!)</span>
                                  </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        // Character counters with limits
                          document.addEventListener('DOMContentLoaded', function() {
                              // Meta Title Counter (60 chars max)
                              const metaTitle = document.querySelector('[name="meta_title"]');
                              const titleCounter = document.getElementById('title_counter');
                              if (metaTitle && titleCounter) {
                                  metaTitle.addEventListener('input', function() {
                                      const remaining = 60 - this.value.length;
                                      titleCounter.textContent = `${this.value.length}/60`;
                                      titleCounter.style.color = remaining < 0 ? 'red' : '';
                                      if (remaining < 0) {
                                          this.value = this.value.substring(0, 60); // Enforce limit
                                      }
                                  });
                                  // Initialize on load
                                  metaTitle.dispatchEvent(new Event('input'));
                              }

                              // Meta Description Counter (160 chars max)
                              const metaDesc = document.querySelector('[name="meta_description"]');
                              const descCounter = document.getElementById('desc_counter');
                              if (metaDesc && descCounter) {
                                  metaDesc.addEventListener('input', function() {
                                      const remaining = 160 - this.value.length;
                                      descCounter.textContent = `${this.value.length}/160`;
                                      descCounter.style.color = remaining < 0 ? 'red' : '';
                                      if (remaining < 0) {
                                          this.value = this.value.substring(0, 160); // Enforce limit
                                      }
                                  });
                                  // Initialize on load
                                  metaDesc.dispatchEvent(new Event('input'));
                              }
                          });
                        </script>
                        <div class="mb-3">
                          <label for="excerpt" class="form-label">Excerpt (Optional)</label>
                          <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?=
                                                                                              htmlspecialchars($_POST['excerpt'] ?? '')
                                                                                              ?></textarea>
                          <div class="form-text">A short summary of your post</div>
                        </div>

                        <div class="row">
                          <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                              <option value="">Uncategorized</option>
                              <?php renderCategoryOptions($categories, $_POST['category_id'] ?? null); ?>
                            </select>
                          </div>

                          <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                              <option value="draft" <?= ($_POST['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                              <option value="published" <?= ($_POST['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                            </select>
                          </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                          <button type="submit" class="btn btn-primary" name="submit">Save Post</button>
                          <button type="button" class="btn btn-outline-secondary" name="preview">Preview</button>
                        </div>
                    </div>

                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                <!-- Include CKEditor or other rich text editor -->
                <script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
                <script>
                  ClassicEditor
                    .create(document.querySelector('#content'))
                    .catch(error => {
                      console.error(error);
                    });
                </script>
              </div>
              </form>

              <div class="col-lg-3 col-md-auto">
                right Menu
              </div>
            </div>


          </div>
        </div>
      </div>


      <!-- Footer -->
      <?php include("footer.php"); ?>
    </div>

    <!-- Chat Now -->
  </main><!-- ===============================================-->
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