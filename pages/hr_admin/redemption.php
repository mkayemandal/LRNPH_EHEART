<?php $allowedRoles = ['HR_ADMIN', 'SYSTEM_ADMIN'];
require_once __DIR__ . '/../_guard.php';
$processedByName = json_encode($currentUser['employee_name'] ?? '');
$activePage = 'redemption';
$pageTitle = 'Redemption'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="/eheart/assets/cares_logo.png">
  <title>Redemption — eHeart</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/topbar.css">
  <link rel="stylesheet" href="/eheart/styles/redemption_stepper.css">
  <link rel="stylesheet" href="/eheart/styles/responsive.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
  <div class="eh-app">
    <?php require __DIR__ . '/../../components/layout/sidebar.php'; ?>
    <div class="eh-main">
      <?php require __DIR__ . '/../../components/layout/topbar.php'; ?>
      <div class="eh-content">
        <!-- ===================================================== STEPPER INDICATOR ====================================================== -->
        <div class="eh-stepper" id="stepperIndicator">
          <div class="eh-stepper-node active" data-node="1">
            <span class="eh-stepper-circle">
              1
            </span>
            <span class="eh-stepper-label">
              Scan / Search
            </span>
          </div>
          <div class="eh-stepper-line"></div>
          <div class="eh-stepper-node" data-node="2">
            <span class="eh-stepper-circle">
              2
            </span>
            <span class="eh-stepper-label">
              Verify
            </span>
          </div>
          <div class="eh-stepper-line"></div>
          <div class="eh-stepper-node" data-node="3">
            <span class="eh-stepper-circle">
              3
            </span>
            <span class="eh-stepper-label">
              Gift Certificate
            </span>
          </div>
          <div class="eh-stepper-line"></div>
          <div class="eh-stepper-node" data-node="4">
            <span class="eh-stepper-circle">
              4
            </span>
            <span class="eh-stepper-label">
              Review
            </span>
          </div>
        </div>
        <!-- ===================================================== STEP 1 ====================================================== -->
        <section class="eh-card eh-step eh-step-search" id="step1" data-step="1">
          <div class="eh-card-body">
            <div class="eh-step-heading">
              <div class="eh-step-icon eh-search-step-icon">
                <i data-lucide="search"></i>
              </div>
              <div>
                <div class="eh-step-kicker">
                  STEP 1 OF 4
                </div>
                <div class="eh-step-title">
                  Find a Redemption
                </div>
                <div class="eh-step-description">
                  Search using the biometrics ID, Heart Card control number, or scan the QR code.
                </div>
              </div>
            </div>
            <!-- SEARCH MODE -->
            <div class="eh-search-mode-card">
              <div class="eh-search-mode-label">
                Search using
              </div>
              <div class="eh-search-toggle" id="searchModeToggle">
                <button type="button" class="eh-toggle-btn active" data-mode="employee_id">
                  <i data-lucide="user" class="eh-toggle-icon">
                  </i>
                  Biometrics ID
                </button>
                <button type="button" class="eh-toggle-btn" data-mode="control_number">
                  <i data-lucide="heart" class="eh-toggle-icon">
                  </i>
                  Heart Card
                </button>
                <button type="button" class="eh-toggle-btn" data-mode="qr_scan">
                  <i data-lucide="qr-code" class="eh-toggle-icon">
                  </i>
                  Scan QR
                </button>
              </div>
            </div>
            <!-- SEARCH INPUT -->
            <div class="eh-search-input-card" id="searchInputCard">
              <label class="eh-label" id="searchInputLabel">
                Biometrics ID
              </label>
              <div class="eh-inline-search">
                <div class="eh-search-input-wrap">
                  <i data-lucide="search" class="eh-input-icon">
                  </i>
                  <input class="eh-input" type="text" id="employeeIdInput" placeholder="Enter Biometrics ID" autocomplete="off">
                </div>
                <button type="button" class="eh-btn eh-btn-primary eh-search-btn" id="searchEmployeeBtn">
                  <span>
                    Search
                  </span>
                </button>
              </div>
              <div class="eh-search-hint">
                Enter the biometrics ID or Heart Card control number to continue.
              </div>
            </div>
            <!-- QR SCAN -->
            <div class="eh-qr-scan-card" id="qrScanCard" hidden>
              <label class="eh-label">
                Scan Heart Card QR Code
              </label>
              <div class="eh-qr-video-wrap">
                <video id="qrVideo" playsinline muted></video>
                <div class="eh-qr-frame"></div>
              </div>
              <canvas id="qrCanvas" hidden></canvas>
              <div class="eh-qr-status" id="qrStatus">
                Point the camera at the QR code on the Heart Card.
              </div>
              <div class="eh-search-hint">
                Camera access is required. If scanning doesn't start, allow camera permission for this site in your browser.
              </div>
            </div>
          </div>
        </section>
        <!-- ===================================================== STEP 2 ====================================================== -->
        <section class="eh-card eh-step eh-step-verify" id="step2" data-step="2" hidden>
          <div class="eh-card-body">
            <!-- HEADER -->
            <div class="eh-verify-header">
              <div class="eh-verified-banner">
                <i data-lucide="check-circle-2" class="eh-check-circle">
                </i>
                <div>
                  <div class="eh-verified-title">
                    Employee Verified
                  </div>
                  <div class="eh-verified-subtitle">
                    Please review the employee and Heart Card details before proceeding.
                  </div>
                </div>
              </div>
              <div class="eh-step-number-badge">
                STEP 2 OF 4
              </div>
            </div>
            <!-- EMPLOYEE INFORMATION -->
            <div class="eh-info-section">
              <div class="eh-section-heading">
                <div>
                  <div class="eh-section-title">
                    Employee Information
                  </div>
                  <div class="eh-section-subtitle">
                    Confirm that this is the correct employee.
                  </div>
                </div>
              </div>
              <div class="eh-employee-profile">
                <div class="eh-avatar">
                  <span id="empInitial">
                    E
                  </span>
                </div>
                <div class="eh-employee-main">
                  <div class="eh-employee-name" id="empName">
                    —
                  </div>
                  <div class="eh-employee-meta">
                    <span class="eh-meta-item">
                      <span class="eh-meta-label">
                        Biometrics ID
                      </span>
                      <strong id="empId">
                        —
                      </strong>
                    </span>
                    <span class="eh-meta-divider"></span>
                    <span class="eh-meta-item">
                      <span class="eh-meta-label">
                        Department
                      </span>
                      <strong id="empDept">
                        —
                      </strong>
                    </span>
                  </div>
                </div>
                <div class="eh-verified-chip">
                  <span class="eh-verified-icon">
                    <i data-lucide="check"></i>
                  </span>
                  Verified
                </div>
              </div>
            </div>
            <!-- HEART CARD -->
            <div class="eh-info-section eh-heart-card-section">
              <div class="eh-section-heading">
                <div>
                  <div class="eh-section-title">
                    Heart Card for Redemption
                  </div>
                  <div class="eh-section-subtitle">
                    Verify the reward details before validation.
                  </div>
                </div>
              </div>
              <div class="eh-heart-card-layout">
                <div class="eh-hc-card">
                  <div class="eh-hc-card-top">
                    <span class="eh-hc-mini-label">
                      HEART CARD
                    </span>
                    <i data-lucide="heart" class="eh-hc-heart">
                    </i>
                  </div>
                  <div class="eh-hc-card-center">
                    <div class="eh-hc-brand" id="hcBrand">
                      —
                    </div>
                  </div>
                  <div class="eh-hc-card-bottom">
                    <span>
                      CONTROL NO.
                    </span>
                    <strong class="eh-hc-code" id="hcCode">
                      —
                    </strong>
                  </div>
                </div>
                <div class="eh-hc-details">
                  <div class="eh-detail-box">
                    <span class="eh-detail-label">
                      Reward
                    </span>
                    <strong id="hcReward">
                      —
                    </strong>
                  </div>
                  <div class="eh-detail-box">
                    <span class="eh-detail-label">
                      Created Date
                    </span>
                    <strong id="hcIssueDate">
                      —
                    </strong>
                  </div>
                  <div class="eh-detail-box">
                    <span class="eh-detail-label">
                      Current Status
                    </span>
                    <div>
                      <span class="eh-status-pill" id="hcStatus">
                        —
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- VALIDATION -->
            <div class="eh-validation-section">
              <div class="eh-validation-heading">
                <div class="eh-validation-icon">
                  <i data-lucide="pencil"></i>
                </div>
                <div>
                  <div class="eh-validation-title">
                    Validation Notes
                  </div>
                  <div class="eh-validation-subtitle">
                    Add optional notes regarding this redemption.
                  </div>
                </div>
              </div>
              <textarea class="eh-textarea" id="validationNotes" placeholder="Enter validation notes (optional)"></textarea>
            </div>
            <div class="eh-error" id="step2Error">
            </div>
            <!-- ACTIONS -->
            <div class="eh-step-actions eh-verify-actions">
              <button type="button" class="eh-btn eh-btn-outline-pink" id="cancelStep2Btn">
                <!-- <i data-lucide="arrow-left"></i> -->
                Cancel
              </button>
              <button type="button" class="eh-btn eh-btn-primary eh-proceed-btn" id="proceedStep2Btn">
                <span>
                  Proceed to Redemption
                </span>
                <!-- <i data-lucide="arrow-right"></i> -->
              </button>
            </div>
          </div>
        </section>
        <!-- ===================================================== STEP 3 DESKTOP = SUMMARY LEFT / SERIAL RIGHT ====================================================== -->
        <section id="step3" class="eh-step" hidden>
          <div class="eh-card-body">
            <!-- HEADER -->
            <div class="eh-release-header">
              <div class="eh-release-icon">
                <i data-lucide="gift"></i>
              </div>
              <div class="eh-release-header-content">
                <div class="eh-release-kicker">
                  STEP 3 OF 4
                </div>
                <h2 class="eh-release-title">
                  Release Gift Certificate
                </h2>
                <div class="eh-release-description">
                  Review the redemption details and enter the Gift Certificate serial number.
                </div>
              </div>
            </div>
            <!-- ================================================= TWO COLUMN CONTENT ================================================== -->
            <div class="eh-step3-content">
              <!-- ================================================= LEFT - REDEMPTION SUMMARY ================================================== -->
              <div class="eh-redemption-summary-card">
                <div class="eh-summary-card-header">
                  <div>
                    <div class="eh-summary-card-title">
                      Redemption Summary
                    </div>
                    <div class="eh-summary-card-subtitle">
                      Review the details before releasing the certificate.
                    </div>
                  </div>
                  <div class="eh-summary-reference">
                    REDEMPTION
                  </div>
                </div>
                <div class="eh-summary-details">
                  <!-- EMPLOYEE -->
                  <div class="eh-summary-detail-row">
                    <div class="eh-summary-detail-label">
                      Employee
                      <span id="sumEmployee">
                        —
                      </span>
                    </div>
                    <div class="eh-summary-detail-value">
                      Verified
                    </div>
                  </div>
                  <!-- HEART CARD -->
                  <div class="eh-summary-detail-row">
                    <div class="eh-summary-detail-label">
                      Heart Card
                      <span id="sumHeartCard">
                        —
                      </span>
                    </div>
                    <div class="eh-summary-detail-value">
                      Valid
                    </div>
                  </div>
                  <!-- REWARD -->
                  <div class="eh-summary-detail-row">
                    <div class="eh-summary-detail-label">
                      Reward
                      <span id="sumReward">
                        —
                      </span>
                    </div>
                  </div>
                  <!-- AMOUNT -->
                  <div class="eh-summary-amount">
                    <div class="eh-summary-amount-label">
                      <strong>
                        Gift Certificate Amount
                      </strong>
                      <span>
                        Reward value to be released
                      </span>
                    </div>
                    <div id="sumAmount" class="eh-summary-amount-value">
                      ₱300.00
                    </div>
                  </div>
                </div>
              </div>
              <!-- ================================================= RIGHT - GIFT CERTIFICATE ================================================== -->
              <div class="eh-step3-right">
                <!-- SERIAL NUMBER -->
                <div class="eh-gc-release-section">
                  <div class="eh-gc-release-heading">
                    <div class="eh-gc-release-heading-icon">
                      <i data-lucide="hash"></i>
                    </div>
                    <div class="eh-gc-release-heading-content">
                      <div class="eh-gc-release-title">
                        Gift Certificate Serial Number
                      </div>
                      <div class="eh-gc-release-subtitle">
                        Enter the serial number printed on the certificate.
                      </div>
                    </div>
                  </div>
                  <!-- INPUT -->
                  <div class="eh-serial-input-wrap">
                    <i data-lucide="hash" class="eh-serial-input-icon">
                    </i>
                    <input type="text" id="gcSerialNumber" class="eh-input" placeholder="Enter GC serial number" autocomplete="off">
                  </div>
                  <!-- HINT -->
                  <div class="eh-serial-hint">
                    <i data-lucide="info"></i>
                    Ensure the serial number matches the physical Gift Certificate.
                  </div>
                  <!-- ERROR -->
                  <div id="step3Error" class="eh-error">
                  </div>
                </div>
                <!-- SECURITY NOTE -->
                <div class="eh-release-note">
                  <div class="eh-release-note-icon">
                    <i data-lucide="alert-triangle"></i>
                  </div>
                  <div>
                    <strong>
                      Final Confirmation Required
                    </strong>
                    <span>
                      Once the Gift Certificate is released, the Heart Card redemption will be completed.
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <!-- ================================================= ACTIONS ================================================== -->
            <div class="eh-step3-actions">
              <button type="button" id="backStep3Btn" class="eh-btn eh-btn-outline-pink">
                <!-- <i data-lucide="arrow-left"></i> -->
                Cancel
              </button>
              <button type="button" id="releaseGcBtn" class="eh-btn eh-btn-primary">
                <i data-lucide="gift"></i>
                Release Gift Certificate
              </button>
            </div>
          </div>
        </section>
        <!-- ===================================================== STEP 4 ====================================================== -->
        <section class="eh-card eh-step eh-step-success" id="step4" data-step="4" hidden>
          <div class="eh-card-body eh-success-body">
            <!-- SUCCESS ICON -->
            <div class="eh-success-animation">
              <div class="eh-success-ring"></div>
              <div class="eh-success-icon">
                <i data-lucide="check"></i>
              </div>
            </div>
            <!-- SUCCESS TEXT -->
            <div class="eh-success-kicker">
              REDEMPTION COMPLETED
            </div>
            <div class="eh-success-title">
              Gift Certificate Released Successfully!
            </div>
            <div class="eh-success-description">
              The Heart Card has been successfully redeemed and the Gift Certificate
              has been assigned to the employee.
            </div>
            <!-- ================================================= RECEIPT ================================================== -->
            <div class="eh-redemption-receipt">
              <div class="eh-receipt-header">
                <div>
                  <div class="eh-receipt-title">
                    Redemption Summary
                  </div>
                  <div class="eh-receipt-subtitle">
                    Keep this record for verification.
                  </div>
                </div>
                <div class="eh-receipt-success-badge">
                  <i data-lucide="check"></i>
                  Completed
                </div>
              </div>
              <div class="eh-receipt-divider"></div>
              <div class="eh-success-details">
                <!-- EMPLOYEE -->
                <div class="eh-success-row">
                  <span class="eh-success-label">
                    Employee
                  </span>
                  <strong id="sEmployee">
                    —
                  </strong>
                </div>
                <!-- HEART CARD -->
                <div class="eh-success-row">
                  <span class="eh-success-label">
                    Heart Card
                  </span>
                  <strong id="sHeartCard">
                    —
                  </strong>
                </div>
                <!-- REWARD -->
                <div class="eh-success-row">
                  <span class="eh-success-label">
                    Reward
                  </span>
                  <strong id="sReward">
                    —
                  </strong>
                </div>
                <!-- GC SERIAL -->
                <div class="eh-success-row eh-gc-row">
                  <span class="eh-success-label">
                    GC Serial Number
                  </span>
                  <strong id="sGcSerial">
                    —
                  </strong>
                </div>
                <div class="eh-receipt-divider"></div>
                <!-- REDEEMED -->
                <div class="eh-success-row">
                  <span class="eh-success-label">
                    Redeemed At
                  </span>
                  <strong id="sRedeemedAt">
                    —
                  </strong>
                </div>
                <!-- PROCESSED BY -->
                <div class="eh-success-row">
                  <span class="eh-success-label">
                    Processed By
                  </span>
                  <strong id="sProcessedBy">
                    —
                  </strong>
                </div>
              </div>
            </div>
            <!-- NOTIFICATION -->
            <div class="eh-success-message">
              <i data-lucide="check" class="eh-success-message-icon">
              </i>
              <div>
                <strong>
                  Employee notification sent
                </strong>
                <span>
                  The redemption has been successfully recorded.
                </span>
              </div>
            </div>
            <!-- DONE -->
            <button type="button" class="eh-btn eh-btn-primary eh-done-btn" id="doneBtn">
              <span>
                Done
              </span>
              <!-- <i data-lucide="arrow-right" class="eh-btn-arrow"> -->
              </i>
            </button>
            <div class="eh-success-footer-note">
              You can now proceed with another Heart Card redemption.
            </div>
          </div>
        </section>
      </div>
      <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
    </div>
  </div>
  <!-- ========================================================= CURRENT USER ========================================================= -->
  <script>
    window.eHeartProcessedByName =
      <?= $processedByName ?>;
  </script>
  <!-- ========================================================= SCRIPTS ========================================================= -->
  <script src="https://unpkg.com/jsqr@1.4.0/dist/jsQR.js"></script>
  <script src="/eheart/scripts/app.js"></script>
  <script src="/eheart/scripts/topbar.js"></script>
  <script src="/eheart/scripts/redemption_stepper.js"></script>\
  <script>
    lucide.createIcons();
  </script>
</body>

</html>