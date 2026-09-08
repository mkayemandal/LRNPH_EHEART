/**
 * Custom range calendar. Fully dynamic — no hardcoded dates/months/years.
 * Everything derived from real Date() object at render time.
 * Single input box shows "from - to". Selection previews in popup only;
 * committed to inputs (and fires 'change') when Apply is clicked.
 */
const EHCalendar = {
  instances: {},

  init(scope = document) {
    scope.querySelectorAll('.eh-date-range').forEach((wrap) => {
      if (wrap.dataset.ehBound) return;
      wrap.dataset.ehBound = '1';
      this.bind(wrap);
    });

    document.addEventListener('click', (e) => {
      Object.values(this.instances).forEach((inst) => {
        if (!inst.wrap.contains(e.target)) this.close(inst);
      });
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        Object.values(this.instances).forEach((inst) => this.close(inst));
      }
    });

    window.addEventListener('resize', () => {
      Object.values(this.instances).forEach((inst) => {
        if (inst.wrap.classList.contains('open')) this.reposition(inst);
      });
    });
  },

  bind(wrap) {
    const fromId = wrap.dataset.fromId;
    const toId = wrap.dataset.toId;
    const displayInput = document.getElementById(fromId + 'Display');
    const fromInput = document.getElementById(fromId);
    const toInput = document.getElementById(toId);
    const popup = wrap.querySelector('.eh-calendar-popup');
    if (!displayInput || !fromInput || !toInput || !popup) return;

    popup.addEventListener('click', (e) => e.stopPropagation());

    const today = new Date();

    const inst = {
      wrap,
      displayInput,
      fromInput,
      toInput,
      popup,
      viewYear: today.getFullYear(),
      viewMonth: today.getMonth(),
      selecting: 'from',
      range: { from: null, to: null }
    };

    this.instances[fromId] = inst;

    displayInput.addEventListener('click', () => this.toggle(inst));

    this.render(inst);
  },

  toggle(inst) {
    const isOpen = inst.wrap.classList.contains('open');
    Object.values(this.instances).forEach((i) => this.close(i));
    if (!isOpen) this.open(inst);
  },

  open(inst) {
    document.body.appendChild(inst.popup); // move to body, escape all clipping
    inst.popup.style.position = 'fixed';
    inst.wrap.classList.add('open');
    inst.popup.classList.add('open');
    this.render(inst);
    if (window.lucide) lucide.createIcons();
    requestAnimationFrame(() => this.reposition(inst));
  },

  reposition(inst) {
    const rect = inst.displayInput.getBoundingClientRect();
    const popupRect = inst.popup.getBoundingClientRect();

    let top = rect.bottom + 8;
    let left = rect.left;

    // flip up if not enough room below
    if (top + popupRect.height > window.innerHeight - 8) {
      top = rect.top - popupRect.height - 8;
    }
    // clamp right edge
    if (left + popupRect.width > window.innerWidth - 8) {
      left = window.innerWidth - popupRect.width - 8;
    }
    if (left < 8) left = 8;

    inst.popup.style.top = `${top}px`;
    inst.popup.style.left = `${left}px`;
    inst.popup.style.right = 'auto';
  },

  close(inst) {
    inst.wrap.classList.remove('open');
    inst.popup.classList.remove('open');
    inst.popup.style.top = '';
    inst.popup.style.left = '';
    inst.popup.style.right = '';
  },

  formatDisplay(date) {
    if (!date) return '';
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    const yyyy = date.getFullYear();
    return `${mm}/${dd}/${yyyy}`;
  },

  formatValue(date) {
    if (!date) return '';
    const mm = String(date.getMonth() + 1).padStart(2, '0');
    const dd = String(date.getDate()).padStart(2, '0');
    const yyyy = date.getFullYear();
    return `${yyyy}-${mm}-${dd}`;
  },

  updateClearVisibility(inst) {
    const clearBtn = inst.wrap.querySelector('.eh-date-clear');
    if (!clearBtn) return;
    clearBtn.classList.toggle('visible', !!(inst.range.from || inst.range.to));
  },

  pickDay(inst, date) {
    if (inst.selecting === 'from' || !inst.range.from || (inst.range.from && inst.range.to)) {
      inst.range.from = date;
      inst.range.to = null;
      inst.selecting = 'to';
    } else {
      if (date < inst.range.from) {
        inst.range.to = inst.range.from;
        inst.range.from = date;
      } else {
        inst.range.to = date;
      }
      inst.selecting = 'from';
    }

    // preview only — commit happens on Apply
    this.render(inst);
  },

  applyRange(inst) {
    inst.fromInput.value = this.formatValue(inst.range.from);
    inst.fromInput.dataset.isoValue = this.formatValue(inst.range.from);
    inst.toInput.value = this.formatValue(inst.range.to);
    inst.toInput.dataset.isoValue = this.formatValue(inst.range.to);

    inst.displayInput.value = inst.range.from
      ? `${this.formatDisplay(inst.range.from)}${inst.range.to ? ' - ' + this.formatDisplay(inst.range.to) : ''}`
      : '';

    inst.fromInput.dispatchEvent(new Event('change', { bubbles: true }));
    inst.toInput.dispatchEvent(new Event('change', { bubbles: true }));
    inst.wrap.dispatchEvent(new CustomEvent('eh:date-range-change', { bubbles: true }));

    this.close(inst);
  },

  changeMonth(inst, delta) {
    inst.viewMonth += delta;
    if (inst.viewMonth > 11) { inst.viewMonth = 0; inst.viewYear++; }
    if (inst.viewMonth < 0) { inst.viewMonth = 11; inst.viewYear--; }
    this.render(inst);
  },

  isSameDay(a, b) {
    return a && b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  },

  render(inst) {
    const monthNames = new Date(2000, inst.viewMonth, 1).toLocaleString('en-US', { month: 'long' });
    const today = new Date();

    const firstOfMonth = new Date(inst.viewYear, inst.viewMonth, 1);
    const startWeekday = firstOfMonth.getDay(); // dynamic, no hardcode
    const daysInMonth = new Date(inst.viewYear, inst.viewMonth + 1, 0).getDate(); // dynamic
    const daysInPrevMonth = new Date(inst.viewYear, inst.viewMonth, 0).getDate();

    const weekdayLabels = [];
    const base = new Date(2023, 0, 1); // a Sunday, used only to derive labels dynamically
    for (let i = 0; i < 7; i++) {
      const d = new Date(base);
      d.setDate(base.getDate() + i);
      weekdayLabels.push(d.toLocaleString('en-US', { weekday: 'short' }).slice(0, 2));
    }

    let cells = '';

    for (let i = startWeekday - 1; i >= 0; i--) {
      const day = daysInPrevMonth - i;
      cells += `<span class="eh-cal-day eh-cal-day-muted" data-disabled="1">${day}</span>`;
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(inst.viewYear, inst.viewMonth, day);
      const iso = this.formatValue(date);
      let classes = 'eh-cal-day';

      if (this.isSameDay(date, today)) classes += ' eh-cal-day-today';
      if (this.isSameDay(date, inst.range.from) || this.isSameDay(date, inst.range.to)) classes += ' eh-cal-day-selected';
      if (inst.range.from && inst.range.to && date > inst.range.from && date < inst.range.to) classes += ' eh-cal-day-inrange';

      cells += `<span class="${classes}" data-date="${iso}">${day}</span>`;
    }

    const totalCells = startWeekday + daysInMonth;
    const trailing = (7 - (totalCells % 7)) % 7;
    for (let day = 1; day <= trailing; day++) {
      cells += `<span class="eh-cal-day eh-cal-day-muted" data-disabled="1">${day}</span>`;
    }

    const previewText = inst.range.from
      ? `${this.formatDisplay(inst.range.from)}${inst.range.to ? ' - ' + this.formatDisplay(inst.range.to) : ' - ...'}`
      : 'Select a date';

    inst.popup.innerHTML = `
      <div class="eh-cal-header">
        <div class="eh-cal-title">${monthNames} ${inst.viewYear}</div>
        <div class="eh-cal-nav">
          <button type="button" class="eh-cal-nav-btn" data-dir="-1" aria-label="Previous month"><i data-lucide="chevron-up"></i></button>
          <button type="button" class="eh-cal-nav-btn" data-dir="1" aria-label="Next month"><i data-lucide="chevron-down"></i></button>
        </div>
      </div>
      <div class="eh-cal-preview">${previewText}</div>
      <div class="eh-cal-weekdays">
        ${weekdayLabels.map(w => `<span>${w}</span>`).join('')}
      </div>
      <div class="eh-cal-grid">
        ${cells}
      </div>
      <div class="eh-cal-footer">
        <button type="button" class="eh-cal-footer-btn" data-action="clear">Clear</button>
        <button type="button" class="eh-cal-footer-btn eh-cal-footer-btn-accent" data-action="today">Today</button>
        <button type="button" class="eh-cal-footer-btn-apply" data-action="apply" ${inst.range.from ? '' : 'disabled'}>Apply</button>
      </div>
    `;

    inst.popup.querySelectorAll('.eh-cal-nav-btn').forEach((btn) => {
      btn.addEventListener('click', () => this.changeMonth(inst, Number(btn.dataset.dir)));
    });

    inst.popup.querySelectorAll('.eh-cal-day:not(.eh-cal-day-muted)').forEach((el) => {
      el.addEventListener('click', () => {
        const [y, m, d] = el.dataset.date.split('-').map(Number);
        this.pickDay(inst, new Date(y, m - 1, d));
      });
    });

    inst.popup.querySelector('[data-action="clear"]')?.addEventListener('click', () => {
      inst.range = { from: null, to: null };
      inst.selecting = 'from';
      this.applyRange(inst);
    });

    inst.popup.querySelector('[data-action="today"]')?.addEventListener('click', () => {
      const now = new Date();
      inst.viewYear = now.getFullYear();
      inst.viewMonth = now.getMonth();
      this.pickDay(inst, new Date(now.getFullYear(), now.getMonth(), now.getDate()));
    });

    inst.popup.querySelector('[data-action="apply"]')?.addEventListener('click', () => {
      this.applyRange(inst);
    });

    if (window.lucide) lucide.createIcons();

    // keep popup on-screen after re-render if already open
    if (inst.wrap.classList.contains('open')) {
      requestAnimationFrame(() => this.reposition(inst));
    }
  }
};

document.addEventListener('DOMContentLoaded', () => EHCalendar.init());
window.EHCalendar = EHCalendar;