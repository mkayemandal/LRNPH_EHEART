<?php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../connection/database.php';
require_once __DIR__ . '/../../services/audit_service.php';
require_once __DIR__ . '/../../utils/date_helper.php';
require_once __DIR__ . '/../../components/ui/search.php';
require_once __DIR__ . '/../../components/ui/filter.php';
require_once __DIR__ . '/../../components/ui/date_range.php';

function eh_format_label(string $val): string
{
  return ucwords(str_replace('_', ' ', $val));
}

function eh_action_badge_class(string $action): string
{
  $a = strtoupper(trim($action));
  return match ($a) {
    'RELEASE GIFT CERTIFICATE' => 'eh-badge-purple',
    'REDEEM HEART CARD' => 'eh-badge-orange',
    'MANAGER GIVE HEART CARD' => 'eh-badge-cyan',
    'APPROVE HEART CARD' => 'eh-badge-green',
    'REJECT HEART CARD' => 'eh-badge-red',
    'CREATE HEART CARD' => 'eh-badge-teal',
    'RESUBMIT HEART CARD' => 'eh-badge-amber',
    'REQUEST REVISION' => 'eh-badge-indigo',
    default => eh_badge_hash_color($a),
  };
}

function eh_badge_hash_color(string $val): string
{
  $colors = [
    'eh-badge-blue',
    'eh-badge-green',
    'eh-badge-purple',
    'eh-badge-orange',
    'eh-badge-cyan',
    'eh-badge-rose',
    'eh-badge-teal',
    'eh-badge-amber',
    'eh-badge-indigo',
    'eh-badge-lime'
  ];

  $idx = (crc32($val) & 0x7FFFFFFF) % count($colors);
  return $colors[$idx];
}

$activePage = 'history';
$pageTitle = 'History';
$isManager = ($currentUser['role_code'] ?? '') === 'MANAGER';

$logsError = null;
try {
  $logs = AuditService::listByModule(['heart_card', 'redemption'], ['LOGIN', 'LOGOUT']);
} catch (Throwable $e) {
  $logs = [];
  $logsError = 'History records are temporarily unavailable.';
  error_log('History page failed to load audit logs: ' . $e->getMessage());
}

$actionOptions = [];
foreach ($logs as $log) {
  $actionOptions[$log['action']] = eh_format_label($log['action']);
}
ksort($actionOptions);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>History — eHeart</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/topbar.css">
  <link rel="stylesheet" href="/eheart/styles/table.css">
  <link rel="stylesheet" href="/eheart/styles/heart_card.css">
  <link rel="stylesheet" href="/eheart/styles/ui_filters.css">
  <link rel="stylesheet" href="/eheart/styles/department_quota.css">
  <link rel="stylesheet" href="/eheart/styles/responsive.css">
  <style>
    .hidden {
      display: none !important;
    }
  </style>
</head>

<body>
  <div class="eh-app">
    <?php require __DIR__ . '/../../components/layout/sidebar.php'; ?>
    <div class="eh-main">
      <?php require __DIR__ . '/../../components/layout/topbar.php'; ?>
      <div class="eh-content">
        <div class="eh-table-wrap">
          <div class="eh-table-toolbar eh-fluid-toolbar">
            <div class="eh-title-block">
              <span class="eh-title-icon"><i data-lucide="history"></i></span>
              <div>
                <h2 class="eh-page-title">History</h2>
                <p class="eh-page-subtitle">Track all actions and transactions across Heart Cards.</p>
              </div>
            </div>

            <div class="eh-filter-group">
              <?= eh_search('historySearch', 'Search user, action, description...') ?>
              <?= eh_filter('actionFilter', 'Action', $actionOptions) ?>
              <?= eh_date_range('historyDateFrom', 'historyDateTo') ?>

              <button type="button" class="eh-clear-filters-btn hidden" id="historyClearFiltersBtn" title="Clear all filters">
                <i data-lucide="filter-x"></i>
                <span>Clear Filters</span>
              </button>
            </div>
          </div>

          <div class="eh-table-scroll">
            <?php if ($logsError): ?>
              <div class="eh-empty eh-mb-3"><?= htmlspecialchars($logsError) ?></div>
            <?php endif; ?>
            <table class="eh-table">
              <thead>
                <tr>
                  <th>Control No.</th>
                  <th><?= $isManager ? 'Processed By' : 'Performed By' ?></th>
                  <th>Role</th>
                  <th>Receiver</th>
                  <th>Action</th>
                  <th>Description</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody id="historyBody">
                <?php if (empty($logs)): ?>
                  <tr>
                    <td colspan="7">
                      <div class="eh-empty">No history records found.</div>
                    </td>
                  </tr>
                  <?php else: foreach ($logs as $log): ?>
                    <?php $logDate = $log['created_at'] ? date('Y-m-d', strtotime($log['created_at'])) : ''; ?>
                    <tr data-action="<?= htmlspecialchars($log['action']) ?>" data-date="<?= htmlspecialchars($logDate) ?>">
                      <td><?= !empty($log['heart_card_id']) ? htmlspecialchars(DateHelper::controlNumber((int) $log['heart_card_id'])) : '—' ?></td>
                      <td><?= htmlspecialchars($isManager ? ($log['processed_by_name'] ?? $log['user_biometric_id'] ?? '—') : ($log['manager_full_name'] ?? $log['user_biometric_id'] ?? '—')) ?></td>
                      <td><?= htmlspecialchars($log['performed_by_role'] ?? '—') ?></td>
                      <td><?= htmlspecialchars($log['receiver_full_name'] ?? $log['receiver_biometric_id'] ?? '—') ?></td>
                      <td><span class="eh-badge <?= eh_action_badge_class($log['action']) ?>"><?= htmlspecialchars(eh_format_label($log['action'])) ?></span></td>
                      <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
                      <td><?= $log['created_at'] ? date('M j, Y - g:i A', strtotime($log['created_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach;
                endif; ?>
              </tbody>
            </table>
          </div>

          <div class="eh-table-footer">
            <div class="eh-table-summary">
              <span id="historyCount">Showing <span class="eh-page-size-container" id="historyPageSize"></span> of <span id="historyTotalCount"><?= count($logs) ?></span> records</span>
            </div>
            <div class="eh-pagination" id="historyPagination">
              <button class="eh-page-btn active">1</button>
            </div>
            <div class="eh-goto-page" id="historyGotoPageContainer">
              <span>Go to page</span>
              <input type="number" class="eh-goto-input" id="historyGotoPage" min="1" value="1">
            </div>
          </div>
        </div>
      </div>
      <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
    </div>
  </div>
  <script src="/eheart/scripts/app.js"></script>
  <script src="/eheart/scripts/topbar.js"></script>
  <script src="/eheart/scripts/ui_dropdown.js"></script>
  <script src="/eheart/scripts/ui_calendar.js"></script>
  <script src="/eheart/scripts/ui_pagination.js"></script>
  <script src="/eheart/scripts/history.js"></script>
</body>

</html>