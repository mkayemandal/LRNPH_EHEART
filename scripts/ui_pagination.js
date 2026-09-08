/**
 * EHPagination — shared pagination UI builder.
 * Use on any page: audit, heart_card, history, users, etc.
 *
 * Usage:
 *   EHPagination.render({
 *     container: document.getElementById('cardPagination'),
 *     pageSizeContainerId: 'cardPageSize',
 *     gotoInputId: 'cardGotoPage',
 *     gotoPageContainerId: 'cardGotoPageContainer',
 *     totalPages: 10,
 *     currentPage: this.page,
 *     pageSize: this.pageSize,
 *     onPageSizeChange: (pageSize) => { this.pageSize = pageSize; this.page = 1; this.render(); },
 *     onChange: (page) => { this.page = page; this.renderRows(...); }
 *   });
 */
const EHPagination = {
    render({ container, pageSizeContainerId, gotoInputId, gotoPageContainerId, totalPages, currentPage, onChange, pageSize = 15, onPageSizeChange, pageSizeOptions = [10, 15, 20, 25, 50, 100], maxVisible = 3 }) {
        if (!container) return;

        totalPages = Math.max(1, totalPages);
        currentPage = Math.min(Math.max(1, currentPage), totalPages);

        let startPage = 1;
        let endPage = Math.min(totalPages, maxVisible);

        if (currentPage > endPage) {
            startPage = currentPage;
            endPage = Math.min(totalPages, startPage + maxVisible - 1);
        }

        let html = '';
                html += `
            <button type="button" class="eh-page-btn eh-page-prev" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''} aria-label="Previous page">
                <i data-lucide="chevron-left"></i>
            </button>
        `;

        for (let i = startPage; i <= endPage; i++) {
            html += `<button type="button" class="eh-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < totalPages) {
            html += `<span class="eh-page-ellipsis">...</span>`;
        }

        html += `
      <button type="button" class="eh-page-btn eh-page-next" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''} aria-label="Next page">
        <i data-lucide="chevron-right"></i>
      </button>
    `;

        container.innerHTML = html;

        container.querySelectorAll('.eh-page-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = Number(btn.dataset.page);
                if (!page || page < 1 || page > totalPages || page === currentPage) return;
                onChange(page);
            });
        });

        const pageSizeContainer = pageSizeContainerId && document.getElementById(pageSizeContainerId);
        if (pageSizeContainer && onPageSizeChange) {
            pageSizeContainer.innerHTML = `
                <label class="eh-page-size" for="${pageSizeContainerId}Select">
                    <span class="eh-sr-only">Rows per page</span>
                    <select class="eh-page-size-select" id="${pageSizeContainerId}Select" aria-label="Rows per page">
                        ${pageSizeOptions.map(size => `<option value="${size}" ${Number(size) === Number(pageSize) ? 'selected' : ''}>${size}</option>`).join('')}
                    </select>
                </label>
            `;

            pageSizeContainer.querySelector('.eh-page-size-select').addEventListener('change', (event) => {
                const nextPageSize = Number(event.target.value);
                if (nextPageSize > 0 && nextPageSize !== Number(pageSize)) onPageSizeChange(nextPageSize);
            });
        }

        const gotoPageContainer = gotoPageContainerId && document.getElementById(gotoPageContainerId);
        const gotoInput = gotoInputId && document.getElementById(gotoInputId);
        if (gotoPageContainer && gotoInput) {
            gotoInput.max = totalPages;
            gotoInput.value = currentPage;

            const clone = gotoInput.cloneNode(true);
            gotoInput.parentNode.replaceChild(clone, gotoInput);
            clone.addEventListener('change', () => {
                let page = parseInt(clone.value, 10);
                if (!page || page < 1) page = 1;
                if (page > totalPages) page = totalPages;
                clone.value = page;
                if (page !== currentPage) onChange(page);
            });
            clone.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') clone.blur();
            });
        }

        if (window.lucide) lucide.createIcons();
    }
};