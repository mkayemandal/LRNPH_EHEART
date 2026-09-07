console.log('[eHeart] PN bridge file loaded. BUILD MARKER: v5-qr-big-inline-footer');

const PNNotificationBridge = {
    config: {
        eheartBaseUrl: window.EHEART_BRIDGE_CONFIG?.baseUrl || 'http://10.2.0.8/eheart',
        pnUserUrl: '/api/method/frappe.auth.get_logged_user',
        pollIntervalMs: 60000
    },

    state: {
        email: null,
        employee: null,
        notifications: [],
        shownNotificationIds: new Set(),
        pollTimer: null
    },

    async init() {
        try {
            this.ensureHost();

            const email = await this.getPNLoggedUser();

            if (!email) {
                console.log('[eHeart] No PN user detected.');
                return;
            }

            this.state.email = email;
            console.log('[eHeart] PN user:', email);

            const employee = await this.findEmployee(email);

            if (!employee) {
                console.log('[eHeart] PN user is not in masterlist.');
                return;
            }

            this.state.employee = employee;
            console.log('[eHeart] Employee:', employee.full_name);

            await this.refresh();

            this.state.pollTimer = window.setInterval(
                () => this.refresh().catch(error => console.error(
                    '[eHeart] Notification refresh failed:',
                    error
                )),
                this.config.pollIntervalMs
            );
        } catch (error) {
            console.error('[eHeart] PN notification bridge error:', error);
        }
    },

    async getPNLoggedUser() {
        const response = await fetch(
            this.config.pnUserUrl,
            {
                method: 'GET',
                credentials: 'include',
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(`Frappe returned HTTP ${response.status}`);
        }

        const data = await response.json();
        return data.message || null;
    },

    async findEmployee(email) {
        const identifyEmployeeUrl =
            `${this.config.eheartBaseUrl}/api/notifications/pn_user.php`;

        const response = await fetch(
            identifyEmployeeUrl,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email: email }),
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(`Employee lookup failed: HTTP ${response.status}`);
        }

        const data = await response.json();

        if (
            !data.success ||
            !data.data ||
            !data.data.employee_found
        ) {
            return null;
        }

        return data.data.employee;
    },

    async getUnreadNotifications(email) {
        const url =
            `${this.config.eheartBaseUrl}/api/notifications/pn_unread.php` +
            `?email=${encodeURIComponent(email)}`;

        const response = await fetch(
            url,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                cache: 'no-store'
            }
        );

        if (!response.ok) {
            throw new Error(`Notification lookup failed: HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success || !data.data) {
            return [];
        }

        return data.data.notifications || [];
    },

    async refresh() {
        if (!this.state.email) {
            return;
        }

        const notifications = await this.getUnreadNotifications(
            this.state.email
        );

        this.state.notifications = notifications;
        this.showNotifications(notifications);
    },

    ensureHost() {
        if (!document.getElementById('eheart-pn-notifications')) {
            const container = document.createElement('div');
            container.id = 'eheart-pn-notifications';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        const stylesheetId = 'eheart-pn-notification-styles';

        if (!document.getElementById(stylesheetId)) {
            const stylesheet = document.createElement('link');
            stylesheet.id = stylesheetId;
            stylesheet.rel = 'stylesheet';
            stylesheet.href =
                `${this.config.eheartBaseUrl}/styles/pn_notification.css`;

            document.head.appendChild(stylesheet);
        }
    },

    showNotifications(notifications) {
        notifications.forEach(notification => {
            const notificationId =
                Number(notification.notification_id);

            if (this.state.shownNotificationIds.has(notificationId)) {
                return;
            }

            this.state.shownNotificationIds.add(notificationId);
            this.createNotificationPopup(notification);
        });
    },

    /**
     * Pull a sender name out of the message body so it can be
     * highlighted, e.g. "...Heart Card from Juan Dela Cruz in
     * recognition..." -> "Juan Dela Cruz".
     */
    extractSenderName(message) {
        const match = String(message || '').match(
            /from\s+(.+?)\s+in recognition/i
        );

        return match ? match[1].trim() : null;
    },

    /**
     * Split the raw message into a body paragraph, with the
     * sender name (if found) wrapped for pink highlighting.
     */
    renderMessageHtml(message, senderName) {
        const escaped = this.escapeHtml(message);

        if (!senderName) {
            return escaped;
        }

        const escapedName = this.escapeHtml(senderName);

        return escaped.replace(
            escapedName,
            `<span class="eheart-pn-sender">${escapedName}</span>`
        );
    },

    getControlNumber(notification) {
        const raw =
            notification.resolved_reference_id ??
            notification.reference_id ??
            null;

        if (raw === null || raw === undefined || raw === '') {
            return null;
        }

        const numeric = String(raw).replace(/\D/g, '');

        if (!numeric) {
            return String(raw);
        }

        return `HC-${numeric.padStart(4, '0')}`;
    },

    isCardReceived(notification) {
        return String(notification.notification_type || '')
            .toUpperCase() === 'CARD_RECEIVED';
    },

    getAssetUrl(path) {
        if (!path) {
            return null;
        }

        const rawPath = String(path).trim();

        // Path from DB already root-relative (e.g. "/eheart/uploads/qr/35.png").
        // Resolve against page origin, not eheartBaseUrl, or segment doubles.
        try {
            if (/^https?:\/\//i.test(rawPath)) {
                return rawPath;
            }

            if (rawPath.startsWith('/')) {
                return new URL(rawPath, window.location.origin).toString();
            }

            return new URL(rawPath, `${this.config.eheartBaseUrl}/`).toString();
        } catch (error) {
            console.error('[eHeart] Invalid notification asset URL:', error, rawPath);
            return null;
        }
    },

    createNotificationPopup(notification) {
        const container = document.getElementById(
            'eheart-pn-notifications'
        );

        if (!container) {
            console.error(
                '[eHeart] Notification container not found.'
            );
            return;
        }

        const isCardReceived = this.isCardReceived(notification);
        const rawMessage = String(notification.message || '');
        const senderName = this.extractSenderName(rawMessage);
        const messageHtml = this.renderMessageHtml(rawMessage, senderName);
        const controlNumber = this.getControlNumber(notification);
        const qrCodePath = notification.qr_code_path || null;
        const qrCodeUrl = notification.qr_code_data ||
            this.getAssetUrl(qrCodePath);

        console.log(
            '[eHeart] QR debug -> has base64:',
            Boolean(notification.qr_code_data),
            '| raw path:', qrCodePath,
            '| resolved url:', qrCodeUrl
        );

        const popup = document.createElement('div');
        popup.className = 'eheart-pn-notification';

        const heartIconSvg = `
            <svg viewBox="0 0 24 24" width="20" height="20" fill="#ffffff">
                <path d="M12 21s-7.5-4.6-10.1-9.1C.4 9.1 1.3 5.7 4.3 4.4c2.2-1 4.7-.3 6.1 1.6.4.5 1.1.5 1.5 0 1.4-1.9 3.9-2.6 6.1-1.6 3 1.3 3.9 4.7 2.4 7.5C19.5 16.4 12 21 12 21z"/>
            </svg>`;

        const monitorIconSvg = `
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#ec1876" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="13" rx="2"/>
                <line x1="8" y1="21" x2="16" y2="21"/>
                <line x1="12" y1="17" x2="12" y2="21"/>
            </svg>`;

        const idIconSvg = `
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#2e9e5b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="15" rx="2.5"/>
                <circle cx="9" cy="11" r="2.2"/>
                <path d="M5.5 17c.6-2 2.1-3.2 3.5-3.2s2.9 1.2 3.5 3.2"/>
                <line x1="14.5" y1="9.5" x2="18" y2="9.5"/>
                <line x1="14.5" y1="13" x2="18" y2="13"/>
            </svg>`;

        const checkIconSvg = `
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>`;

        // Control number box + big QR sit side by side in one row.
        const controlRowHtml = `
            <div class="eheart-pn-control-row">
                ${controlNumber ? `
                <div class="eheart-pn-notification-control">
                    <div class="eheart-pn-notification-control-label-row">
                        <span class="eheart-pn-notification-box-icon">${monitorIconSvg}</span>
                        <span class="eheart-pn-notification-box-label">Heart Card Control Number</span>
                    </div>
                    <span class="eheart-pn-notification-box-value">${this.escapeHtml(controlNumber)}</span>
                </div>` : ''}

                ${qrCodeUrl ? `
                <div class="eheart-pn-notification-qr" id="eheart-qr-wrap-${notification.notification_id}">
                    <img
                        src="${qrCodeUrl}"
                        alt="Heart Card QR Code"
                        class="eheart-pn-qr-image"
                    />
                    <span class="eheart-pn-qr-caption">Show to HR</span>
                    <span class="eheart-pn-qr-error" style="display:none; color:#b3261e;">
                        Show ${this.escapeHtml(controlNumber || 'control number')} to HR instead.
                    </span>
                </div>` : ''}
            </div>
        `;

        popup.innerHTML = `
            <button
                type="button"
                class="eheart-pn-notification-close"
                aria-label="Close notification">
                &times;
            </button>

            <div class="eheart-pn-notification-header">
                <div class="eheart-pn-notification-icon">
                    ${heartIconSvg}
                </div>
                <div class="eheart-pn-notification-heading">
                    <span class="eheart-pn-notification-eyebrow">eHeart Recognition</span>
                    <h3 class="eheart-pn-notification-title">Congratulations!</h3>
                    <p class="eheart-pn-notification-subtitle">You received a Heart Card</p>
                </div>
            </div>

            <div class="eheart-pn-notification-body">
                ${messageHtml}
            </div>

            ${controlRowHtml}

            ${isCardReceived ? `
            <div class="eheart-pn-notification-hr">
                <div class="eheart-pn-notification-box-icon">${idIconSvg}</div>
                <div class="eheart-pn-notification-box-text">
                    <span class="eheart-pn-notification-box-label">Please proceed to HR and bring your Employee ID to redeem your Heart Card reward.</span>
                </div>
            </div>` : ''}

            <div class="eheart-pn-notification-footer">
                <span class="eheart-pn-notification-footer-msg">
                    <span class="eheart-pn-notification-footer-heart">${heartIconSvg.replace('width="20" height="20"', 'width="14" height="14"').replace('fill="#ffffff"', 'fill="#ec1876"')}</span>
                    Thank you for going above and beyond!
                </span>

                <button
                    type="button"
                    class="eheart-pn-notification-read">
                    ${checkIconSvg} Mark as read
                </button>
            </div>
        `;

        container.appendChild(popup);

        const qrImage = popup.querySelector('.eheart-pn-qr-image');

        if (qrImage) {
            qrImage.addEventListener('error', () => {
                console.error('[eHeart] QR image failed to load:', qrImage.src);
                qrImage.style.display = 'none';
                const caption = popup.querySelector('.eheart-pn-qr-caption');
                const errorLabel = popup.querySelector('.eheart-pn-qr-error');
                if (caption) caption.style.display = 'none';
                if (errorLabel) errorLabel.style.display = 'block';
            });
        }

        const closeButton = popup.querySelector(
            '.eheart-pn-notification-close'
        );

        const readButton = popup.querySelector(
            '.eheart-pn-notification-read'
        );

        closeButton.addEventListener('click', () => {
            popup.remove();
        });

        readButton.addEventListener('click', async () => {
            const success = await this.markAsRead(
                notification.notification_id
            );

            if (success) {
                popup.remove();
            }
        });
    },

    async markAsRead(notificationId) {
        if (!this.state.email) {
            return false;
        }

        try {
            const response = await fetch(
                `${this.config.eheartBaseUrl}/api/notifications/pn_mark_read.php`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        notification_id: Number(notificationId),
                        email: this.state.email
                    })
                }
            );

            if (!response.ok) {
                return false;
            }

            const data = await response.json();

            return (
                data.success === true &&
                data.data?.marked_read === true
            );
        } catch (error) {
            console.error(
                '[eHeart] Mark notification read failed:',
                error
            );

            return false;
        }
    },

    getDisplayMessage(notification) {
        const message = String(notification.message || '');

        if (
            this.isCardReceived(notification) &&
            !message.toLowerCase().includes('employee id')
        ) {
            return `${message} Congratulations! Please proceed to HR and bring your Employee ID to redeem your Heart Card.`;
        }

        return message;
    },

    escapeHtml(value) {
        const div = document.createElement('div');

        div.textContent =
            value == null ? '' : String(value);

        return div.innerHTML;
    }
};

const startPNNotificationBridge = () => {
    PNNotificationBridge.init();
};

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        startPNNotificationBridge,
        { once: true }
    );
} else {
    startPNNotificationBridge();
}