<?php
$allowedRoles = ['SYSTEM_ADMIN'];
require_once __DIR__ . '/../_guard.php';

$activePage = 'system-settings';
$pageTitle = 'System Settings';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/eheart/assets/cares_logo.png">

    <title>System Settings — eHeart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- GLOBAL STYLES -->
    <link rel="stylesheet" href="/eheart/styles/app.css">
    <link rel="stylesheet" href="/eheart/styles/topbar.css">
    <link rel="stylesheet" href="/eheart/styles/table.css">
    <link rel="stylesheet" href="/eheart/styles/heart_card.css">
    <link rel="stylesheet" href="/eheart/styles/department_quota.css">
    <link rel="stylesheet" href="/eheart/styles/responsive.css">
    <!-- SYSTEM SETTINGS -->
    <link rel="stylesheet" href="/eheart/styles/system_settings.css">
</head>

<body>

    <div class="eh-app">

        <!-- SIDEBAR -->
        <?php require __DIR__ . '/../../components/layout/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="eh-main">

            <!-- TOPBAR -->
            <?php require __DIR__ . '/../../components/layout/topbar.php'; ?>

            <!-- PAGE CONTENT -->
            <div class="eh-content">

                <div class="eh-settings-page">

                    <!-- ============================================
                         SYSTEM SETTINGS HERO
                    ============================================= -->
                    <div class="eh-settings-hero">
                        <div class="eh-settings-hero-content">

                            <!-- HERO ICON -->
                            <div class="eh-settings-hero-icon">
                                <i data-lucide="sliders-horizontal"></i>
                            </div>

                            <!-- HERO TEXT -->
                            <div>
                                <h2>System Configuration</h2>
                                <p>Manage EHEART limits and system-wide recognition rules.</p>
                            </div>

                        </div>
                    </div>

                    <!-- ============================================
                         SYSTEM SETTINGS LIST
                    ============================================= -->
                    <div id="settingsList" class="eh-settings-container">
                        <!-- System setting cards are injected by system_settings.js -->
                    </div>

                </div>

            </div>
            <?php require __DIR__ . '/../../components/layout/footer.php'; ?>
        </div>

    </div>

    <script src="/eheart/scripts/app.js"></script>
    <script src="/eheart/scripts/topbar.js"></script>
    <script src="/eheart/scripts/system_settings.js"></script>

</body>

</html>