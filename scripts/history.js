const HistoryPage = {
    allRows: [],
    filters: { q: '', action: '', date_from: '', date_to: '' },
    page: 1,
    pageSize: 15,

    apply() {
        const q = this.filters.q.toLowerCase().trim();
        const visibleRows = [];

        this.allRows.forEach(row => {
            const action = row.dataset.action || '';
            const date = (row.dataset.date || '').slice(0, 10);
            const dateFrom = (this.filters.date_from || '').slice(0, 10);
            const dateTo = (this.filters.date_to || '').slice(0, 10);
            const matchesAction = !this.filters.action || action === this.filters.action;
            const matchesSearch = !q || row.textContent.toLowerCase().includes(q);
            const matchesDate = dateFrom && !dateTo
                ? date === dateFrom
                : (!dateFrom || date >= dateFrom) && (!dateTo || date <= dateTo);
            const visible = matchesAction && matchesSearch && matchesDate;

            row.classList.toggle('eh-filtered-out', !visible);
            if (visible) visibleRows.push(row);
        });

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

        const emptyRow = document.getElementById('historyEmptyRow');
        if (emptyRow) emptyRow.remove();
        if (visibleRows.length === 0) {
            document.getElementById('historyBody').insertAdjacentHTML('beforeend', `
                <tr id="historyEmptyRow">
                    <td colspan="7">
                        <div class="eh-empty">
                            <div class="eh-empty-icon"><i data-lucide="search-x"></i></div>
                            <div class="eh-empty-title">No history records found</div>
                            <div class="eh-empty-text">Try adjusting your search or filters.</div>
                        </div>
                    </td>
                </tr>
            `);
            if (window.lucide) lucide.createIcons();
        }

        document.getElementById('historyTotalCount').textContent = visibleRows.length;
        this.renderPagination(totalPages);
    },

    renderPagination(totalPages) {
        EHPagination.render({
            container: document.getElementById('historyPagination'),
            pageSizeContainerId: 'historyPageSize',
            gotoInputId: 'historyGotoPage',
            gotoPageContainerId: 'historyGotoPageContainer',
            totalPages,
            currentPage: this.page,
            pageSize: this.pageSize,
            onPageSizeChange: (pageSize) => {
                this.pageSize = pageSize;
                this.page = 1;
                this.apply();
            },
            onChange: (page) => {
                this.page = page;
                this.apply();
                document.querySelector('.eh-table-scroll')?.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    },

    updateClearBtn() {
        const btn = document.getElementById('historyClearFiltersBtn');
        if (!btn) return;
        const hasFilter = !!this.filters.q || !!this.filters.action || !!this.filters.date_from || !!this.filters.date_to;
        btn.classList.toggle('hidden', !hasFilter);
    },

    bindEvents() {
        const searchInput = document.getElementById('historySearch');
        const searchClear = document.getElementById('historySearchClear');

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

        document.getElementById('actionFilter')?.addEventListener('change', (e) => {
            this.filters.action = e.target.value;
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('historyDateFrom')?.closest('.eh-date-range')?.addEventListener('eh:date-range-change', () => {
            const dateFrom = document.getElementById('historyDateFrom');
            const dateTo = document.getElementById('historyDateTo');
            this.filters.date_from = dateFrom?.dataset.isoValue || dateFrom?.value || '';
            this.filters.date_to = dateTo?.dataset.isoValue || dateTo?.value || '';
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });

        document.getElementById('historyClearFiltersBtn')?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            searchClear?.classList.remove('visible');

            document.getElementById('actionFilter')?.ehDropdown?.reset();

            const dFrom = document.getElementById('historyDateFrom');
            const dTo = document.getElementById('historyDateTo');
            if (dFrom) { dFrom.value = ''; dFrom.dataset.isoValue = ''; }
            if (dTo) { dTo.value = ''; dTo.dataset.isoValue = ''; }

            const calInst = window.EHCalendar?.instances?.historyDateFrom;
            if (calInst) {
                const today = new Date();
                calInst.range = { from: null, to: null };
                calInst.viewYear = today.getFullYear();
                calInst.viewMonth = today.getMonth();
                calInst.displayInput.value = '';
                window.EHCalendar.render(calInst);
            }

            this.filters = { q: '', action: '', date_from: '', date_to: '' };
            this.page = 1;
            this.updateClearBtn();
            this.apply();
        });
    },

    init() {
        this.allRows = Array.from(document.querySelectorAll('#historyBody tr[data-action]'));
        this.bindEvents();
        this.updateClearBtn();
        this.apply();
    }
};

document.addEventListener('DOMContentLoaded', () => HistoryPage.init());
if (window.lucide) lucide.createIcons();