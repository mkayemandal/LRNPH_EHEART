<?php
$user = Auth::user();
$role = $user['role_code'] ?? 'MANAGER';
$activePage = $activePage ?? '';
function eh_nav_item(string $key, string $label, string $icon, string $href, string $active): void
{
    $cls = $active === $key ? 'eh-nav-item active' : 'eh-nav-item';
    echo '
        <a href="' . htmlspecialchars($href) . '" class="' . $cls . '" data-tooltip="' . htmlspecialchars($label) . '">
            <span class="nav-icon"><i data-lucide="' . htmlspecialchars($icon) . '"></i></span>
            <span class="nav-label">' . htmlspecialchars($label) . '</span>
        </a>
    ';
}
?>
<link rel="stylesheet" href="/eheart/styles/sidebar.css">
<aside class="eh-sidebar" id="sidebar">
    <div class="eh-sidebar-brand" id="sidebarBrand">
        <span class="heart-icon"><i data-lucide="heart"></i></span>
        <span class="brand-name"><span class="brand-e">e</span>Heart.</span>
        <button type="button" class="sidebar-collapse-btn" id="sidebarToggle" aria-label="Toggle sidebar" title="Collapse sidebar">
            <i data-lucide="panel-left-close"></i>
        </button>
    </div>
    <div class="eh-sidebar-nav">
        <!-- GENERAL -->
        <div class="eh-sidebar-section-label">General</div>
        <?php eh_nav_item('dashboard', 'Dashboard', 'layout-dashboard', '/eheart/pages/manager/dashboard.php', $activePage); ?>
        <?php eh_nav_item('heart-card', 'Heart Card', 'heart', '/eheart/pages/manager/heart_card.php', $activePage); ?>
        <?php if (in_array($role, ['HR_ADMIN', 'SYSTEM_ADMIN'], true)): ?>
            <?php eh_nav_item('redemption', 'Redemption', 'gift', '/eheart/pages/hr_admin/redemption.php', $activePage); ?>
        <?php endif; ?>
        <?php eh_nav_item('history', 'History', 'history', '/eheart/pages/manager/history.php', $activePage); ?>
        <!-- ADMINISTRATION -->
        <?php if (in_array($role, ['SYSTEM_ADMIN'], true)): ?>
            <div class="eh-sidebar-section-label administration-label">
                Administration
            </div>
            <?php if ($role === 'SYSTEM_ADMIN'): ?>
                <?php eh_nav_item('users', 'eHeart Users', 'users', '/eheart/pages/system_admin/users.php', $activePage); ?>
                <?php eh_nav_item('system-settings', 'System Settings', 'settings', '/eheart/pages/system_admin/system_settings.php', $activePage); ?>
                <?php eh_nav_item('department-quota', 'Department Quota', 'building-2', '/eheart/pages/system_admin/department_quota.php', $activePage); ?>
                <?php eh_nav_item('audit', 'Audit Logs', 'scroll-text', '/eheart/pages/hr_admin/audit.php', $activePage); ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="eh-sidebar-footer">
        <div class="eh-sidebar-it-logo" aria-label="IT Department">
            <img src="/eheart/assets/IT%20LOGO.png" alt="IT Department logo">
        </div>
    </div>
</aside>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="/eheart/scripts/sidebar.js"></script>