<?php
$allowedRoles = ['SYSTEM_ADMIN'];
require_once __DIR__ . '/../_guard.php';

$activePage = 'department-quota';
$pageTitle = 'Department Quota';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/eheart/assets/cares_logo.png">
    <title>Department Quota — eHeart</title>
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
            <div class="eh-content eh-quota-page">
                <div class="eh-table-wrap">
                    <div class="eh-table-toolbar eh-fluid-toolbar">
                        <div class="eh-title-block">
                            <span class="eh-title-icon"><i data-lucide="building-2"></i></span>
                            <div>
                                <h2 class="eh-page-title">Department Monthly Quota</h2>
                                <p class="eh-page-subtitle">Set the maximum EHEARTs each department can receive per month.</p>
                            </div>
                        </div>
                        <div class="eh-toolbar-actions">
                            <?php require_once __DIR__ . '/../../components/ui/search.php'; ?>
                            <?= eh_search('deptQuotaSearch', 'Search department', 'q') ?>
                            <button class="eh-btn eh-btn-primary" onclick="DeptQuotaPage.resetModal(); EHeart.openModal('addDeptQuotaModal')">+ Add New Department Quota</button>
                        </div>
                    </div>

                    <div class="eh-table-scroll">
                        <table class="eh-table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Headcount</th>
                                    <th>Monthly Quota</th>
                                    <th>Used</th>
                                    <th>Remaining</th>
                                    <th>Availability</th>
                                    <th class="eh-col-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody id="deptQuotaBody">
                                <tr>
                                    <td colspan="6">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="eh-table-footer">
                        <div class="eh-table-summary">
                            <span id="deptQuotaCount">Showing <span class="eh-page-size-container" id="deptQuotaPageSize"></span> of <span id="deptQuotaTotalCount">0</span> departments</span>
                        </div>
                        <div class="eh-pagination" id="deptQuotaPagination">
                            <button class="eh-page-btn active">1</button>
                        </div>
                        <div class="eh-goto-page" id="deptQuotaGotoPageContainer">
                            <span>Go to page</span>
                            <input type="number" class="eh-goto-input" id="deptQuotaGotoPage" min="1" value="1">
                        </div>
                    </div>
                </div>
            </div>
            <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/../../components/ui/select.php'; ?>

    <div id="addDeptQuotaModal" class="eh-modal-overlay" style="display:none;">
        <div class="eh-modal">
            <div class="eh-modal-title">Set Department Quota</div>
            <form id="deptQuotaForm">
                <?= eh_select('department', 'Department', [], '', 'Loading departments...') ?>
                <div class="eh-field">
                    <label class="eh-label">Headcount</label>
                    <input class="eh-input" type="number" min="0" name="headcount" required>
                </div>
                <div class="eh-field">
                    <label class="eh-label">Monthly Quota</label>
                    <input class="eh-input" type="number" min="1" name="monthly_quota" required>
                </div>
                <div class="eh-modal-actions">
                    <button type="button" class="eh-btn eh-btn-secondary" onclick="DeptQuotaPage.resetModal(); EHeart.closeModal('addDeptQuotaModal')">Cancel</button>
                    <button type="submit" class="eh-btn eh-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteDeptQuotaModal" class="eh-modal-overlay" style="display:none;">
        <div class="eh-delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteDeptQuotaTitle">
            <div class="eh-delete-modal-header">
                <div class="eh-delete-icon">
                    <i data-lucide="trash-2"></i>
                </div>
                <button type="button" class="eh-delete-close" aria-label="Close" onclick="DeptQuotaPage.closeDeleteModal()">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="eh-delete-modal-content">
                <h2 id="deleteDeptQuotaTitle" class="eh-delete-title">Delete Department Quota?</h2>
                <p class="eh-delete-description">
                    You are about to permanently remove the monthly quota configuration for this department.
                </p>

                <div class="eh-delete-department">
                    <div class="eh-delete-department-icon">
                        <i data-lucide="building-2"></i>
                    </div>
                    <div class="eh-delete-department-info">
                        <span class="eh-delete-label">Department</span>
                        <strong id="deleteDeptQuotaName">—</strong>
                    </div>
                </div>

                <div class="eh-delete-warning">
                    <div class="eh-delete-warning-icon">
                        <i data-lucide="triangle-alert"></i>
                    </div>
                    <div>
                        <strong>This action cannot be undone.</strong>
                        <p>The department will no longer have a configured monthly quota until a new quota is created.</p>
                    </div>
                </div>

                <div class="eh-delete-confirm-field">
                    <label for="deleteDeptQuotaInput">
                        Type <strong>DELETE</strong> to confirm
                    </label>

                    <div class="eh-delete-input-wrap">
                        <i data-lucide="shield-alert"></i>
                        <input
                            type="text"
                            id="deleteDeptQuotaInput"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            placeholder="Type DELETE"
                            aria-describedby="deleteDeptQuotaHint">
                        <i class="eh-delete-input-check" data-lucide="check-circle-2"></i>
                    </div>

                    <span id="deleteDeptQuotaHint" class="eh-delete-confirm-hint">
                        This confirmation is required before the quota can be deleted.
                    </span>
                </div>
            </div>

            <div class="eh-delete-modal-actions">
                <button
                    type="button"
                    class="eh-btn eh-btn-secondary eh-delete-cancel-btn"
                    onclick="DeptQuotaPage.closeDeleteModal()">
                    Cancel
                </button>

                <button
                    type="button"
                    class="eh-btn eh-btn-danger eh-delete-confirm-btn"
                    id="deleteDeptQuotaConfirmBtn"
                    disabled
                    onclick="DeptQuotaPage.confirmDelete()">
                    <i data-lucide="trash-2"></i>
                    <span>Delete Quota</span>
                </button>
            </div>
        </div>
    </div>

    <script src="/eheart/scripts/app.js"></script>
    <script src="/eheart/scripts/topbar.js"></script>
    <script src="/eheart/scripts/ui_pagination.js"></script>
    <script src="/eheart/scripts/ui_dropdown.js"></script>
    <script src="/eheart/scripts/department_quota.js"></script>
</body>

</html>