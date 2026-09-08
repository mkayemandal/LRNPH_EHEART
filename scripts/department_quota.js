const DeptQuotaPage = {
    page: 1,
    pageSize: 15,
    rows: [],
    searchTerm: '',

    async load() {
        try {
            const res = await EHeart.api('/eheart/api/department/quota_list.php');
            this.rows = res.data || [];
            this.render();
        } catch (e) {
            console.error('Failed to load department quotas:', e);
        }
    },

    getFilteredRows() {
        if (!this.searchTerm) return this.rows;
        const term = this.searchTerm.toLowerCase();
        return this.rows.filter(r => r.department.toLowerCase().includes(term));
    },

    availabilityBadge(remaining) {
        const num = Number(remaining);
        return num > 0
            ? `<span class="eh-badge eh-badge-green">Available</span>`
            : `<span class="eh-badge eh-badge-red">Not Available</span>`;
    },

    render() {
        const tbody = document.getElementById('deptQuotaBody');
        if (!tbody) return;

        const filteredRows = this.getFilteredRows();
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / this.pageSize));
        if (this.page > totalPages) this.page = totalPages;

        const start = (this.page - 1) * this.pageSize;
        const pageRows = filteredRows.slice(start, start + this.pageSize);

        if (!pageRows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="eh-empty">
                            <div class="eh-empty-icon"><i data-lucide="search-x"></i></div>
                            <div class="eh-empty-title">No department quotas found</div>
                            <div class="eh-empty-text">Set a monthly quota to get started.</div>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            tbody.innerHTML = pageRows.map(r => `
                <tr>
                    <td>
                        <div class="eh-dept-cell">
                            <i data-lucide="building-2" aria-hidden="true"></i>
                            <span class="eh-dept-name">${this.escapeHtml(r.department)}</span>
                        </div>
                    </td>
                    <td>${r.headcount}</td>
                    <td>${r.monthly_quota}</td>
                    <td>${r.used}</td>
                    <td>${r.remaining}</td>
                    <td>${this.availabilityBadge(r.remaining)}</td>
                    <td class="eh-col-actions">
                        <button
                            type="button"
                            class="eh-icon-btn action-edit"
                            title="Edit Quota"
                            aria-label="Edit Quota"
                            onclick="DeptQuotaPage.editRow('${this.escapeHtml(r.department)}', ${Number(r.monthly_quota)}, ${Number(r.headcount)})">
                            <i data-lucide="pencil"></i>
                        </button>
                        <button
                            type="button"
                            class="eh-icon-btn action-delete"
                            title="Delete Quota"
                            aria-label="Delete Quota"
                            onclick="DeptQuotaPage.deleteRow('${this.escapeHtml(r.department)}')">
                            <i data-lucide="trash-2"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        const totalCountEl = document.getElementById('deptQuotaTotalCount');
        if (totalCountEl) totalCountEl.textContent = filteredRows.length;

        this.renderPagination(totalPages);

        if (window.lucide) lucide.createIcons();
    },

    renderPagination(totalPages) {
        EHPagination.render({
            container: document.getElementById('deptQuotaPagination'),
            pageSizeContainerId: 'deptQuotaPageSize',
            gotoInputId: 'deptQuotaGotoPage',
            gotoPageContainerId: 'deptQuotaGotoPageContainer',
            totalPages,
            currentPage: this.page,
            pageSize: this.pageSize,
            onPageSizeChange: (pageSize) => {
                this.pageSize = pageSize;
                this.page = 1;
                this.render();
            },
            onChange: page => {
                this.page = page;
                this.render();
                document.querySelector('.eh-table-scroll')?.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    },

    editRow(department, monthlyQuota, headcount) {
        const modalTitle = document.querySelector('#addDeptQuotaModal .eh-modal-title');
        const wrap = document.querySelector('[data-name="department"]');
        const list = wrap?.querySelector('.eh-filter-dropdown');
        const quotaInput = document.querySelector('#deptQuotaForm input[name="monthly_quota"]');
        const headcountInput = document.querySelector('#deptQuotaForm input[name="headcount"]');

        if (modalTitle) modalTitle.textContent = 'Edit Department Quota';

        if (wrap && list) {
            // department already has a quota so it was excluded from the fetch list —
            // inject it back so it can be shown/selected while editing
            if (![...list.querySelectorAll('li')].some(li => li.dataset.value === department)) {
                const li = document.createElement('li');
                li.dataset.value = department;
                li.textContent = department;
                list.appendChild(li);
                li.addEventListener('click', () => EHDropdown.select(wrap, li.dataset.value, li.textContent.trim()));
            }
            EHDropdown.select(wrap, department, department, false);

            const btn = wrap.querySelector('.eh-filter-select');
            if (btn) {
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.6';
            }
        }

        if (quotaInput) quotaInput.value = monthlyQuota;
        if (headcountInput) headcountInput.value = headcount;

        EHeart.openModal('addDeptQuotaModal');
    },

    pendingDeleteDept: null,

    deleteRow(department) {
        this.pendingDeleteDept = department;

        const nameEl = document.getElementById('deleteDeptQuotaName');
        const input = document.getElementById('deleteDeptQuotaInput');
        const confirmBtn = document.getElementById('deleteDeptQuotaConfirmBtn');

        if (nameEl) nameEl.textContent = department;
        if (input) input.value = '';
        if (confirmBtn) confirmBtn.disabled = true;

        EHeart.openModal('deleteDeptQuotaModal');
        setTimeout(() => input?.focus(), 100);
    },

    closeDeleteModal() {
        this.pendingDeleteDept = null;
        EHeart.closeModal('deleteDeptQuotaModal');
    },

    async confirmDelete() {
        const input = document.getElementById('deleteDeptQuotaInput');
        const confirmBtn = document.getElementById('deleteDeptQuotaConfirmBtn');

        if (!this.pendingDeleteDept || input?.value.trim().toLowerCase() !== 'delete') {
            EHeart.toast('Type "delete" to confirm.', 'error');
            return;
        }

        if (confirmBtn) confirmBtn.disabled = true;

        try {
            await EHeart.api('/eheart/api/department/quota_delete.php', {
                method: 'POST',
                body: { department: this.pendingDeleteDept }
            });

            EHeart.toast('Department quota deleted.', 'success');
            this.closeDeleteModal();
            this.page = 1;
            this.load();
            DeptQuotaDepartments.load();
        } catch (e) {
            console.error('Failed to delete quota:', e);
            if (confirmBtn) confirmBtn.disabled = false;
        }
    },

    resetModal() {
        const modalTitle = document.querySelector('#addDeptQuotaModal .eh-modal-title');
        const wrap = document.querySelector('[data-name="department"]');
        const form = document.getElementById('deptQuotaForm');

        if (modalTitle) modalTitle.textContent = 'Set Department Quota';

        if (wrap) {
            const btn = wrap.querySelector('.eh-filter-select');
            if (btn) {
                btn.style.pointerEvents = '';
                btn.style.opacity = '';
            }
            wrap.querySelector('input[type="hidden"]')?.ehDropdown?.reset();
        }

        form?.reset();
        DeptQuotaDepartments.load();
    },

    escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    bindSearch() {
        const input = document.getElementById('deptQuotaSearch');
        const clearBtn = document.getElementById('deptQuotaSearchClear');
        if (!input) return;

        input.addEventListener('input', () => {
            this.searchTerm = input.value.trim();
            clearBtn?.classList.toggle('visible', !!this.searchTerm);
            this.page = 1;
            this.render();
        });

        clearBtn?.addEventListener('click', () => {
            input.value = '';
            this.searchTerm = '';
            clearBtn.classList.remove('visible');
            this.page = 1;
            this.render();
            input.focus();
        });
    },

    init() {
        this.load();
        DeptQuotaDepartments.load();
        this.bindSearch();
        EHDropdown.init();

        document.getElementById('deleteDeptQuotaInput')?.addEventListener('input', (e) => {
            const confirmBtn = document.getElementById('deleteDeptQuotaConfirmBtn');
            if (confirmBtn) confirmBtn.disabled = e.target.value.trim().toLowerCase() !== 'delete';
        });

        document.getElementById('deptQuotaForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);

            try {
                await EHeart.api('/eheart/api/department/quota_upsert.php', {
                    method: 'POST',
                    body: Object.fromEntries(fd)
                });

                EHeart.toast('Department quota saved.', 'success');
                EHeart.closeModal('addDeptQuotaModal');
                this.resetModal();
                this.page = 1;
                this.load();
            } catch (err) {
                console.error('Failed to save quota:', err);
            }
        });
    }
};

/* =========================================
   DEPARTMENT DROPDOWN — only departments not
   already listed in eheart_department_quota
========================================= */

const DeptQuotaDepartments = {
    loaded: false,

    async load() {
        const wrap = document.querySelector('[data-name="department"]');
        const list = wrap?.querySelector('.eh-filter-dropdown');
        const labelEl = wrap?.querySelector('.eh-filter-select-label');
        if (!wrap || !list) return;

        try {
            const res = await EHeart.api('/eheart/api/department/list.php?exclude_quota=1');
            const departments = res.data || [];

            list.innerHTML = '<li data-value="" class="selected">Select department...</li>' +
                departments.map(d => `<li data-value="${this.escapeHtml(d)}">${this.escapeHtml(d)}</li>`).join('');

            if (labelEl) {
                labelEl.textContent = departments.length
                    ? 'Select department...'
                    : 'All departments already have quotas';
            }

            list.querySelectorAll('li').forEach((li) => {
                li.addEventListener('click', () => {
                    EHDropdown.select(wrap, li.dataset.value, li.textContent.trim());
                });
            });

            this.loaded = true;
        } catch (e) {
            if (labelEl) labelEl.textContent = 'Unable to load departments';
            console.error('Failed to load masterlist departments:', e);
        }
    },

    escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    DeptQuotaPage.init();
    if (window.lucide) lucide.createIcons();
});