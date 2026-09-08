<?php
require_once __DIR__ . '/../../utils/bootstrap.php';
require_once __DIR__ . '/../../components/ui/loading.php';

$user = Auth::requireLogin();
$activePage = 'notifications';
$pageTitle = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/eheart/assets/cares_logo.png">
    <title>Notifications — eHeart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/eheart/styles/app.css">
    <link rel="stylesheet" href="/eheart/styles/topbar.css">
    <link rel="stylesheet" href="/eheart/styles/notifications.css">
    <link rel="stylesheet" href="/eheart/styles/responsive.css">
</head>

<body>
    <div class="eh-app">
        <?php require __DIR__ . '/../../components/layout/sidebar.php'; ?>
        <main class="eh-main">
            <div class="eh-notif-page">

                <!-- PAGE HERO -->
                <section class="eh-notif-hero">
                    <div class="eh-notif-hero-content">
                        <div class="eh-notif-hero-icon">
                            <i data-lucide="heart"></i>
                        </div>
                        <div class="eh-notif-hero-text">
                            <span class="eh-notif-eyebrow">eHEART ACTIVITY</span>
                            <h1>Notifications</h1>
                            <p>Stay updated on your Heart Cards and recognition activities.</p>
                        </div>
                    </div>

                    <div class="eh-notif-hero-decoration">
                        <div class="eh-notif-pulse-circle pulse-one"></div>
                        <div class="eh-notif-pulse-circle pulse-two"></div>
                        <div class="eh-notif-pulse-circle pulse-three"></div>
                        <i data-lucide="heart"></i>
                    </div>
                </section>

                <!-- TOOLBAR -->
                <section class="eh-notif-toolbar">
                    <div class="eh-notif-tabs">
                        <button type="button" class="eh-notif-tab active" data-filter="all" id="notifAllTab">
                            <span>All</span>
                            <span class="eh-notif-tab-count" id="notifAllCount">0</span>
                        </button>

                        <button type="button" class="eh-notif-tab" data-filter="unread" id="notifUnreadTab">
                            <span>Unread</span>
                            <span class="eh-notif-tab-count eh-notif-unread-count" id="notifUnreadCount">0</span>
                        </button>
                    </div>

                    <div class="eh-notif-actions">
                        <button type="button" class="eh-notif-action-btn" id="notifRefreshBtn" title="Refresh notifications">
                            <i data-lucide="refresh-cw"></i>
                            <span>Refresh</span>
                        </button>

                        <button type="button" class="eh-notif-action-btn eh-notif-mark-all" id="notifMarkAllBtn">
                            <i data-lucide="check-check"></i>
                            <span>Mark all as read</span>
                        </button>
                    </div>
                </section>

                <!-- NOTIFICATIONS -->
                <section class="eh-notif-content">
                    <div class="eh-notif-page-list" id="notifPageList">
                        <?= eh_loading('Loading your recognition activity...') ?>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="/eheart/scripts/app.js" defer></script>
    <script src="/eheart/scripts/topbar.js"></script>
    <script src="/eheart/scripts/sidebar.js"></script>
    <script src="/eheart/scripts/notifications.js"></script>
    <script src="/eheart/scripts/idle_timer.js"></script>
</body>

</html>