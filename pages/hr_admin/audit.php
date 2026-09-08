<?php
$allowedRoles = ['SYSTEM_ADMIN'];
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../connection/database.php';
require_once __DIR__ . '/../../services/audit_service.php';
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
    'LOGIN'                     => 'eh-badge-blue',
    'LOGOUT'                    => 'eh-badge-slate',
    'RELEASE GIFT CERTIFICATE'  => 'eh-badge-purple',
    'REDEEM HEART CARD'         => 'eh-badge-orange',
    'MANAGER GIVE HEART CARD'   => 'eh-badge-cyan',
    'APPROVE HEART CARD'        => 'eh-badge-green',
    'REJECT HEART CARD'         => 'eh-badge-red',
    'CREATE EMPLOYEE'           => 'eh-badge-teal',
    'UPDATE EMPLOYEE'           => 'eh-badge-amber',
    'DELETE EMPLOYEE'           => 'eh-badge-rose',
    default                     => eh_badge_hash_color($a),
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

$activePage = 'audit';
$pageTitle = 'Audit Logs';
$logsError = null;
try {
  $logs = AuditService::list(0);
} catch (Throwable $e) {
  $logs = [];
  $logsError = 'Audit records are temporarily unavailable.';
  error_log('Audit page failed to load audit logs: ' . $e->getMessage());
}

$moduleOptions = [];
foreach ($logs as $log) {
  $moduleOptions[$log['module']] = eh_format_label($log['module']);
}
ksort($moduleOptions);

$actionOptions = [];
foreach ($logs as $log) {
  $actionOptions[$log['action']] = eh_format_label($log['action']);
}
ksort($actionOptions);

$roleOptions = [];
foreach ($logs as $log) {
  if (!empty($log['user_role'])) {
    $roleOptions[$log['user_role']] = $log['user_role'];
  }
}
ksort($roleOptions);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Audit Logs — eHeart</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/topbar.css">
  <link rel="stylesheet" href="/eheart/styles/table.css">
  <link rel="stylesheet" href="/eheart/styles/audit.css">
  <link rel="stylesheet" href="/eheart/styles/heart_card.css">
  <link rel="stylesheet" href="/eheart/styles/ui_filters.css">
  <link rel="stylesheet" href="/eheart/styles/department_quota.css">
  <link rel="stylesheet" href="/eheart/styles/responsive.css">
</head>

<body>
  <div class="eh-app">
    <?php require __DIR__ . '/../../components/layout/sidebar.php'; ?>
    <div class="eh-main">
      <?php require __DIR__ . '/../../components/layout/topbar.php'; ?>
      <div class="eh-content">
        <div class="eh-table-wrap">
          <div class="eh-table-toolbar eh-audit-toolbar">

            <div class="eh-title-block">
              <span class="eh-title-icon"><i data-lucide="scroll-text"></i></span>
              <div>
                <h2 class="eh-page-title">Audit Logs</h2>
                <p class="eh-page-subtitle">System-wide record of user actions and events.</p>
              </div>
            </div>

            <div class="eh-filter-group">

              <?= eh_search(
                'auditSearch',
                'Search user, action, module...'
              ) ?>

              <?= eh_filter(
                'moduleFilter',
                'Module',
                $moduleOptions
              ) ?>

              <?= eh_filter(
                'actionFilter',
                'Action',
                $actionOptions
              ) ?>

              <?= eh_filter(
                'roleFilter',
                'Role',
                $roleOptions
              ) ?>

              <?= eh_date_range(
                'auditDateFrom',
                'auditDateTo'
              ) ?>

              <button
                type="button"
                class="eh-clear-filters-btn hidden"
                id="auditClearFiltersBtn"
                title="Clear all filters">
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
                  <th>User</th>
                  <th>Role</th>
                  <th>Module</th>
                  <th>Action</th>
                  <th>Description</th>
                  <th>IP Address</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody id="auditBody">
                <?php if (empty($logs)): ?>
                  <tr id="auditEmptyRow">
                    <td colspan="7">
                      <div class="eh-empty">No audit records found.</div>
                    </td>
                  </tr>
                  <?php else: foreach ($logs as $log): ?>
                    <?php $logDate = $log['created_at'] ? date('Y-m-d', strtotime($log['created_at'])) : ''; ?>
                    <tr data-module="<?= htmlspecialchars($log['module']) ?>" data-action="<?= htmlspecialchars($log['action']) ?>" data-role="<?= htmlspecialchars($log['user_role'] ?? '') ?>" data-date="<?= htmlspecialchars($logDate) ?>">
                      <!-- <td>#<?= (int) $log['audit_log_id'] ?></td> -->
                      <?php
                      $eid = $log['user_employee_id'] ?? '';
                      $photoUrl = $eid ? 'http://10.2.0.8/lrnph/emp_photos/' . htmlspecialchars($eid) . '.jpg' : '/eheart/assets/default_avatar.jpg';
                      ?>
                      <td>
                        <span class="eh-audit-user">
                          <img
                            src="<?= $photoUrl ?>"
                            alt=""
                            class="eh-table-employee-photo"
                            onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">
                          <span><?= htmlspecialchars($log['user_full_name'] ?? $log['user_biometric_id'] ?? '—') ?></span>
                        </span>
                      </td>
                      <td><?= htmlspecialchars($log['user_role'] ?? '—') ?></td>
                      <td><?= htmlspecialchars(eh_format_label($log['module'])) ?></td>
                      <td><span class="eh-badge <?= eh_action_badge_class($log['action']) ?>"><?= htmlspecialchars(eh_format_label($log['action'])) ?></span></td>
                      <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
                      <td><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                      <td><?= $log['created_at'] ? date('M j, Y - g:i A', strtotime($log['created_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach;
                endif; ?>
              </tbody>
            </table>
          </div>

          <div class="eh-table-footer">
            <span id="auditCount">Showing <?= count($logs) ?> of <?= count($logs) ?> records</span>
            <div class="eh-pagination" id="auditPagination">
              <button class="eh-page-btn active">1</button>
            </div>
            <div class="eh-goto-page">
              <span>Go to page</span>
              <input type="number" class="eh-goto-input" id="auditGotoPage" min="1" value="1">
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
  <script src="/eheart/scripts/audit.js"></script>

</body>

</html>