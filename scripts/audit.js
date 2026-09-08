const AuditPage = {
    allRows: [],
    filters: { q: '', module: '', action: '', role: '', date_from: '', date_to: '' },
    page: 1,
    pageSize: 15,

    apply() {
        const q = this.filters.q.toLowerCase().trim();
        const visibleRows = [];

        this.allRows.forEach(row => {
            const module = row.dataset.module || '';
            const action = row.dataset.action || '';
            const role = row.dataset.role || '';
            const date = (row.dataset.date || '').slice(0, 10);
            const matchesModule = !this.filters.module || module === this.filters.module;
            const matchesAction = !this.filters.action || action === this.filters.action;
            const matchesRole = !this.filters.role || role === this.filters.role;
            const matchesSearch = !q || row.textContent.toLowerCase().includes(q);
            const dateFrom = (this.filters.date_from || '').slice(0, 10);
            const dateTo = (this.filters.date_to || '').slice(0, 10);
            const matchesDate = dateFrom && !dateTo
                ? date === dateFrom
                : (!dateFrom || date >= dateFrom) && (!dateTo || date <= dateTo);
            const visible = matchesModule && matchesAction && matchesRole && matchesSearch && matchesDate;

            row.classList.toggle('eh-filtered-out', !visible);
            if (visible) visibleRows.push(row);
        });

        // paginate the filtered set
        const totalPages = Math.max(1, Math.ceil(visibleRows.length / this.pageSize));
        if (this.page > totalPages) this.page = totalPages;
        const start = (this.page - 1) * this.pageSize;
        const pageSet = new Set(visibleRows.slice(start, start + this.pageSize));

        this.allRows.forEach(row => {
            if (row.classList.contains('eh-filtered-out')) {
                row.style.display = 'none';
            } else {
                row.style.display = pageSet.has(row) ? '' : 'none';
            }
        });

        const emptyRow = document.getElementById('auditEmptyRow');
        if (emptyRow) emptyRow.remove();
        if (visibleRows.length === 0) {
            document.getElementById('auditBody').insertAdjacentHTML('beforeend', `
                <tr id="auditEmptyRow">
                    <td colspan="7">
                        <div class="eh-empty">
                            <div class="eh-empty-icon"><i data-lucide="search-x"></i></div>
                            <div class="eh-empty-title">No audit records found</div>
                            <div class="eh-empty-text">Try adjusting your search or filters.</div>
                        </div>
                    </td>
                </tr>
            `);
            if (window.lucide) lucide.createIcons();
        }

        document.getElementById('auditCount').textContent = `Showing ${pageSet.size} of ${visibleRows.length} records`;
        this.renderPagination(totalPages);
    },

    renderPagination(totalPages) {
        EHPagination.render({
            container: document.getElementById('auditPagination'),
            gotoInputId: 'auditGotoPage',
            totalPages,
            currentPage: this.page,
            onChange: (page) => {
                this.page = page;
                this.apply();
                document.querySelector('.eh-table-scroll')?.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    },

    updateClearBtn() {
        const btn = document.getElementById('auditClearFiltersBtn');
        if (!btn) return;
        const hasFilter = !!this.filters.q || !!this.filters.module || !!this.filters.action || !!this.filters.date_from || !!this.filters.date_to;
        const hasRoleFilter = !!this.filters.role;
        btn.classList.toggle('hidden', !(hasFilter || hasRoleFilter));
    },

    bindEvents() {
        const searchInput = document.getElementById('auditSearch');
        const searchClear = document.getElementById('auditSearchClear');

        searchInput?.addEventListener('input', (e) => {
            this.filters.q = e.target.value.trim();
            this.page = 1;
            searchClear?.classList.toggle('visible', !!this.filters.q);
            this.updateClearBtn();
            this.apply();
        });

        searchClear?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            this.filters.q = '';
            this.page = 1;
            searchClear.classList.remove('visible');
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('moduleFilter')?.addEventListener('change', (e) => {
            this.filters.module = e.target.value;
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('actionFilter')?.addEventListener('change', (e) => {
            this.filters.action = e.target.value;
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('roleFilter')?.addEventListener('change', (e) => {
            this.filters.role = e.target.value;
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('auditDateFrom')?.closest('.eh-date-range')?.addEventListener('eh:date-range-change', () => {
            const dateFrom = document.getElementById('auditDateFrom');
            const dateTo = document.getElementById('auditDateTo');
            this.filters.date_from = dateFrom?.dataset.isoValue || dateFrom?.value || '';
            this.filters.date_to = dateTo?.dataset.isoValue || dateTo?.value || '';
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('auditClearFiltersBtn')?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            searchClear?.classList.remove('visible');

            document.getElementById('moduleFilter')?.ehDropdown?.reset();
            document.getElementById('actionFilter')?.ehDropdown?.reset();
            document.getElementById('roleFilter')?.ehDropdown?.reset();

            const dFrom = document.getElementById('auditDateFrom');
            const dTo = document.getElementById('auditDateTo');
            const dDisplay = document.getElementById('auditDateFromDisplay');   // <-- add this
            if (dFrom) { dFrom.value = ''; dFrom.dataset.isoValue = ''; }
            if (dTo) { dTo.value = ''; dTo.dataset.isoValue = ''; }
            if (dDisplay) { dDisplay.value = ''; }                              // <-- add this

            const calInst = window.EHCalendar?.instances?.auditDateFrom;
            if (calInst) {
                const today = new Date();
                calInst.range = { from: null, to: null };
                calInst.viewYear = today.getFullYear();
                calInst.viewMonth = today.getMonth();
                window.EHCalendar.render(calInst);
            }

            this.filters = { q: '', module: '', action: '', role: '', date_from: '', date_to: '' };
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });
    },

    init() {
        this.allRows = Array.from(document.querySelectorAll('#auditBody tr[data-module]'));
        this.bindEvents();
        this.updateClearBtn();
        this.apply();
    }
};

document.addEventListener('DOMContentLoaded', () => AuditPage.init());
if (window.lucide) lucide.createIcons();