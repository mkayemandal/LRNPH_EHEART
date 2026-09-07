<?php
$currentUser = $currentUser ?? ($user ?? []);
$employeeName = $currentUser['employee_name'] ?? 'User';
$department = $currentUser['department'] ?? '';
$role = $currentUser['role_code'] ?? '';
$roleLabels = [
    'MANAGER' => 'Manager',
    'HR' => 'HR',
    'HR_ADMIN' => 'HR Administrator',
    'SYSTEM_ADMIN' => 'System Administrator'
];
$roleLabel = $roleLabels[$role] ?? ucwords(strtolower(str_replace('_', ' ', $role)));
$initial = strtoupper(mb_substr(trim($employeeName), 0, 1));
$employeeId = $currentUser['employee_id'] ?? '';
$avatarPhotoUrl = $employeeId ? 'http://10.2.0.8/lrnph/emp_photos/' . htmlspecialchars($employeeId) . '.jpg' : '';
?>
<header class="eh-topbar">
    <div class="eh-topbar-left">
        <button type="button" class="eh-mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Open menu" title="Open menu">
            <i data-lucide="menu"></i>
        </button>
        <div class="eh-topbar-title"><?= htmlspecialchars($pageTitle ?? 'eHeart') ?></div>
    </div>
    <div class="eh-topbar-actions">
        <button type="button" class="eh-topbar-guide" id="topbarGuideBtn" title="User Guide" aria-label="Open User Guide">
            <i data-lucide="circle-help"></i>
        </button>
        <div class="eh-topbar-notification-wrapper">
            <button type="button" class="eh-topbar-notification" id="topbarNotificationBtn" title="Notifications">
                <i data-lucide="bell"></i>
                <span class="eh-topbar-notification-badge" id="topbarNotifBadge" style="display:none;">0</span>
            </button>
            <div class="eh-topbar-notif-box" id="topbarNotifBox">
                <div class="eh-topbar-notif-box-header">
                    <span>Notifications</span>
                    <span class="eh-topbar-notif-box-badge" id="topbarNotifBoxBadge" style="display:none;">0</span>
                </div>
                <div class="eh-topbar-notif-box-list" id="topbarNotifBoxList">
                    <div class="eh-topbar-notif-box-empty">Loading...</div>
                </div>
                <a href="/eheart/pages/manager/notifications.php" class="eh-topbar-notif-box-seeall">See all</a>
            </div>
        </div>
        <div class="eh-topbar-profile-wrapper">
            <button type="button" class="eh-topbar-profile" id="topbarProfileToggle">
                <div class="eh-topbar-avatar">
                    <?php if ($avatarPhotoUrl): ?>
                        <img src="<?= $avatarPhotoUrl ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="var p=this.parentElement;p.removeChild(this);p.textContent='<?= htmlspecialchars($initial, ENT_QUOTES) ?>';">
                    <?php else: ?>
                        <?= htmlspecialchars($initial) ?>
                    <?php endif; ?>
                </div>
                <div class="eh-topbar-profile-info">
                    <div class="eh-topbar-name"><?= htmlspecialchars($employeeName) ?></div>
                    <div class="eh-topbar-role"><?= htmlspecialchars($roleLabel) ?></div>
                </div>
                <i data-lucide="chevrons-up-down" class="eh-topbar-profile-chevron"></i>
            </button>
            <div class="eh-topbar-profile-menu" id="topbarProfileMenu">
                <button type="button" class="eh-topbar-menu-item eh-topbar-logout" onclick="window.location='/eheart/auth/logout.php'">
                    <i data-lucide="log-out"></i>
                    <span>Log out</span>
                </button>
            </div>
        </div>
    </div>
</header>

