document.addEventListener('DOMContentLoaded', () => {

    const list = document.getElementById('notifPageList');

    if (!list) {
        return;
    }

    let allNotifications = [];
    let currentFilter = 'all';

    const allTab = document.getElementById('notifAllTab');
    const unreadTab = document.getElementById('notifUnreadTab');
    const allCount = document.getElementById('notifAllCount');
    const unreadCount = document.getElementById('notifUnreadCount');
    const markAllBtn = document.getElementById('notifMarkAllBtn');
    const refreshBtn = document.getElementById('notifRefreshBtn');

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function parseDate(value) {
        if (!value) {
            return null;
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date;
    }

    function getCreatedDate(notification) {
        return (
            notification.created_at ??
            notification.date_created ??
            notification.createdAt ??
            notification.date ??
            null
        );
    }

    function timeAgo(dateValue) {
        const date = parseDate(dateValue);

        if (!date) {
            return '';
        }

        const diff = Math.floor((Date.now() - date.getTime()) / 1000);

        if (diff < 0) {
            return 'just now';
        }

        if (diff < 60) {
            return 'just now';
        }

        if (diff < 3600) {
            return Math.floor(diff / 60) + 'm ago';
        }

        if (diff < 86400) {
            return Math.floor(diff / 3600) + 'h ago';
        }

        if (diff < 604800) {
            return Math.floor(diff / 86400) + 'd ago';
        }

        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatDate(dateValue) {
        const date = parseDate(dateValue);

        if (!date) {
            return '';
        }

        return date.toLocaleString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function isToday(dateValue) {
        const date = parseDate(dateValue);

        if (!date) {
            return false;
        }

        const now = new Date();

        return (
            date.getFullYear() === now.getFullYear() &&
            date.getMonth() === now.getMonth() &&
            date.getDate() === now.getDate()
        );
    }

    function isNotificationRead(notification) {
        if (
            notification.is_read === true ||
            notification.is_read === 1 ||
            notification.is_read === '1'
        ) {
            return true;
        }

        if (
            notification.read === true ||
            notification.read === 1 ||
            notification.read === '1'
        ) {
            return true;
        }

        if (
            notification.read_at !== null &&
            notification.read_at !== undefined &&
            notification.read_at !== ''
        ) {
            return true;
        }

        if (
            typeof notification.status === 'string' &&
            notification.status.toLowerCase() === 'read'
        ) {
            return true;
        }

        return false;
    }

    function getNotificationId(notification) {
        return (
            notification.notification_id ??
            notification.id ??
            notification.notif_id ??
            null
        );
    }

    function getNotificationType(notification) {
        const rawType = String(
            notification.type ??
            notification.notification_type ??
            notification.status ??
            ''
        ).toLowerCase();

        const title = String(
            notification.title ??
            notification.subject ??
            ''
        ).toLowerCase();

        const message = String(
            notification.message ??
            notification.body ??
            ''
        ).toLowerCase();

        const text = `${rawType} ${title} ${message}`;

        if (text.includes('approved') || text.includes('approval')) {
            return { icon: 'check-circle-2', className: 'icon-success' };
        }

        if (text.includes('rejected') || text.includes('reject')) {
            return { icon: 'x-circle', className: 'icon-danger' };
        }

        if (text.includes('revision') || text.includes('revise')) {
            return { icon: 'refresh-cw', className: 'icon-warning' };
        }

        if (
            text.includes('redeemed') ||
            text.includes('redemption') ||
            text.includes('redeem')
        ) {
            return { icon: 'gift', className: 'icon-success' };
        }

        if (text.includes('expired') || text.includes('expiry')) {
            return { icon: 'clock-3', className: 'icon-danger' };
        }

        if (text.includes('pending') || text.includes('submitted')) {
            return { icon: 'hourglass', className: 'icon-info' };
        }

        if (text.includes('heart card') || text.includes('recognition')) {
            return { icon: 'heart', className: '' };
        }

        return { icon: 'bell', className: '' };
    }

    function getNotificationUrl(notification) {
        const referenceType = String(notification.reference_type ?? '').toLowerCase();
        const referenceId = notification.resolved_reference_id ?? notification.reference_id;

        if (referenceId === null || referenceId === undefined || referenceId === '') {
            return null;
        }

        const routes = {
            heart_card: '/eheart/pages/manager/heart_card.php',
            redemption: '/eheart/pages/manager/heart_card.php',
            gift_certificate: '/eheart/pages/manager/heart_card.php',
            request: '/eheart/pages/manager/heart_card.php'
        };
        const path = routes[referenceType];

        if (!path) {
            return null;
        }

        return `${path}?reference_id=${encodeURIComponent(String(referenceId))}`;
    }

    function updateCounts() {
        const total = allNotifications.length;

        const unread = allNotifications.filter(
            notification => !isNotificationRead(notification)
        ).length;

        if (allCount) {
            allCount.textContent = total;
        }

        if (unreadCount) {
            unreadCount.textContent = unread;
            unreadCount.classList.toggle('has-unread', unread > 0);
        }

    }

    function groupNotifications(items) {
        const today = [];
        const earlier = [];

        items.forEach(notification => {
            const date = getCreatedDate(notification);

            if (isToday(date)) {
                today.push(notification);
            } else {
                earlier.push(notification);
            }
        });

        return { today, earlier };
    }

    function renderNotification(notification) {
        const title = escapeHtml(
            notification.title ?? notification.subject ?? 'Notification'
        );

        const message = escapeHtml(
            notification.message ?? notification.body ?? ''
        );

        const createdAt = getCreatedDate(notification);
        const when = timeAgo(createdAt);
        const fullDate = formatDate(createdAt);
        const read = isNotificationRead(notification);
        const id = getNotificationId(notification);
        const type = getNotificationType(notification);
        const url = getNotificationUrl(notification);

        return `
            <div class="eh-notif-page-item ${read ? '' : 'is-unread'}${url ? ' is-clickable' : ''}" data-notification-id="${escapeHtml(id ?? '')}"${url ? ` data-notification-url="${escapeHtml(url)}" tabindex="0" role="link"` : ''}>

                <div class="eh-notif-timeline-dot"></div>

                <span class="eh-notif-page-icon ${type.className}">
                    <i data-lucide="${type.icon}"></i>
                </span>

                <div class="eh-notif-page-body">

                    <div class="eh-notif-page-title-row">
                        <div class="eh-notif-page-title">${title}</div>
                        ${!read ? `<span class="eh-notif-unread-label">New</span>` : ''}
                    </div>

                    ${message ? `<div class="eh-notif-page-msg">${message}</div>` : ''}

                    <div class="eh-notif-page-meta">
                        <span class="eh-notif-page-time">${escapeHtml(when)}</span>
                        ${
                            fullDate && when !== fullDate
                                ? `<span class="eh-notif-page-date" title="${escapeHtml(fullDate)}">${escapeHtml(fullDate)}</span>`
                                : ''
                        }
                    </div>

                </div>

                <div class="eh-notif-view">
                    <span>${read ? 'View' : 'Review'}</span>
                    <i data-lucide="chevron-right"></i>
                </div>

            </div>
        `;
    }

    function renderGroup(title, items) {
        if (!items.length) {
            return '';
        }

        return `
            <section class="eh-notif-group">
                <div class="eh-notif-group-title">${title}</div>
                <div class="eh-notif-group-items">
                    ${items.map(renderNotification).join('')}
                </div>
            </section>
        `;
    }

    function renderEmpty(unreadOnly = false) {
        list.innerHTML = `
            <div class="eh-notif-empty">
                <div class="eh-notif-empty-icon">
                    <i data-lucide="heart"></i>
                </div>
                <h3>${unreadOnly ? 'You are all caught up!' : 'No recognition activity yet'}</h3>
                <p>${
                    unreadOnly
                        ? 'There are no unread notifications waiting for you.'
                        : 'When something happens with your Heart Cards, your recognition activity will appear here.'
                }</p>
            </div>
        `;

        createIcons();
    }

    function renderError() {
        list.innerHTML = `
            <div class="eh-notif-error">
                <div class="eh-notif-error-icon">
                    <i data-lucide="wifi-off"></i>
                </div>
                <h3>Unable to load notifications</h3>
                <p>Something went wrong while loading your recognition activity.</p>
                <button type="button" class="eh-notif-retry" id="notifRetryBtn">Try again</button>
            </div>
        `;

        createIcons();

        const retry = document.getElementById('notifRetryBtn');

        if (retry) {
            retry.addEventListener('click', loadNotifications);
        }
    }

    function render(items) {
        allNotifications = Array.isArray(items) ? items : [];

        updateCounts();

        let filtered = allNotifications;

        if (currentFilter === 'unread') {
            filtered = allNotifications.filter(
                notification => !isNotificationRead(notification)
            );
        }

        if (!filtered.length) {
            renderEmpty(currentFilter === 'unread');
            return;
        }

        filtered = [...filtered].sort((a, b) => {
            const dateA = parseDate(getCreatedDate(a));
            const dateB = parseDate(getCreatedDate(b));

            if (!dateA && !dateB) {
                return 0;
            }

            if (!dateA) {
                return 1;
            }

            if (!dateB) {
                return -1;
            }

            return dateB.getTime() - dateA.getTime();
        });

        const groups = groupNotifications(filtered);

        list.innerHTML = `
            ${renderGroup('Today', groups.today)}
            ${renderGroup('Earlier', groups.earlier)}
        `;

        createIcons();
        attachNotificationEvents();
    }

    function createIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    async function markNotificationRead(notificationId) {
        if (!notificationId) {
            return true;
        }

        try {
            const response = await fetch('/eheart/api/notifications/mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: Number(notificationId) })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Unable to mark notification as read');
            }

            return true;

        } catch (error) {
            console.error('Mark notification read failed:', error);
            return false;
        }
    }

    function attachNotificationEvents() {
        const items = list.querySelectorAll('.eh-notif-page-item');

        items.forEach(item => {
            const openNotification = async () => {
                const id = item.dataset.notificationId;

                const notification = allNotifications.find(
                    n => String(getNotificationId(n)) === String(id)
                );
                const url = item.dataset.notificationUrl;

                if (!notification) {
                    return;
                }

                if (!isNotificationRead(notification)) {
                    item.classList.remove('is-unread');

                    const label = item.querySelector('.eh-notif-unread-label');

                    if (label) {
                        label.remove();
                    }

                    const success = await markNotificationRead(id);

                    if (success) {
                        if (notification.is_read !== undefined) {
                            notification.is_read = 1;
                        }

                        if (notification.read !== undefined) {
                            notification.read = 1;
                        }

                        notification.read_at = new Date().toISOString();

                        updateCounts();

                        if (currentFilter === 'unread') {
                            render(allNotifications);
                        }
                    }
                }

                if (url) {
                    window.location.assign(url);
                }
            };

            item.addEventListener('click', openNotification);
            item.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openNotification();
                }
            });
        });
    }

    async function loadNotifications() {
        if (refreshBtn) {
            refreshBtn.classList.add('is-loading');
            refreshBtn.disabled = true;
        }

        list.innerHTML = `
            <div class="eh-loading-component" role="status" aria-live="polite">
                <span class="eh-loading-spinner" aria-hidden="true"></span>
                <span>Loading your recognition activity...</span>
            </div>
        `;

        createIcons();

        try {
            const response = await fetch('/eheart/api/notifications/list.php', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            const items = data.data ?? data.result ?? [];

            render(items);

        } catch (error) {
            console.error('Notification page load failed:', error);
            renderError();

        } finally {
            if (refreshBtn) {
                refreshBtn.classList.remove('is-loading');
                refreshBtn.disabled = false;
            }
        }
    }

    async function markAllAsRead() {
        const unread = allNotifications.filter(
            notification => !isNotificationRead(notification)
        );

        if (!unread.length) {
            if (window.EHeart && typeof EHeart.toast === 'function') {
                EHeart.toast('You have no unread notifications.', 'info');
            }

            return;
        }

        if (markAllBtn) {
            markAllBtn.disabled = true;
        }

        try {
            const response = await fetch('/eheart/api/notifications/mark_all_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Unable to mark notifications as read');
            }

            allNotifications.forEach(notification => {
                if (notification.is_read !== undefined) {
                    notification.is_read = 1;
                }

                if (notification.read !== undefined) {
                    notification.read = 1;
                }

                notification.read_at = new Date().toISOString();
            });

            updateCounts();
            render(allNotifications);

            if (window.EHeart && typeof EHeart.toast === 'function') {
                EHeart.toast('All notifications marked as read.', 'success');
            }

        } catch (error) {
            console.error('Mark all notifications read failed:', error);

            if (window.EHeart && typeof EHeart.toast === 'function') {
                EHeart.toast('Unable to mark notifications as read.', 'error');
            }

        } finally {
            if (markAllBtn) {
                markAllBtn.disabled = false;
            }
        }
    }

    function setFilter(filter) {
        currentFilter = filter;

        if (allTab) {
            allTab.classList.toggle('active', filter === 'all');
        }

        if (unreadTab) {
            unreadTab.classList.toggle('active', filter === 'unread');
        }

        render(allNotifications);
    }

    if (allTab) {
        allTab.addEventListener('click', () => {
            setFilter('all');
        });
    }

    if (unreadTab) {
        unreadTab.addEventListener('click', () => {
            setFilter('unread');
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', markAllAsRead);
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadNotifications);
    }

    loadNotifications();

});