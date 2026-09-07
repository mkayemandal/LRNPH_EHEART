<?php
$allowedRoles = ['SYSTEM_ADMIN'];
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../connection/database.php';
require_once __DIR__ . '/../../components/ui/loading.php';
require_once __DIR__ . '/../../components/ui/search.php';
require_once __DIR__ . '/../../components/ui/filter.php';

$activePage = 'users';
$pageTitle = 'eHeart Users';

$pdo = DB::get_connection();
$roles = $pdo->query("SELECT role_id, role_name FROM eheart_role WHERE is_active = 1")->fetchAll();

$roleOptions = [];
foreach ($roles as $r) {
  $roleOptions[$r['role_name']] = $r['role_name'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>eHeart Users — eHeart</title>
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
          <div class="eh-table-toolbar">
            <div class="eh-title-block">
              <span class="eh-title-icon"><i data-lucide="users"></i></span>
              <div>
                <h2 class="eh-page-title">eHeart Users</h2>
                <p class="eh-page-subtitle">Manage user accounts, roles, and access.</p>
              </div>
            </div>

            <div class="eh-filter-group">
              <?= eh_search('userSearch', 'Search biometric no., name, role...') ?>

              <?= eh_filter('roleFilter', 'Role', $roleOptions) ?>

              <?= eh_filter(
                'statusFilter',
                'Status',
                [
                  '1' => 'Active',
                  '0' => 'Inactive'
                ]
              ) ?>

              <button type="button" class="eh-clear-filters-btn hidden" id="usersClearFiltersBtn" title="Clear all filters">
                <i data-lucide="filter-x"></i>
                <span>Clear Filters</span>
              </button>

              <button class="eh-btn eh-btn-primary" onclick="AddUserLookup.reset(true); EHeart.openModal('addUserModal')">+ Add User</button>
            </div>
          </div>
          <div class="eh-table-scroll">
            <table class="eh-table">
              <thead>
                <tr>
                  <th>Biometric ID</th>
                  <th>Full Name</th>
                  <th>Department</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Request Quota</th>
                  <th>Status</th>
                  <th>Last Login</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="usersBody">
                <tr>
                  <td colspan="9">
                    <?= eh_loading('Loading users...') ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="eh-table-footer">
            <span id="usersCount">Showing 0 of 0 users</span>
            <div class="eh-pagination" id="usersPagination">
              <button class="eh-page-btn active">1</button>
            </div>
            <div class="eh-goto-page">
              <span>Go to page</span>
              <input type="number" class="eh-goto-input" id="usersGotoPage" min="1" value="1">
            </div>
          </div>
        </div>
      </div>
      <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
    </div>
  </div>

  <div id="addUserModal" class="eh-modal-overlay" style="display:none;">
    <div class="eh-modal">
      <div class="eh-modal-title">Add eHeart User</div>
      <form id="addUserForm">
        <div class="eh-field">
          <label class="eh-label" for="addUserFullName">Full Name</label>
          <div class="eh-employee-lookup">
            <input
              class="eh-input"
              type="text"
              id="addUserFullName"
              name="full_name"
              placeholder="Type first or last name..."
              autocomplete="off"
              required>
            <span class="eh-employee-lookup-status hidden" id="addUserLookupStatus" aria-hidden="true">
              <i data-lucide="loader-2"></i>
            </span>
          </div>
          <div class="eh-employee-suggestions hidden" id="addUserNameSuggestions"></div>
          <div class="eh-employee-lookup-message hidden" id="addUserLookupMessage" role="alert"></div>
        </div>

        <div class="eh-field">
          <label class="eh-label" for="addUserBiometricId">Biometric ID</label>
          <input
            class="eh-input"
            type="text"
            id="addUserBiometricId"
            name="biometric_id"
            placeholder="Type Biometric ID..."
            autocomplete="off"
            required>
        </div>

        <input type="hidden" id="addUserEmployeeId" name="employee_id">

        <div class="eh-field">
          <label class="eh-label">Department</label>
          <input class="eh-input eh-input-disabled" type="text" id="addUserDepartment" readonly tabindex="-1">
        </div>
        <div class="eh-field">
          <label class="eh-label">Email</label>
          <input class="eh-input eh-input-disabled" type="email" id="addUserEmail" name="email" readonly tabindex="-1">
        </div>
        <div class="eh-field">
          <label class="eh-label">Role</label>
          <select class="eh-select" name="role_id" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= (int) $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="eh-modal-actions">
          <button type="button" class="eh-btn eh-btn-secondary" onclick="EHeart.closeModal('addUserModal')">Cancel</button>
          <button type="submit" class="eh-btn eh-btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>

  <div id="editUserModal" class="eh-modal-overlay" style="display:none;">
    <div class="eh-modal">
      <div class="eh-modal-title">Edit eHeart User</div>
      <form id="editUserForm">
        <input type="hidden" id="editUserId" name="user_id">

        <div class="eh-field">
          <label class="eh-label" for="editUserFullName">Full Name</label>
          <input
            class="eh-input eh-input-disabled"
            type="text"
            id="editUserFullName"
            name="full_name"
            readonly
            tabindex="-1">
        </div>

        <div class="eh-field">
          <label class="eh-label">Department</label>
          <input class="eh-input eh-input-disabled" type="text" id="editUserDepartment" readonly tabindex="-1">
        </div>

        <div class="eh-field">
          <label class="eh-label" for="editUserEmail">Email</label>
          <input
            class="eh-input"
            type="email"
            id="editUserEmail"
            name="email"
            autocomplete="off">
        </div>

        <div class="eh-field">
          <label class="eh-label">Role</label>
          <select class="eh-select" id="editUserRoleId" name="role_id" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= (int) $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="eh-modal-actions">
          <button type="button" class="eh-btn eh-btn-secondary" onclick="EHeart.closeModal('editUserModal')">Cancel</button>
          <button type="submit" class="eh-btn eh-btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script src="/eheart/scripts/app.js"></script>
  <script src="/eheart/scripts/topbar.js"></script>
  <script src="/eheart/scripts/ui_dropdown.js"></script>
  <script src="/eheart/scripts/ui_pagination.js"></script>
  <script src="/eheart/scripts/users.js"></script>
</body>

</html>