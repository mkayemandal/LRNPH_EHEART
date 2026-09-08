<?php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../connection/database.php';
require_once __DIR__ . '/../../components/ui/loading.php';

$activePage = 'heart-card';
$pageTitle = 'Heart Card';
$role = $currentUser['role_code'];

$pdo = DB::get_connection();
$coreValues = $pdo->query("
    SELECT core_value_id, core_value_name
    FROM eheart_core_value
    WHERE is_active = 1
    ORDER BY core_value_name
")->fetchAll();

$departments = $pdo->query("
    SELECT DISTINCT receiver_department
    FROM eheart_heart_card
    WHERE receiver_department IS NOT NULL
      AND LTRIM(RTRIM(receiver_department)) <> ''
    ORDER BY receiver_department
")->fetchAll(PDO::FETCH_COLUMN);

$departmentOptions = [];
foreach ($departments as $department) {
  $departmentOptions[$department] = $department;
}

require_once __DIR__ . '/../../components/ui/select.php';
require_once __DIR__ . '/../../components/ui/multiselect.php';
require_once __DIR__ . '/../../components/ui/search.php';
require_once __DIR__ . '/../../components/ui/filter.php';
require_once __DIR__ . '/../../components/ui/date_range.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Heart Card — eHeart</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/eheart/styles/app.css">
  <link rel="stylesheet" href="/eheart/styles/topbar.css">
  <link rel="stylesheet" href="/eheart/styles/table.css">
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
        <div class="eh-layout-split <?= in_array($role, ['MANAGER', 'HR_ADMIN', 'SYSTEM_ADMIN'], true) ? 'with-panel' : '' ?>" id="splitLayout">
          <div class="eh-table-wrap">
            <div class="eh-table-toolbar eh-heart-card-toolbar">
              <div class="eh-title-block">
                <span class="eh-title-icon"><i data-lucide="heart"></i></span>
                <div>
                  <h2 class="eh-page-title">Heart Card</h2>
                  <p class="eh-page-subtitle">Track and manage Heart Card requests and recognitions.</p>
                </div>
              </div>

              <div class="eh-filter-group">
                <?= eh_search('searchInput', 'Search control no., employee, department...') ?>

                <?= eh_filter('departmentFilter', 'Department', $departmentOptions) ?>

                <?= eh_filter(
                  'statusFilter',
                  'Status',
                  [
                    'PENDING' => 'Pending',
                    'APPROVED' => 'Approved',
                    'FOR_REDEMPTION' => 'For Redemption',
                    'REDEEMED' => 'Redeemed',
                    'REJECTED' => 'Rejected',
                    'FOR_REVISION' => 'For Revision',
                    'EXPIRED' => 'Expired'
                  ]
                ) ?>

                <?= eh_date_range('dateFrom', 'dateTo') ?>

                <button type="button" class="eh-clear-filters-btn hidden" id="clearFiltersBtn" title="Clear all filters">
                  <i data-lucide="filter-x"></i>
                  <span>Clear Filters</span>
                </button>

                <button class="eh-btn eh-btn-secondary" id="exportDataBtn" type="button">
                  <i data-lucide="download"></i>
                  Export Data
                </button>

                <?php if ($role === 'MANAGER'): ?>
                  <button class="eh-btn eh-btn-primary" id="newRequestBtn" type="button">+ New Request</button>
                <?php endif; ?>
              </div>
            </div>

            <div class="eh-table-scroll">
              <table class="eh-table">
                <thead>
                  <tr>
                    <!-- <th class="eh-col-check">
                      <input type="checkbox" id="selectAllCards">
                    </th> -->
                    <th>Control No.</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Core Values</th>
                    <th>Date Requested</th>
                    <th>Status</th>
                    <th class="eh-col-actions-head">Actions</th>
                  </tr>
                </thead>
                <tbody id="cardTableBody">
                  <tr>
                    <td colspan="7">
                      <?= eh_loading('Loading Heart Cards...') ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="eh-table-footer">
              <div class="eh-table-summary">
                <span>Showing <span class="eh-page-size-container" id="cardPageSize"></span> of <span id="cardCount3">0</span> request</span>
              </div>

              <div class="eh-pagination" id="cardPagination">
                <button class="eh-page-btn active" type="button">1</button>
              </div>

              <div class="eh-goto-page" id="cardGotoPageContainer">
                <span>Go to page</span>
                <input type="number" class="eh-goto-input" id="cardGotoPage" min="1" value="1">
              </div>
            </div>
          </div>

          <?php if (in_array($role, ['MANAGER', 'HR_ADMIN', 'SYSTEM_ADMIN'], true)): ?>
            <div class="eh-panel" id="requestPanel">
              <div class="eh-panel-head">
                <div class="eh-panel-title" id="panelTitle">Request Heart Card</div>
                <button type="button" class="eh-panel-close" id="closeDetailsBtn">&times;</button>
              </div>

              <div id="detailsView" class="eh-details hidden">
                <div class="eh-details-control" id="dvControl">HC - 0000</div>
                <div class="eh-details-status" id="dvStatusBadge">PENDING REQUEST</div>

                <div class="eh-details-list">
                  <div class="eh-details-row" id="dvActionRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="message-square"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Action Performed</div>
                      <div class="eh-details-row-value" id="dvAction">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvRevisionNoteRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="alert-triangle"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Revision Requested</div>
                      <div class="eh-details-row-value" id="dvRevisionNote">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row">
                    <div class="eh-details-row-icon">
                      <i data-lucide="briefcase"></i>
                    </div>
                    <div style="flex:1;">
                      <div class="eh-details-row-label">Business Impact</div>
                      <div class="eh-details-row-value" id="dvBusinessImpact">—</div>
                      <textarea class="eh-textarea hidden" id="dvBusinessImpactInput" rows="3"></textarea>
                    </div>
                  </div>

                  <div class="eh-details-row">
                    <div class="eh-details-row-icon">
                      <i data-lucide="lightbulb"></i>
                    </div>
                    <div style="flex:1;">
                      <div class="eh-details-row-label">Why Beyond Normal Job Expectation</div>
                      <div class="eh-details-row-value" id="dvWhyBeyond">—</div>
                      <textarea class="eh-textarea hidden" id="dvWhyBeyondInput" rows="3"></textarea>
                    </div>
                  </div>

                  <div class="eh-details-row" id="dvRequestedByRow">
                    <img
                      id="dvRequestedByPhoto"
                      src="/eheart/assets/default_avatar.jpg"
                      alt=""
                      class="eh-details-row-photo"
                      onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">
                    <div>
                      <div class="eh-details-row-label">Requested by</div>
                      <div class="eh-details-row-value" id="dvRequestedBy">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvInspiringNoteRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="sparkles"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Inspiring Note</div>
                      <div class="eh-details-row-value" id="dvInspiringNote">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvRedeemedByRow">
                    <img
                      id="dvRedeemedByPhoto"
                      src="/eheart/assets/default_avatar.jpg"
                      alt=""
                      class="eh-details-row-photo"
                      onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">
                    <div>
                      <div class="eh-details-row-label" id="dvRedeemedByLabel">Processed by</div>
                      <div class="eh-details-row-value" id="dvRedeemedBy">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvReviewedAtRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="clock"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Date Reviewed</div>
                      <div class="eh-details-row-value" id="dvReviewedAt">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvRedeemedDateRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="calendar-check-2"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label" id="dvRedeemedDateLabel">Redeemed Date</div>
                      <div class="eh-details-row-value" id="dvRedeemedDate">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvDateIssuedRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="calendar-check"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Date Issued</div>
                      <div class="eh-details-row-value" id="dvDateIssued">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvRejectionReasonRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="x-circle"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Reason for Rejection</div>
                      <div class="eh-details-row-value" id="dvRejectionReason">—</div>
                    </div>
                  </div>

                  <div class="eh-details-row hidden" id="dvReviewedAtRow">
                    <div class="eh-details-row-icon">
                      <i data-lucide="clock"></i>
                    </div>
                    <div>
                      <div class="eh-details-row-label">Date Reviewed</div>
                      <div class="eh-details-row-value" id="dvReviewedAt">—</div>
                    </div>
                  </div>
                </div>

                <div class="eh-panel-actions" id="dvActions"></div>

                <div class="eh-remarks-box hidden" id="dvRemarksBox">
                  <!-- HR approval checklist: gates the Confirm Approve action -->
                  <div class="eh-checklist hidden" id="dvApproveChecklist">
                    <label class="eh-label" style="margin-bottom:4px;">Approval Checklist</label>
                    <label class="eh-checkbox-item">
                      <input type="checkbox" id="chkApproveActionClear">
                      <span>Is the action clear and specific?</span>
                    </label>
                    <label class="eh-checkbox-item">
                      <input type="checkbox" id="chkApproveRealImpact">
                      <span>Is there a real impact (not effort only)?</span>
                    </label>
                    <label class="eh-checkbox-item">
                      <input type="checkbox" id="chkApproveBeyondDuty">
                      <span>Is it beyond normal duty?</span>
                    </label>
                    <label class="eh-checkbox-item">
                      <input type="checkbox" id="chkApproveValueDemo">
                      <span>Is the value correctly demonstrated?</span>
                    </label>
                  </div>

                  <label class="eh-label" id="dvRemarksLabel">Remarks</label>
                  <textarea class="eh-textarea" id="dvRemarksInput" placeholder="Type remarks here..." rows="3"></textarea>

                  <div class="eh-remarks-actions">
                    <button type="button" class="eh-btn eh-btn-secondary" id="dvRemarksCancel">Cancel</button>
                    <button type="button" class="eh-btn eh-btn-primary" id="dvRemarksConfirm">Confirm</button>
                  </div>
                </div>

                <div class="eh-remarks-box hidden" id="dvGiveBox">
                  <label class="eh-label">Inspiring Note</label>
                  <textarea class="eh-textarea" id="dvGiveNoteInput" placeholder="Write a short inspiring note..." rows="3" maxlength="140"></textarea>
                  <div class="eh-remarks-actions">
                    <button type="button" class="eh-btn eh-btn-primary" id="dvGiveConfirm" onclick="HeartCardPage.confirmGive()">Send to Employee</button>
                  </div>
                </div>
              </div>

              <form id="requestForm">
                <input type="hidden" id="heart_card_id" name="heart_card_id">

                <div class="eh-quota-box">
                  <div class="eh-quota-icon">
                    <i data-lucide="gauge"></i>
                  </div>

                  <div class="eh-quota-content">
                    <div class="eh-quota-title">
                      Monthly Request Quota
                    </div>

                    <div class="eh-quota-value">
                      <span id="managerQuotaUsed">0</span>
                      /
                      <span id="managerQuotaLimit">10</span>
                      requests used
                    </div>
                  </div>
                </div>

                <div class="eh-field">
                  <label class="eh-label" for="receiverFullName">Employee Name</label>

                  <div class="eh-employee-lookup">
                    <input
                      class="eh-input"
                      type="text"
                      id="receiverFullName"
                      name="receiver_full_name"
                      placeholder="Type first or last name..."
                      autocomplete="off"
                      required>

                    <span class="eh-employee-lookup-status hidden" id="employeeLookupStatus" aria-hidden="true">
                      <i data-lucide="loader-2"></i>
                    </span>
                  </div>

                  <div class="eh-employee-suggestions hidden" id="employeeNameSuggestions"></div>
                  <div class="eh-employee-lookup-message hidden" id="employeeLookupMessage" role="alert"></div>
                </div>

                <div class="eh-field">
                  <label class="eh-label" for="receiverBiometricId">Employee Biometric ID</label>
                  <input
                    class="eh-input"
                    type="text"
                    id="receiverBiometricId"
                    name="receiver_biometric_id"
                    placeholder="Type Biometric ID..."
                    autocomplete="off"
                    required>
                </div>

                <div class="eh-field">
                  <label class="eh-label" for="receiverDepartment">Department</label>
                  <input
                    class="eh-input eh-input-disabled"
                    type="text"
                    id="receiverDepartment"
                    name="receiver_department"
                    placeholder="Department will appear here"
                    readonly
                    tabindex="-1"
                    required>
                </div>

                <div class="eh-field">
                  <label class="eh-label" for="specificAction">Specific Action Performed</label>
                  <input
                    class="eh-input"
                    type="text"
                    id="specificAction"
                    name="specific_action"
                    placeholder="Describe the specific action..."
                    required>
                </div>

                <div class="eh-field">
                  <label class="eh-label" for="businessOperationalRequest">Business / Operational Request</label>
                  <input
                    class="eh-input"
                    type="text"
                    id="businessOperationalRequest"
                    name="business_operational_request"
                    placeholder="Write your message here..."
                    required>
                </div>

                <?= eh_multiselect_core_values($coreValues) ?>

                <div class="eh-field">
                  <label class="eh-label" for="whyBeyondNormal">Why this is beyond normal job expectation?</label>
                  <input
                    class="eh-input"
                    type="text"
                    id="whyBeyondNormal"
                    name="why_beyond_normal"
                    placeholder="Write your message here..."
                    required>
                </div>

                <!-- Manager submission checklist: gates the Submit Request action -->
                <div class="eh-field eh-checklist">
                  <label class="eh-label">Confirm Before Submit</label>

                  <label class="eh-checkbox-item">
                    <input type="checkbox" id="chkActionClear">
                    <span>Is the action clear and specific?</span>
                  </label>

                  <label class="eh-checkbox-item">
                    <input type="checkbox" id="chkRealImpact">
                    <span>Is there a real impact (not effort only)?</span>
                  </label>

                  <label class="eh-checkbox-item">
                    <input type="checkbox" id="chkBeyondDuty">
                    <span>Is it beyond normal duty?</span>
                  </label>

                  <label class="eh-checkbox-item">
                    <input type="checkbox" id="chkValueDemo">
                    <span>Is the value correctly demonstrated?</span>
                  </label>
                </div>

                <div class="eh-panel-actions">
                  <button type="button" class="eh-btn eh-btn-secondary" id="cancelRequestBtn">Cancel</button>
                  <button type="submit" class="eh-btn eh-btn-primary" id="submitRequestBtn">Submit Request</button>
                </div>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
    </div>
  </div>

  <script>
    window.EH_ROLE = <?= json_encode($role) ?>;
  </script>

  <script src="/eheart/scripts/app.js"></script>
  <script src="/eheart/scripts/topbar.js"></script>
  <script src="/eheart/scripts/ui_dropdown.js"></script>
  <script src="/eheart/scripts/ui_calendar.js"></script>
  <script src="/eheart/scripts/export.js"></script>
  <script src="/eheart/scripts/ui_pagination.js"></script>
  <script src="/eheart/scripts/heart_card.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

  <script>
    if (window.lucide) lucide.createIcons();

    const selectAllCards = document.getElementById('selectAllCards');

    if (selectAllCards) {
      selectAllCards.addEventListener('change', (e) => {
        document.querySelectorAll('#cardTableBody .row-check').forEach((cb) => {
          cb.checked = e.target.checked;
        });
      });
    }
  </script>
</body>

</html>