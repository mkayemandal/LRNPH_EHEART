<?php
require_once __DIR__ . '/../connection/database.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/response.php';

Auth::start();

if (Auth::check()) {
  header('Location: /eheart/pages/manager/dashboard.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>eHeart — Sign In</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/login.css">
</head>

<body class="eh-login-body">
  <main class="eh-login-shell">
    <!-- LEFT SIDE -->
    <section class="eh-login-hero">
      <div class="hero-shape hero-shape-1"></div>
      <div class="hero-shape hero-shape-2"></div>
      <div class="hero-shape hero-shape-3"></div>
      <div class="hero-shape hero-shape-4"></div>

      <div class="topo topo-top-left">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
      </div>
      <div class="topo topo-bottom-right">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
      </div>

      <div class="dot-pattern">
        <?php for ($i = 0; $i < 24; $i++): ?>
          <span></span>
        <?php endfor; ?>
      </div>

      <div class="decor-plus plus-top">+</div>
      <div class="decor-plus plus-middle">+</div>

      <div class="decor-circle circle-top"></div>
      <div class="decor-circle circle-bottom"></div>

      <div class="eh-login-hero-content">
        <div class="cares-brand">
          <img src="/eheart/assets/cares_logo.png" alt="Cares in Action Logo" class="cares-logo">

          <div class="cares-brand-content">
            <div class="cares-title">
              <span class="cares-text">CARES</span>
              <span class="in-text">IN</span>
              <span class="action-text">ACTION</span>
            </div>

            <div class="cares-tagline">Care. Act. Make an impact.</div>
          </div>
        </div>

        <div class="cares-divider"></div>

        <div class="login-welcome">
          <h1>Welcome back!</h1>
          <p>Sign in to access the eHeart Recognition System.</p>
        </div>
      </div>
    </section>

    <!-- RIGHT SIDE -->
    <section class="eh-login-form-side">
      <div class="login-content">
        <h2 class="eh-login-title">Sign In</h2>

        <div id="loginStatus" class="eh-alert eh-alert-info" style="display:none;"></div>
        <div id="loginError" class="eh-alert eh-alert-error" style="display:none;"></div>

        <form id="loginForm">
          <div class="eh-field">
            <label class="eh-label" for="biometric_id">Biometric ID</label>
            <div class="input-wrapper">
              <span class="input-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" fill="currentColor" />
                  <path d="M21 22C21 17.5817 16.9706 14 12 14C7.02944 14 3 17.5817 3 22" fill="currentColor" />
                </svg>
              </span>
              <input class="eh-input" id="biometric_id" type="text" name="biometric_id" placeholder="Enter your biometric ID" autocomplete="username">
            </div>
          </div>
          <div class="eh-field">
            <label class="eh-label" for="password">Password</label>
            <div class="input-wrapper">
              <span class="input-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="7" cy="15" r="4" stroke="currentColor" stroke-width="2" />
                  <path d="M10 12L20 2M20 2H16M20 2V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
              </span>
              <input class="eh-input" id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password">
              <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M1 12S5 4 12 4s11 8 11 8-4 8-11 8S1 12 1 12z" stroke="currentColor" stroke-width="2" />
                  <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                </svg>
              </button>
            </div>
          </div>
          <button class="eh-btn eh-btn-primary login-button" type="submit">Sign In</button>
        </form>
      </div>
    </section>
  </main>

  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
  <script src="/eheart/scripts/login.js"></script>
</body>

</html>