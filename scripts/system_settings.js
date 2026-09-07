const SystemSettingsPage = {

    settings: [],
    editingKey: null,

    async load() {
        try {
            const res = await EHeart.api('/eheart/api/system_settings/list.php');
            this.settings = res.data || [];
            this.editingKey = null;
            this.render();
        } catch (e) {
            console.error('Failed to load settings:', e);
            EHeart.toast('Failed to load system settings.', 'error');
        }
    },

    render() {
        const wrap = document.getElementById('settingsList');
        if (!wrap) return;

        if (!this.settings.length) {
            wrap.innerHTML = `
                <div class="eh-settings-empty">
                    <i data-lucide="settings"></i>
                    <p>No system settings available.</p>
                </div>
            `;
            this.refreshIcons();
            return;
        }

        wrap.innerHTML = this.settings.map(setting => {
            const isEditing = this.editingKey === setting.setting_key;
            const unit = setting.setting_key === 'MANAGER_MONTHLY_REQUEST_LIMIT' ? 'requests' : 'cards';

            return `
                <div class="eh-setting-card ${isEditing ? 'is-editing' : ''}">

                    <!-- TOP -->
                    <div class="eh-setting-top">
                        <div class="eh-setting-icon">
                            <i data-lucide="${this.getIcon(setting.setting_key)}"></i>
                        </div>
                        <div class="eh-setting-status">
                            <span class="eh-setting-status-dot"></span>
                            Active
                        </div>
                    </div>

                    <!-- CONTENT -->
                    <div class="eh-setting-content">
                        <h3>${setting.label}</h3>
                        <p>${setting.description}</p>
                    </div>

                    <!-- LIMIT -->
                    <div class="eh-setting-limit-box">
                        <div>
                            <span class="eh-setting-limit-label">Current Monthly Limit</span>
                        </div>

                        <div>
                            ${isEditing
                    ? `
                                        <input
                                            type="number"
                                            min="1"
                                            id="setting-${setting.setting_key}"
                                            class="eh-setting-input"
                                            value="${setting.setting_value}"
                                        >
                                    `
                    : `
                                        <span class="eh-setting-number">
                                            ${setting.setting_value}
                                            <span>${unit}</span>
                                        </span>
                                    `
                }
                        </div>
                    </div>

                    <!-- FOOTER -->
                    <div class="eh-setting-footer">
                        <div class="eh-setting-updated">
                            ${isEditing ? 'Editing configuration' : `Per month • ${unit}`}
                        </div>

                        <div class="eh-setting-buttons">
                            ${isEditing
                    ? `
                                        <button type="button" class="eh-setting-cancel-btn" onclick="SystemSettingsPage.cancelEdit()">
                                            Cancel
                                        </button>
                                        <button type="button" class="eh-setting-save-btn" onclick="SystemSettingsPage.save('${setting.setting_key}')">
                                            <i data-lucide="save"></i>
                                            Save
                                        </button>
                                    `
                    : `
                                        <button type="button" class="eh-setting-edit-btn" onclick="SystemSettingsPage.edit('${setting.setting_key}')">
                                            <i data-lucide="pencil"></i>
                                            Edit
                                        </button>
                                    `
                }
                        </div>
                    </div>

                </div>
            `;
        }).join('');

        this.refreshIcons();
    },

    edit(key) {
        this.editingKey = key;
        this.render();

        setTimeout(() => {
            const input = document.getElementById('setting-' + key);
            if (input) {
                input.focus();
                input.select();
            }
        }, 100);
    },

    cancelEdit() {
        this.editingKey = null;
        this.render();
    },

    async save(key) {
        const input = document.getElementById('setting-' + key);
        if (!input) return;

        const value = parseInt(input.value, 10);

        if (!Number.isInteger(value) || value <= 0) {
            EHeart.toast('Value must be greater than 0.', 'error');
            input.focus();
            return;
        }

        try {
            await EHeart.api('/eheart/api/system_settings/update.php', {
                method: 'POST',
                body: {
                    setting_key: key,
                    setting_value: value
                }
            });

            EHeart.toast('System setting updated successfully.', 'success');
            await this.load();

        } catch (e) {
            console.error('Failed to save setting:', e);
            EHeart.toast('Failed to update system setting.', 'error');
        }
    },

    getIcon(key) {
        const icons = {
            MANAGER_MONTHLY_REQUEST_LIMIT: 'users-round',
            EMPLOYEE_MONTHLY_EHEART_LIMIT: 'heart'
        };
        return icons[key] || 'settings';
    },

    refreshIcons() {
        if (window.lucide) {
            lucide.createIcons();
        }
    },

    init() {
        this.load();
    }

};

document.addEventListener('DOMContentLoaded', () => {
    SystemSettingsPage.init();
});