<div class="eh-guide-overlay" id="userGuideModal" style="display:none;" data-role="<?= htmlspecialchars($role) ?>">
    <div class="eh-guide-modal" role="dialog" aria-modal="true" aria-labelledby="userGuideTitle">
        <div class="eh-guide-top">
            <div class="eh-guide-brand">
                <div class="eh-guide-brand-icon">
                    <i data-lucide="heart-handshake"></i>
                </div>
                <div>
                    <div class="eh-guide-eyebrow">EHEART SYSTEM</div>
                    <h2 id="userGuideTitle">Your eHeart Guide</h2>
                    <p>Learn how to make recognition simple and meaningful.</p>
                </div>
            </div>
            <button type="button" class="eh-guide-close" id="userGuideClose" aria-label="Close User Guide">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="eh-guide-layout">
            <div class="eh-guide-sidebar">
                <div class="eh-guide-user">
                    <div class="eh-guide-user-avatar"><?= htmlspecialchars($initial) ?></div>
                    <div>
                        <strong><?= htmlspecialchars($employeeName) ?></strong>
                        <span><?= htmlspecialchars($roleLabel) ?></span>
                    </div>
                </div>

                <div class="eh-guide-nav" id="userGuideNav">
                    <button type="button" class="eh-guide-nav-item active" data-step="0">
                        <span class="eh-guide-nav-icon"><i data-lucide="sparkles"></i></span>
                        <span>Welcome</span>
                    </button>

                    <?php if ($role === 'MANAGER'): ?>
                        <button type="button" class="eh-guide-nav-item" data-step="1">
                            <span class="eh-guide-nav-icon"><i data-lucide="heart"></i></span>
                            <span>Create Request</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="2">
                            <span class="eh-guide-nav-icon"><i data-lucide="gauge"></i></span>
                            <span>Department Quota</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="3">
                            <span class="eh-guide-nav-icon"><i data-lucide="clipboard-check"></i></span>
                            <span>Track Requests</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="4">
                            <span class="eh-guide-nav-icon"><i data-lucide="bell-ring"></i></span>
                            <span>Stay Updated</span>
                        </button>

                    <?php elseif ($role === 'HR_ADMIN' || $role === 'HR'): ?>
                        <button type="button" class="eh-guide-nav-item" data-step="1">
                            <span class="eh-guide-nav-icon"><i data-lucide="clipboard-list"></i></span>
                            <span>Review Requests</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="2">
                            <span class="eh-guide-nav-icon"><i data-lucide="badge-check"></i></span>
                            <span>Process Requests</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="3">
                            <span class="eh-guide-nav-icon"><i data-lucide="qr-code"></i></span>
                            <span>Redeem Cards</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="4">
                            <span class="eh-guide-nav-icon"><i data-lucide="chart-no-axes-combined"></i></span>
                            <span>Monitor Activity</span>
                        </button>

                    <?php elseif ($role === 'SYSTEM_ADMIN'): ?>
                        <button type="button" class="eh-guide-nav-item" data-step="1">
                            <span class="eh-guide-nav-icon"><i data-lucide="users"></i></span>
                            <span>Manage Users</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="2">
                            <span class="eh-guide-nav-icon"><i data-lucide="settings"></i></span>
                            <span>System Settings</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="3">
                            <span class="eh-guide-nav-icon"><i data-lucide="shield-check"></i></span>
                            <span>Access Control</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="4">
                            <span class="eh-guide-nav-icon"><i data-lucide="activity"></i></span>
                            <span>System Monitoring</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="5">
                            <span class="eh-guide-nav-icon"><i data-lucide="clipboard-list"></i></span>
                            <span>Review Requests</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="6">
                            <span class="eh-guide-nav-icon"><i data-lucide="badge-check"></i></span>
                            <span>Process Requests</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="7">
                            <span class="eh-guide-nav-icon"><i data-lucide="qr-code"></i></span>
                            <span>Redeem Cards</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="8">
                            <span class="eh-guide-nav-icon"><i data-lucide="chart-no-axes-combined"></i></span>
                            <span>Monitor Activity</span>
                        </button>

                    <?php else: ?>
                        <button type="button" class="eh-guide-nav-item" data-step="1">
                            <span class="eh-guide-nav-icon"><i data-lucide="layout-dashboard"></i></span>
                            <span>Navigate eHeart</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="2">
                            <span class="eh-guide-nav-icon"><i data-lucide="bell"></i></span>
                            <span>Notifications</span>
                        </button>
                        <button type="button" class="eh-guide-nav-item" data-step="3">
                            <span class="eh-guide-nav-icon"><i data-lucide="circle-check"></i></span>
                            <span>Best Practices</span>
                        </button>
                    <?php endif; ?>
                </div>

                <div class="eh-guide-sidebar-footer">
                    <i data-lucide="heart"></i>
                    <span>Recognition made meaningful</span>
                </div>
            </div>

            <div class="eh-guide-content">
                <?php if ($role === 'MANAGER'): ?>

                    <div class="eh-guide-step active" data-step="0">
                        <div class="eh-guide-step-icon welcome"><i data-lucide="heart-handshake"></i></div>
                        <div class="eh-guide-step-label">WELCOME TO EHEART</div>
                        <h3>Recognize great work.</h3>
                        <p>eHeart helps you recognize employees who demonstrate your organization's Core Values and make a positive contribution.</p>
                        <div class="eh-guide-highlight">
                            <i data-lucide="lightbulb"></i>
                            <span>Your recognition request starts the journey toward an employee receiving an eHeart Card.</span>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="1">
                        <div class="eh-guide-step-icon"><i data-lucide="heart"></i></div>
                        <div class="eh-guide-step-label">STEP 1</div>
                        <h3>Create a recognition request.</h3>
                        <p>Select the employee you want to recognize, choose the applicable Core Value, and clearly explain why the employee deserves recognition.</p>
                        <div class="eh-guide-checklist">
                            <div><i data-lucide="check"></i>Choose the correct employee</div>
                            <div><i data-lucide="check"></i>Select the applicable Core Value</div>
                            <div><i data-lucide="check"></i>Provide meaningful recognition details</div>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="2">
                        <div class="eh-guide-step-icon"><i data-lucide="gauge"></i></div>
                        <div class="eh-guide-step-label">STEP 2</div>
                        <h3>Be aware of your department quota.</h3>
                        <p>Recognition requests are subject to your department's monthly request quota. Check your available quota before creating additional requests.</p>
                        <div class="eh-guide-highlight">
                            <i data-lucide="calendar-days"></i>
                            <span>Department request usage is monitored monthly and resets for the next calendar period.</span>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="3">
                        <div class="eh-guide-step-icon"><i data-lucide="clipboard-check"></i></div>
                        <div class="eh-guide-step-label">STEP 3</div>
                        <h3>Follow your request journey.</h3>
                        <p>Monitor each request and take action when needed.</p>
                        <div class="eh-guide-statuses">
                            <span class="pending">Pending</span>
                            <span class="revision">For Revision</span>
                            <span class="rejected">Rejected</span>
                            <span class="approved">Approved</span>
                            <span class="for-redemption">For Redemption</span>
                            <span class="redeemed">Redeemed</span>
                        </div>
                        <p class="eh-guide-small">If a request is returned for revision, update the required information and resubmit it.</p>
                    </div>

                    <div class="eh-guide-step" data-step="4">
                        <div class="eh-guide-step-icon"><i data-lucide="bell-ring"></i></div>
                        <div class="eh-guide-step-label">STEP 4</div>
                        <h3>Stay informed.</h3>
                        <p>Use notifications to stay updated when HR processes your recognition request or requests changes.</p>
                        <div class="eh-guide-highlight">
                            <i data-lucide="bell"></i>
                            <span>Important updates are always easier to find from the notification bell in the topbar.</span>
                        </div>
                    </div>

                <?php elseif ($role === 'HR_ADMIN' || $role === 'HR'): ?>

                    <div class="eh-guide-step active" data-step="0">
                        <div class="eh-guide-step-icon welcome"><i data-lucide="heart-handshake"></i></div>
                        <div class="eh-guide-step-label">WELCOME TO EHEART</div>
                        <h3>Manage recognition with confidence.</h3>
                        <p>As an HR Administrator, you review recognition requests, process Heart Cards, and manage employee redemption.</p>
                    </div>

                    <div class="eh-guide-step" data-step="1">
                        <div class="eh-guide-step-icon"><i data-lucide="clipboard-list"></i></div>
                        <div class="eh-guide-step-label">STEP 1</div>
                        <h3>Review submitted requests.</h3>
                        <p>Check the employee, Core Values, recognition details, and other information before making a decision.</p>
                    </div>

                    <div class="eh-guide-step" data-step="2">
                        <div class="eh-guide-step-icon"><i data-lucide="badge-check"></i></div>
                        <div class="eh-guide-step-label">STEP 2</div>
                        <h3>Process the request.</h3>
                        <p>You can process a valid request for redemption, reject an invalid request, or return it to the manager for revision.</p>
                        <div class="eh-guide-statuses">
                            <span class="approved">Approved</span>
                            <span class="for-redemption">For Redemption</span>
                            <span class="redeemed">Redeemed</span>
                            <span class="revision">For Revision</span>
                            <span class="rejected">Rejected</span>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="3">
                        <div class="eh-guide-step-icon"><i data-lucide="qr-code"></i></div>
                        <div class="eh-guide-step-label">STEP 3</div>
                        <h3>Process Heart Card redemption.</h3>
                        <p>Verify the employee's valid QR code and process the redemption correctly to prevent duplicate or invalid redemption.</p>
                        <div class="eh-guide-highlight">
                            <i data-lucide="scan-line"></i>
                            <span>Always verify the card status before completing redemption.</span>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="4">
                        <div class="eh-guide-step-icon"><i data-lucide="chart-no-axes-combined"></i></div>
                        <div class="eh-guide-step-label">STEP 4</div>
                        <h3>Monitor recognition activity.</h3>
                        <p>Use dashboards and reports to review requests, Heart Cards, redemptions, and Core Value activity.</p>
                    </div>

                <?php elseif ($role === 'SYSTEM_ADMIN'): ?>

                    <div class="eh-guide-step active" data-step="0">
                        <div class="eh-guide-step-icon welcome"><i data-lucide="shield-check"></i></div>
                        <div class="eh-guide-step-label">WELCOME TO EHEART</div>
                        <h3>Manage eHeart with confidence.</h3>
                        <p>As System Administrator, you can manage system configuration and users while also accessing HR recognition and redemption features.</p>
                    </div>

                    <div class="eh-guide-step" data-step="1">
                        <div class="eh-guide-step-icon"><i data-lucide="users"></i></div>
                        <div class="eh-guide-step-label">SYSTEM ADMINISTRATION</div>
                        <h3>Manage system users.</h3>
                        <p>Assign the appropriate system roles and ensure authorized employees have the correct access.</p>
                    </div>

                    <div class="eh-guide-step" data-step="2">
                        <div class="eh-guide-step-icon"><i data-lucide="settings"></i></div>
                        <div class="eh-guide-step-label">SYSTEM ADMINISTRATION</div>
                        <h3>Maintain system settings.</h3>
                        <p>Configure department request limits and maintain the settings required for eHeart operations.</p>
                    </div>

                    <div class="eh-guide-step" data-step="3">
                        <div class="eh-guide-step-icon"><i data-lucide="shield-check"></i></div>
                        <div class="eh-guide-step-label">SYSTEM ADMINISTRATION</div>
                        <h3>Maintain secure access.</h3>
                        <p>Review user roles regularly and ensure employees only receive access appropriate to their responsibilities.</p>
                    </div>

                    <div class="eh-guide-step" data-step="4">
                        <div class="eh-guide-step-icon"><i data-lucide="activity"></i></div>
                        <div class="eh-guide-step-label">SYSTEM ADMINISTRATION</div>
                        <h3>Monitor the system.</h3>
                        <p>Review system activity, reports, and audit information to help maintain reliable eHeart operations.</p>
                    </div>

                    <div class="eh-guide-step" data-step="5">
                        <div class="eh-guide-step-icon"><i data-lucide="clipboard-list"></i></div>
                        <div class="eh-guide-step-label">HR OPERATIONS</div>
                        <h3>Review submitted requests.</h3>
                        <p>Check the employee, Core Values, recognition details, and other information before making a decision.</p>
                    </div>

                    <div class="eh-guide-step" data-step="6">
                        <div class="eh-guide-step-icon"><i data-lucide="badge-check"></i></div>
                        <div class="eh-guide-step-label">HR OPERATIONS</div>
                        <h3>Process the request.</h3>
                        <p>You can process a valid request for redemption, reject an invalid request, or return it to the manager for revision.</p>
                        <div class="eh-guide-statuses">
                            <span class="approved">Approved</span>
                            <span class="for-redemption">For Redemption</span>
                            <span class="redeemed">Redeemed</span>
                            <span class="revision">For Revision</span>
                            <span class="rejected">Rejected</span>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="7">
                        <div class="eh-guide-step-icon"><i data-lucide="qr-code"></i></div>
                        <div class="eh-guide-step-label">HR OPERATIONS</div>
                        <h3>Process Heart Card redemption.</h3>
                        <p>Verify the employee's valid QR code and process the redemption correctly to prevent duplicate or invalid redemption.</p>
                        <div class="eh-guide-highlight">
                            <i data-lucide="scan-line"></i>
                            <span>Always verify the card status before completing redemption.</span>
                        </div>
                    </div>

                    <div class="eh-guide-step" data-step="8">
                        <div class="eh-guide-step-icon"><i data-lucide="chart-no-axes-combined"></i></div>
                        <div class="eh-guide-step-label">HR OPERATIONS</div>
                        <h3>Monitor recognition activity.</h3>
                        <p>Use dashboards and reports to review requests, Heart Cards, redemptions, and Core Value activity.</p>
                    </div>

                <?php else: ?>

                    <div class="eh-guide-step active" data-step="0">
                        <div class="eh-guide-step-icon welcome"><i data-lucide="heart-handshake"></i></div>
                        <div class="eh-guide-step-label">WELCOME TO EHEART</div>
                        <h3>Recognition starts here.</h3>
                        <p>Use eHeart to access recognition and Heart Card activities available to your account.</p>
                    </div>

                    <div class="eh-guide-step" data-step="1">
                        <div class="eh-guide-step-icon"><i data-lucide="layout-dashboard"></i></div>
                        <div class="eh-guide-step-label">STEP 1</div>
                        <h3>Explore the system.</h3>
                        <p>Use the sidebar to navigate through the modules available to your role.</p>
                    </div>

                    <div class="eh-guide-step" data-step="2">
                        <div class="eh-guide-step-icon"><i data-lucide="bell"></i></div>
                        <div class="eh-guide-step-label">STEP 2</div>
                        <h3>Check notifications.</h3>
                        <p>Use the notification bell to stay informed about important system updates.</p>
                    </div>

                    <div class="eh-guide-step" data-step="3">
                        <div class="eh-guide-step-icon"><i data-lucide="circle-check"></i></div>
                        <div class="eh-guide-step-label">STEP 3</div>
                        <h3>Keep information accurate.</h3>
                        <p>Always verify information before submitting requests or updating records.</p>
                    </div>

                <?php endif; ?>
            </div>
        </div>

        <div class="eh-guide-footer">
            <div class="eh-guide-progress">
                <div class="eh-guide-progress-text" id="userGuideProgressText">Step 1</div>
                <div class="eh-guide-progress-track">
                    <span id="userGuideProgressBar"></span>
                </div>
            </div>
            <div class="eh-guide-controls">
                <button type="button" class="eh-guide-prev" id="userGuidePrev">
                    <i data-lucide="arrow-left"></i>
                    <span>Back</span>
                </button>
                <button type="button" class="eh-guide-next" id="userGuideNext">
                    <span>Next</span>
                    <i data-lucide="arrow-right"></i>
                </button>
                <button type="button" class="eh-guide-finish" id="userGuideDone" style="display:none;">
                    <i data-lucide="heart"></i>
                    <span>Got it!</span>
                </button>
            </div>
        </div>
    </div>
</div>