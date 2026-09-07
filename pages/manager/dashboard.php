<?php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../components/ui/loading.php';
require_once __DIR__ . '/../../components/charts/bar-chart.php';
require_once __DIR__ . '/../../components/charts/doughnut-chart.php';
require_once __DIR__ . '/../../components/charts/line-chart.php';
$activePage = 'dashboard';
$pageTitle = 'Dashboard';
$role = $currentUser['role_code'] ?? 'MANAGER';
$employeeName = htmlspecialchars(
  $currentUser['employee_name'] ?? 'User',
  ENT_QUOTES,
  'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Dashboard — eHeart</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/topbar.css">
  <link rel="stylesheet" href="/eheart/styles/dashboard.css">
  <link rel="stylesheet" href="/eheart/styles/ui_filters.css">
  <link rel="stylesheet" href="/eheart/styles/responsive.css">
</head>

<body>
  <div class="eh-app">
    <?php require __DIR__ . '/../../components/layout/sidebar.php'; ?>
    <main class="eh-main">
      <?php require __DIR__ . '/../../components/layout/topbar.php'; ?>
      <div class="eh-content">
        <div class="eh-dashboard">
          <!-- HEADER -->
          <header class="eh-dashboard-header">
            <div class="eh-dashboard-heading">
              <h1 class="eh-dashboard-title">
                Welcome back, <?= $employeeName ?>
              </h1>
              <p class="eh-dashboard-subtitle">
                Here's an overview of your Heart Card activity and recognition data.
              </p>
            </div>
            <div class="eh-dashboard-actions">
              <button type="button" class="eh-btn eh-btn-secondary" id="btn-refresh-dashboard">
                <i data-lucide="refresh-cw"></i>
                Refresh
              </button>
              <a class="eh-btn eh-btn-primary" href="/eheart/pages/manager/heart_card.php">
                <i data-lucide="heart"></i>
                View Heart Cards
              </a>
            </div>
          </header>
          <!-- CORE VALUE OVERVIEW -->
          <section class="eh-dashboard-section">
            <div class="eh-section-heading">
              <div>
                <h2 class="eh-section-title">Core Value Overview</h2>
                <p class="eh-section-description">Recognition performance for each Core Value</p>
              </div>
            </div>
            <div id="core-value-cards" class="eh-core-value-grid">
              <?= eh_loading('Loading Core Values...') ?>
            </div>
          </section>
          <!-- OVERVIEW -->
          <section class="eh-dashboard-section">
            <div class="eh-section-heading">
              <div>
                <h2 class="eh-section-title">Overview</h2>
                <p class="eh-section-description">Current Heart Card statistics</p>
              </div>
            </div>
            <?php if ($role === 'MANAGER'): ?>
              <div class="eh-dashboard-stat-grid manager-grid">
                <div class="eh-dashboard-stat" data-stat="total_requests" data-statuses="[]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Total Requests</div>
                      <div class="eh-stat-value" id="stat-total_requests">—</div>
                      <div class="eh-stat-description">Heart Card requests you made</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="heart"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat approved" data-stat="approved" data-statuses="[&quot;APPROVED&quot;,&quot;FOR_REDEMPTION&quot;,&quot;REDEEMED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Approved</div>
                      <div class="eh-stat-value" id="stat-approved">—</div>
                      <div class="eh-stat-description">Approved recognition requests</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="circle-check"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat pending" data-stat="pending" data-statuses="[&quot;PENDING&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Pending Requests</div>
                      <div class="eh-stat-value" id="stat-pending">—</div>
                      <div class="eh-stat-description">Requests waiting for approval</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="clock-3"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat rejected" data-stat="rejected" data-statuses="[&quot;REJECTED&quot;,&quot;NOT_QUALIFIED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Rejected</div>
                      <div class="eh-stat-value" id="stat-rejected">—</div>
                      <div class="eh-stat-description">Rejected requests</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="circle-x"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat" data-stat="for_redemption" data-statuses="[&quot;FOR_REDEMPTION&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">For Redemption</div>
                      <div class="eh-stat-value" id="stat-for_redemption">—</div>
                      <div class="eh-stat-description">Cards ready to redeem</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="ticket"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat redeemed" data-stat="redeemed" data-statuses="[&quot;REDEEMED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Redeemed</div>
                      <div class="eh-stat-value" id="stat-redeemed">—</div>
                      <div class="eh-stat-description">Successfully redeemed cards</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="gift"></i></div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="eh-dashboard-stat-grid">
                <div class="eh-dashboard-stat" data-stat="heart_cards_issued" data-statuses="[]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Total Heart Cards</div>
                      <div class="eh-stat-value" id="stat-heart_cards_issued">—</div>
                      <div class="eh-stat-description">Total cards issued</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="heart"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat approved" data-stat="approved_cards" data-statuses="[&quot;APPROVED&quot;,&quot;FOR_REDEMPTION&quot;,&quot;REDEEMED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Approved Cards</div>
                      <div class="eh-stat-value" id="stat-approved_cards">—</div>
                      <div class="eh-stat-description">Approved requests</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="circle-check"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat pending" data-stat="pending_cards" data-statuses="[&quot;PENDING&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Pending Cards</div>
                      <div class="eh-stat-value" id="stat-pending_cards">—</div>
                      <div class="eh-stat-description">Awaiting approval</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="clock-3"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat rejected" data-stat="rejected_cards" data-statuses="[&quot;REJECTED&quot;,&quot;NOT_QUALIFIED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Rejected Cards</div>
                      <div class="eh-stat-value" id="stat-rejected_cards">—</div>
                      <div class="eh-stat-description">Rejected requests</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="circle-x"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat" data-stat="for_redemption" data-statuses="[&quot;FOR_REDEMPTION&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">For Redemption</div>
                      <div class="eh-stat-value" id="stat-for_redemption">—</div>
                      <div class="eh-stat-description">Cards ready to redeem</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="ticket"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat redeemed" data-stat="redeemed_hearts" data-statuses="[&quot;REDEEMED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Redeemed Hearts</div>
                      <div class="eh-stat-value" id="stat-redeemed_hearts">—</div>
                      <div class="eh-stat-description">Completed redemptions</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="gift"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat expired" data-stat="expired_hearts" data-statuses="[&quot;EXPIRED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Expired Hearts</div>
                      <div class="eh-stat-value" id="stat-expired_hearts">—</div>
                      <div class="eh-stat-description">Cards past validity</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="calendar-x"></i></div>
                  </div>
                </div>
                <div class="eh-dashboard-stat money" data-stat="total_gc_amount" data-statuses="[&quot;REDEEMED&quot;]">
                  <div class="eh-stat-top">
                    <div>
                      <div class="eh-stat-label">Total GC Amount</div>
                      <div class="eh-stat-value">₱<span id="stat-total_gc_amount">—</span></div>
                      <div class="eh-stat-description">Total gift certificate value</div>
                    </div>
                    <div class="eh-stat-icon"><i data-lucide="wallet"></i></div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </section>
          <!-- ANALYTICS -->
          <section class="eh-dashboard-section">
            <div class="eh-section-heading">
              <div>
                <h2 class="eh-section-title">Analytics</h2>
                <p class="eh-section-description">Trends and recognition activity</p>
              </div>
            </div>
            <?php if ($role === 'MANAGER'): ?>
              <div class="eh-dashboard-chart-grid">
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">My Requests by Month</h3>
                      <p class="eh-chart-subtitle">Monthly recognition requests</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_bar_chart('chart-monthly') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">My Recognitions by Core Value</h3>
                      <p class="eh-chart-subtitle">Recognition distribution</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_bar_chart('chart-core_values') ?>
                  </div>
                </div>
              </div>
            <?php elseif ($role === 'HR_ADMIN'): ?>
              <div class="eh-dashboard-chart-grid">
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Card Status</h3>
                      <p class="eh-chart-subtitle">Current card distribution</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_doughnut_chart('chart-status') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Monthly Activity</h3>
                      <p class="eh-chart-subtitle">Heart Card activity over time</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_line_chart('chart-monthly') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Recognition by Core Value</h3>
                      <p class="eh-chart-subtitle">Most recognized core values</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_bar_chart('chart-core_values') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Redemption Trend</h3>
                      <p class="eh-chart-subtitle">Monthly redemption activity</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_line_chart('chart-redemption_trend') ?>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="eh-dashboard-chart-grid">
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Card Status</h3>
                      <p class="eh-chart-subtitle">Current Heart Card distribution</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_doughnut_chart('chart-status') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Monthly Trend</h3>
                      <p class="eh-chart-subtitle">Monthly Heart Card activity</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_line_chart('chart-monthly') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Recognition by Core Value</h3>
                      <p class="eh-chart-subtitle">Recognition frequency per value</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_bar_chart('chart-core_values') ?>
                  </div>
                </div>
                <div class="eh-dashboard-chart-card">
                  <div class="eh-chart-header">
                    <div>
                      <h3 class="eh-chart-title">Recognition by Department</h3>
                      <p class="eh-chart-subtitle">Department recognition comparison</p>
                    </div>
                  </div>
                  <div class="eh-chart-container">
                    <?= eh_bar_chart('chart-by_department') ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </section>
          <!-- RECENT ACTIVITY -->
          <section class="eh-dashboard-section">
            <div class="eh-section-heading">
              <div>
                <h2 class="eh-section-title">Recent Heart Card Activity</h2>
                <p class="eh-section-description">Latest requests and updates</p>
              </div>
            </div>
            <div class="eh-activity-table-wrap">
              <table class="eh-activity-table" data-role="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>">
                <thead>
                  <tr>
                    <th>Control No.</th>
                    <th>Employee</th>
                    <?php if ($role !== 'MANAGER'): ?>
                      <th>Requestor</th>
                    <?php endif; ?>
                    <th>Core Value</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="activity-table-body">
                  <tr>
                    <td colspan="<?= $role === 'MANAGER' ? '4' : '5' ?>" class="eh-activity-empty">Loading recent activity…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </div>

      <!-- STAT DETAIL MODAL -->
      <div id="stat-detail-modal" class="eh-modal-overlay" style="display:none;">
        <div class="eh-modal eh-stat-modal">
          <div class="eh-stat-modal-header">
            <div class="eh-stat-modal-heading">
              <div class="eh-stat-modal-icon" id="stat-modal-icon"><i data-lucide="heart"></i></div>
              <div class="eh-stat-modal-titlewrap">
                <div class="eh-modal-title" id="stat-modal-title">Heart Card Details</div>
                <div class="eh-stat-modal-subtitle" id="stat-modal-subtitle">0 records</div>
              </div>
            </div>
            <button type="button" class="eh-modal-x-close" id="stat-modal-close" aria-label="Close">
              <i data-lucide="x"></i>
            </button>
          </div>
          <div class="eh-modal-body">
            <div class="eh-stat-modal-table-wrap">
              <table class="eh-activity-table" id="stat-modal-table">
                <colgroup>
                  <col class="col-control">
                  <col class="col-employee">
                  <col class="col-requestor">
                  <col class="col-corevalue">
                  <col class="col-status">
                  <col class="col-amount">
                </colgroup>
                <thead>
                  <tr>
                    <th>Control No.</th>
                    <th>Employee</th>
                    <th>Requestor</th>
                    <th>Core Value</th>
                    <th>Status</th>
                    <th class="col-amount">Amount</th>
                  </tr>
                </thead>
                <tbody id="stat-modal-table-body">
                  <tr>
                    <td colspan="5" class="eh-activity-empty">Loading…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4" defer></script>
  <script src="/eheart/scripts/app.js" defer></script>
  <script src="/eheart/scripts/topbar.js"></script>
  <script src="/eheart/scripts/dashboard.js" defer></script>
</body>

</html>