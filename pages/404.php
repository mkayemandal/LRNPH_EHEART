<!DOCTYPE html>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Page Not Found | eHeart</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/404.css">
  <link rel="stylesheet" href="/eheart/styles/responsive.css">
</head>

<body class="eh-not-found-body">
  <main class="eh-not-found" aria-labelledby="notFoundTitle">
    <div class="eh-not-found-glow eh-not-found-glow-one"></div>
    <div class="eh-not-found-glow eh-not-found-glow-two"></div>
    <div class="eh-not-found-shape eh-not-found-shape-one"><i data-lucide="heart"></i></div>
    <div class="eh-not-found-shape eh-not-found-shape-two"><i data-lucide="heart"></i></div>
    <div class="eh-not-found-content">
      <div class="eh-not-found-brand">
        <span class="eh-not-found-brand-icon"><i data-lucide="heart"></i></span>
        <span><strong>e</strong>Heart.</span>
      </div>
      <div class="eh-not-found-illustration">
        <div class="eh-not-found-number">4</div>
        <div class="eh-not-found-heart"><i data-lucide="heart"></i></div>
        <div class="eh-not-found-number">4</div>
      </div>
      <div class="eh-not-found-divider"></div>
      <h1 id="notFoundTitle">Oops! Page not found.</h1>
      <p>The page you are looking for may have been moved, deleted, or the link may be incorrect.</p>
      <div class="eh-not-found-actions">
        <a class="eh-btn eh-btn-primary eh-not-found-home" href="/eheart/pages/manager/dashboard.php">
          <i data-lucide="house"></i>
          <span>Go to Dashboard</span>
        </a>
        <button type="button" class="eh-not-found-back" onclick="history.back()">
          <i data-lucide="arrow-left"></i>
          <span>Go Back</span>
        </button>
      </div>
      <div class="eh-not-found-help">
        <i data-lucide="circle-help"></i>
        <span>If you think this is an error, please contact the system administrator.</span>
      </div>
    </div>
  </main>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>

</html>