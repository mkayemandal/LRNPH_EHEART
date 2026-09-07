/**
 * Custom dropdown engine. Replaces native <select> for full style control.
 * Wires: .eh-dropdown / .eh-filter blocks with button + hidden input + <ul>.
 * Dispatches native 'change' on the hidden input so existing listeners
 * (heart_card.js) keep working unchanged.
 */
const EHDropdown = {
  _bound: new WeakSet(),
  _globalBound: false,

  init(scope = document) {
    scope.querySelectorAll('.eh-filter, .eh-dropdown').forEach((wrap) => {
      if (this._bound.has(wrap)) return;
      this._bound.add(wrap);
      this.bind(wrap);
    });

    // Only ever register these once, no matter how many times init() runs.
    if (this._globalBound) return;
    this._globalBound = true;

    document.addEventListener('click', (e) => {
      document.querySelectorAll('.eh-filter.open, .eh-dropdown.open').forEach((wrap) => {
        if (!wrap.contains(e.target)) this.close(wrap);
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closeAll();
    });
  },

  closeAll(except = null) {
    document.querySelectorAll('.eh-filter.open, .eh-dropdown.open').forEach((wrap) => {
      if (wrap !== except) this.close(wrap);
    });
  },

  bind(wrap) {
    const btn = wrap.querySelector('.eh-filter-select');
    const list = wrap.querySelector('.eh-filter-dropdown');
    const hidden = wrap.querySelector('input[type="hidden"]');
    if (!btn || !list || !hidden) return;

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = wrap.classList.contains('open');

      // Always close every other dropdown first, no exceptions.
      this.closeAll(wrap);

      if (isOpen) {
        this.close(wrap);
      } else {
        this.open(wrap);
      }
    });

    list.querySelectorAll('li').forEach((li) => {
      li.addEventListener('click', () => {
        this.select(wrap, li.dataset.value, li.textContent.trim());
      });
    });

    // Expose programmatic API on the hidden input (used by clearFiltersBtn etc.)
    hidden.ehDropdown = {
      setValue: (value) => {
        const target = [...list.querySelectorAll('li')].find(li => li.dataset.value === value);
        this.select(wrap, value, target ? target.textContent.trim() : '', false);
      },
      reset: () => {
        const first = list.querySelector('li');
        this.select(wrap, '', first ? first.textContent.trim() : '', false);
      }
    };
  },

  open(wrap) {
    wrap.classList.add('open');
    wrap.querySelector('.eh-filter-dropdown')?.classList.add('open');
  },

  close(wrap) {
    wrap.classList.remove('open');
    wrap.querySelector('.eh-filter-dropdown')?.classList.remove('open');
  },

  select(wrap, value, label, fireChange = true) {
    const hidden = wrap.querySelector('input[type="hidden"]');
    const labelEl = wrap.querySelector('.eh-filter-select-label');
    const list = wrap.querySelector('.eh-filter-dropdown');

    hidden.value = value;
    if (labelEl) labelEl.textContent = label || labelEl.dataset.placeholder || label;

    list.querySelectorAll('li').forEach((li) => li.classList.toggle('selected', li.dataset.value === value));
    wrap.classList.toggle('eh-filter-active', !!value);

    this.close(wrap);

    if (fireChange) hidden.dispatchEvent(new Event('change', { bubbles: true }));
  }
};

document.addEventListener('DOMContentLoaded', () => EHDropdown.init());
window.EHDropdown = EHDropdown;