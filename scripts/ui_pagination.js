/**
 * EHPagination — shared pagination UI builder.
 * Use on any page: audit, heart_card, history, users, etc.
 *
 * Usage:
 *   EHPagination.render({
 *     container: document.getElementById('cardPagination'),
 *     gotoInputId: 'cardGotoPage',
 *     totalPages: 10,
 *     currentPage: this.page,
 *     onChange: (page) => { this.page = page; this.renderRows(...); }
 *   });
 */
const EHPagination = {
    render({ container, gotoInputId, totalPages, currentPage, onChange, maxVisible = 3 }) {
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

        // GO TO PAGE box (optional — only wired if gotoInputId given and element exists)
        if (gotoInputId) {
            const input = document.getElementById(gotoInputId);
            if (input) {
                input.max = totalPages;
                input.value = currentPage;

                // avoid stacking duplicate listeners across re-renders
                const clone = input.cloneNode(true);
                input.parentNode.replaceChild(clone, input);

                clone.addEventListener('change', () => {
                    let page = parseInt(clone.value, 10);
                    if (!page || page < 1) page = 1;
                    if (page > totalPages) page = totalPages;
                    clone.value = page;
                    if (page === currentPage) return;
                    onChange(page);
                });

                clone.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') clone.blur();
                });
            }
        }

        if (window.lucide) lucide.createIcons();
    }
};