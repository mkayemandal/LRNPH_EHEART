const UsersPage = {
    allUsers: [],
    filters: { q: '', role: '', status: '' },
    page: 1,
    pageSize: 15,

    getSearchableText(u) {
        return [u.biometric_id, u.full_name, u.role_name]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
    },

    applyFilters() {
        const q = this.filters.q.toLowerCase().trim();

        return this.allUsers.filter(u => {
            if (q && !this.getSearchableText(u).includes(q)) return false;
            if (this.filters.role && u.role_name !== this.filters.role) return false;
            if (this.filters.status !== '' && Number(!!Number(u.is_active)) !== Number(this.filters.status)) return false;
            return true;
        });
    },

    render() {
        const filtered = this.applyFilters();
        const totalPages = Math.max(1, Math.ceil(filtered.length / this.pageSize));

        if (this.page > totalPages) this.page = totalPages;

        const start = (this.page - 1) * this.pageSize;
        const list = filtered.slice(start, start + this.pageSize);
        const tbody = document.getElementById('usersBody');

        tbody.innerHTML = list.length
            ? list.map(u => {
                const active = Number(u.is_active) === 1;
                const photoUrl = u.employee_id ? `http://10.2.0.8/lrnph/emp_photos/${u.employee_id}.jpg` : '';
                return `
                <tr>
                    <td>${u.biometric_id}</td>
                    <td>
                        <div class="eh-table-employee">
                            ${photoUrl
                        ? `<img src="${photoUrl}" alt="${u.full_name || ''}" class="eh-table-employee-photo" onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">`
                        : `<img src="/eheart/assets/default_avatar.jpg" alt="" class="eh-table-employee-photo">`
                    }
                            <span>${u.full_name || '—'}</span>
                        </div>
                    </td>
                    <td>${u.department || '—'}</td>
                    <td>${u.email || '—'}</td>
                    <td>${u.role_name || '—'}</td>
                    <td>
                        ${u.request_quota
                        ? `${u.request_quota.used}/${u.request_quota.limit} used, ${u.request_quota.remaining} left`
                        : '—'
                    }
                    </td>
                    <td>
                        ${active
                        ? `<span class="eh-badge eh-badge-approved"><span class="eh-dot"></span>Active</span>`
                        : `<span class="eh-badge eh-badge-rejected"><span class="eh-dot"></span>Inactive</span>`
                    }
                    </td>
                    <td>${u.last_login_at ? EHeart.formatDate(u.last_login_at) : '—'}</td>
                    <td class="eh-col-actions">
                        <button
                            class="eh-icon-btn action-edit"
                            data-user-id="${u.user_id}"
                            title="Edit"
                        >
                            <i data-lucide="pencil"></i>
                        </button>
                        <button
                            class="eh-icon-btn ${active ? 'action-deactivate' : 'action-activate'}"
                            onclick="toggleUser(${u.user_id}, ${active ? 0 : 1})"
                            title="${active ? 'Deactivate' : 'Activate'}"
                        >
                            <i data-lucide="${active ? 'user-x' : 'user-check'}"></i>
                        </button>
                    </td>
                </tr>
            `;
            }).join('')
            : `
            <tr>
                <td colspan="9">
                    <div class="eh-empty">
                        <div class="eh-empty-icon"><i data-lucide="search-x"></i></div>
                        <div class="eh-empty-title">No users found</div>
                        <div class="eh-empty-text">Try adjusting your search or filters.</div>
                    </div>
                </td>
            </tr>
        `;

        document.getElementById('usersCount').textContent = `Showing ${list.length} of ${filtered.length} users`;
        this.renderPagination(totalPages);

        tbody.querySelectorAll('.action-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.dataset.userId);
                const u = this.allUsers.find(x => Number(x.user_id) === id);
                if (u) openEditUser(u);
            });
        });

        if (window.lucide) lucide.createIcons();
    },

    renderPagination(totalPages) {
        EHPagination.render({
            container: document.getElementById('usersPagination'),
            gotoInputId: 'usersGotoPage',
            totalPages,
            currentPage: this.page,
            onChange: (page) => {
                this.page = page;
                this.render();
                document.querySelector('.eh-table-scroll')?.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    },

    updateClearBtn() {
        const btn = document.getElementById('usersClearFiltersBtn');
        if (!btn) return;

        const hasFilter = !!this.filters.q || !!this.filters.role || this.filters.status !== '';
        btn.classList.toggle('hidden', !hasFilter);
    },

    async load() {
        try {
            const res = await EHeart.api('/eheart/api/users/list.php');
            this.allUsers = res.data || [];
            this.render();
        } catch (e) {
            console.error('Failed to load users:', e);
        }
    },

    bindEvents() {
        const searchInput = document.getElementById('userSearch');
        const searchClear = document.getElementById('userSearchClear');

        searchInput?.addEventListener('input', (e) => {
            this.filters.q = e.target.value.trim();
            this.page = 1;
            searchClear?.classList.toggle('visible', !!this.filters.q);
            this.updateClearBtn();
            this.render();
        });

        searchClear?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            this.filters.q = '';
            this.page = 1;
            searchClear.classList.remove('visible');
            this.updateClearBtn();
            this.render();
        });

        document.getElementById('roleFilter')?.addEventListener('change', (e) => {
            this.filters.role = e.target.value;
            this.page = 1;
            this.updateClearBtn();
            this.render();
        });

        document.getElementById('statusFilter')?.addEventListener('change', (e) => {
            this.filters.status = e.target.value;
            this.page = 1;
            this.updateClearBtn();
            this.render();
        });

        document.getElementById('usersClearFiltersBtn')?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            searchClear?.classList.remove('visible');
            document.getElementById('roleFilter')?.ehDropdown?.reset();
            document.getElementById('statusFilter')?.ehDropdown?.reset();
            this.filters = { q: '', role: '', status: '' };
            this.page = 1;
            this.updateClearBtn();
            this.render();
        });
    },

    init() {
        this.bindEvents();
        this.updateClearBtn();
        this.load();
    }
};

async function toggleUser(id, active) {
    try {
        await EHeart.api('/eheart/api/users/toggle_active.php', {
            method: 'POST',
            body: { user_id: id, is_active: active }
        });

        EHeart.toast(active ? 'User activated.' : 'User deactivated.', 'success');
        UsersPage.load();
    } catch (e) {
        console.error('Failed to update user:', e);
    }
}

/* =========================================
   EDIT USER
========================================= */

function openEditUser(u) {
    document.getElementById('editUserId').value = u.user_id;
    document.getElementById('editUserFullName').value = u.full_name || '';
    document.getElementById('editUserDepartment').value = u.department || '';
    document.getElementById('editUserEmail').value = u.email || '';

    const roleSelect = document.getElementById('editUserRoleId');
    if (roleSelect) {
        [...roleSelect.options].forEach(opt => {
            opt.selected = opt.textContent.trim() === u.role_name;
        });
    }

    EHeart.openModal('editUserModal');
}

document.getElementById('editUserForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(e.target);

    try {
        await EHeart.api('/eheart/api/users/update.php', {
            method: 'POST',
            body: Object.fromEntries(fd)
        });

        EHeart.toast('User updated.', 'success');
        EHeart.closeModal('editUserModal');
        UsersPage.load();
    } catch (err) {
        console.error('Failed to update user:', err);
    }
});

/* =========================================
   ADD USER - MASTERLIST LOOKUP
========================================= */

const AddUserLookup = {
    lookupTimer: null,
    valid: false,

    escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    fillFields(emp) {
        const biometricInput = document.getElementById('addUserBiometricId');
        const employeeIdInput = document.getElementById('addUserEmployeeId');
        const fullNameInput = document.getElementById('addUserFullName');
        const departmentInput = document.getElementById('addUserDepartment');
        const emailInput = document.getElementById('addUserEmail');

        if (biometricInput) biometricInput.value = emp.biometric_id || '';
        if (employeeIdInput) employeeIdInput.value = emp.employee_id || '';
        if (fullNameInput) fullNameInput.value = emp.full_name || '';
        if (departmentInput) departmentInput.value = emp.department || '';
        if (emailInput) emailInput.value = emp.email || '';

        this.valid = true;
    },

    clearFields() {
        const departmentInput = document.getElementById('addUserDepartment');
        const emailInput = document.getElementById('addUserEmail');
        const employeeIdInput = document.getElementById('addUserEmployeeId');
        if (departmentInput) departmentInput.value = '';
        if (emailInput) emailInput.value = '';
        if (employeeIdInput) employeeIdInput.value = '';
        this.valid = false;
    },

    async searchEmployees(query) {
        const status = document.getElementById('addUserLookupStatus');
        const message = document.getElementById('addUserLookupMessage');
        const box = document.getElementById('addUserNameSuggestions');
        const nameInput = document.getElementById('addUserFullName');

        if (!box) return;

        status?.classList.remove('hidden');
        status?.classList.add('loading');
        message?.classList.add('hidden');
        if (message) message.textContent = '';

        try {
            const url = '/eheart/api/users/lookup_employee.php?q=' + encodeURIComponent(query);
            const res = await EHeart.api(url);
            const employees = Array.isArray(res.data) ? res.data : (res.data ? [res.data] : []);

            if (!employees.length) {
                box.innerHTML = '';
                box.classList.add('hidden');

                if (message) {
                    message.textContent = 'No employee found.';
                    message.classList.remove('hidden');
                }

                nameInput?.classList.add('eh-input-error');
                return;
            }

            box.innerHTML = employees.map(emp => {
                const photoUrl = emp.employee_id
                    ? `http://10.2.0.8/lrnph/emp_photos/${emp.employee_id}.jpg`
                    : '';
                return `
        <div class="eh-employee-suggestion-item"
             data-biometric-id="${this.escapeHtml(emp.biometric_id)}"
             data-employee-id="${this.escapeHtml(emp.employee_id)}"
             data-full-name="${this.escapeHtml(emp.full_name)}"
             data-department="${this.escapeHtml(emp.department)}"
             data-email="${this.escapeHtml(emp.email)}"
             role="option"
             tabindex="0">
            <img
                src="${photoUrl || '/eheart/assets/default_avatar.jpg'}"
                alt=""
                class="eh-employee-suggestion-photo"
                onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">
            <div class="eh-employee-suggestion-text">
                <div class="eh-employee-suggestion-name">${this.escapeHtml(emp.full_name) || '—'}</div>
                <div class="eh-employee-suggestion-department">${this.escapeHtml(emp.department) || '—'}</div>
            </div>
        </div>
    `;
            }).join('');

            box.classList.remove('hidden');

            box.querySelectorAll('.eh-employee-suggestion-item').forEach(item => {
                item.addEventListener('click', () => this.selectSuggestion(item));
                item.addEventListener('keydown', ev => {
                    if (ev.key === 'Enter' || ev.key === ' ') {
                        ev.preventDefault();
                        this.selectSuggestion(item);
                    }
                });
            });

            nameInput?.classList.remove('eh-input-error');
        } catch (error) {
            box.innerHTML = '';
            box.classList.add('hidden');

            const errorMessage = error?.message || 'Employee not found.';

            if (message) {
                message.textContent = errorMessage;
                message.classList.remove('hidden');
            }

            nameInput?.classList.add('eh-input-error');
        } finally {
            status?.classList.add('hidden');
            status?.classList.remove('loading');
        }
    },

    selectSuggestion(item) {
        this.fillFields({
            biometric_id: item.dataset.biometricId,
            employee_id: item.dataset.employeeId,
            full_name: item.dataset.fullName,
            department: item.dataset.department,
            email: item.dataset.email
        });

        document.getElementById('addUserLookupMessage')?.classList.add('hidden');
        document.getElementById('addUserNameSuggestions')?.classList.add('hidden');

        document.getElementById('addUserFullName')?.classList.remove('eh-input-error');
        document.getElementById('addUserBiometricId')?.classList.remove('eh-input-error');
    },

    reset(clearName = false) {
        clearTimeout(this.lookupTimer);

        const nameInput = document.getElementById('addUserFullName');
        const bidInput = document.getElementById('addUserBiometricId');
        const employeeIdInput = document.getElementById('addUserEmployeeId');
        const box = document.getElementById('addUserNameSuggestions');
        const message = document.getElementById('addUserLookupMessage');

        if (clearName && nameInput) nameInput.value = '';
        if (bidInput) bidInput.value = '';
        if (employeeIdInput) employeeIdInput.value = '';
        this.clearFields();

        box?.classList.add('hidden');
        if (box) box.innerHTML = '';
        message?.classList.add('hidden');
        if (message) message.textContent = '';

        nameInput?.classList.remove('eh-input-error');
        this.valid = false;
    },

    init() {
        const nameInput = document.getElementById('addUserFullName');
        const bidInput = document.getElementById('addUserBiometricId');
        const box = document.getElementById('addUserNameSuggestions');
        if (!nameInput || !bidInput || !box) return;

        const anchorBox = activeInput => {
            activeInput.insertAdjacentElement('afterend', box);
        };

        const handleTyping = sourceInput => {
            anchorBox(sourceInput);
            const query = sourceInput.value.trim();
            this.valid = false;

            if (sourceInput !== bidInput) bidInput.value = '';
            this.clearFields();

            clearTimeout(this.lookupTimer);
            box.classList.add('hidden');
            box.innerHTML = '';

            document.getElementById('addUserLookupMessage')?.classList.add('hidden');
            nameInput.classList.remove('eh-input-error');

            if (query.length < 2) return;

            this.lookupTimer = setTimeout(() => {
                this.searchEmployees(query);
            }, 400);
        };

        nameInput.addEventListener('input', () => handleTyping(nameInput));
        bidInput.addEventListener('input', () => handleTyping(bidInput));

        document.addEventListener('click', e => {
            if (e.target !== nameInput && e.target !== bidInput && !box.contains(e.target)) {
                box.classList.add('hidden');
            }
        });

        nameInput.addEventListener('focus', () => {
            anchorBox(nameInput);
            if (!this.valid && box.innerHTML.trim() !== '') box.classList.remove('hidden');
        });

        bidInput.addEventListener('focus', () => {
            anchorBox(bidInput);
            if (!this.valid && box.innerHTML.trim() !== '') box.classList.remove('hidden');
        });
    }
};

/* =========================================
   CREATE USER
========================================= */

document.getElementById('addUserForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!AddUserLookup.valid) {
        EHeart.toast('Please select a valid employee from the suggestions.', 'error');
        document.getElementById('addUserFullName')?.focus();
        return;
    }

    const fd = new FormData(e.target);

    try {
        await EHeart.api('/eheart/api/users/create.php', {
            method: 'POST',
            body: Object.fromEntries(fd)
        });

        EHeart.toast('User created.', 'success');
        EHeart.closeModal('addUserModal');
        e.target.reset();
        AddUserLookup.reset(true);
        UsersPage.load();
    } catch (err) {
        console.error('Failed to create user:', err);
    }
});

/* =========================================
   INITIALIZATION
========================================= */

document.addEventListener('DOMContentLoaded', () => {
    UsersPage.init();
    AddUserLookup.init();
});

if (window.lucide) {
    lucide.createIcons();
}