function ehStatusBadgeClass(statusValue) {
  const normalized = String(statusValue ?? '').trim().toUpperCase();
  const map = {
    'PENDING': 'amber',
    'APPROVED': 'green',
    'FOR_REVISION': 'indigo',
    'FOR_REDEMPTION': 'orange',
    'REDEEMED': 'purple',
    'REJECTED': 'red',
    'CANCELLED': 'rose',
    'EXPIRED': 'slate'
  };

  if (map[normalized]) {
    return map[normalized];
  }

  const colors = [
    'blue',
    'green',
    'purple',
    'orange',
    'cyan',
    'rose',
    'teal',
    'amber',
    'indigo',
    'lime'
  ];

  const hash = Array.from(normalized || 'STATUS').reduce((sum, char) => sum + char.charCodeAt(0), 0);
  return colors[hash % colors.length];
}

const HeartCardPage = {
  page: 1,
  pageSize: 15,
  filters: { status: '', department: '', date_from: '', date_to: '', q: '' },
  pendingAction: null,
  currentCardData: null,
  selectedCoreValues: [],
  employeeLookupTimer: null,
  employeeLookupValid: false,
  requestChecklistIds: ['chkActionClear', 'chkRealImpact', 'chkBeyondDuty', 'chkValueDemo'],
  approveChecklistIds: ['chkApproveActionClear', 'chkApproveRealImpact', 'chkApproveBeyondDuty', 'chkApproveValueDemo'],

  setButtonLoading(button, loading, loadingLabel = 'Processing...') {
    if (!button) return;
    if (loading) {
      if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.classList.add('eh-action-loading');
      button.innerHTML = `<span class="eh-action-loading-spinner" aria-hidden="true"></span><span>${loadingLabel}</span>`;
      return;
    }
    button.disabled = false;
    button.classList.remove('eh-action-loading');
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
      if (window.lucide) lucide.createIcons();
    }
  },

  checklistPassed(ids) {
    return ids.every(id => document.getElementById(id)?.checked);
  },

  resetChecklist(ids) {
    ids.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.checked = false;
    });
  },

  updateRequestSubmitState() {
    const btn = document.getElementById('submitRequestBtn');
    if (btn) btn.disabled = !this.checklistPassed(this.requestChecklistIds);
  },

  updateApproveGateState() {
    if (!this.pendingAction || this.pendingAction.type !== 'approve') return;
    const input = document.getElementById('dvRemarksInput');
    const confirmBtn = document.getElementById('dvRemarksConfirm');
    const passed = this.checklistPassed(this.approveChecklistIds);
    if (input) input.disabled = !passed;
    if (confirmBtn) confirmBtn.disabled = !passed;
  },

  // ============================================
  // MANAGER QUOTA — pull real numbers from server
  // ============================================
  async loadManagerQuota() {
    if (window.EH_ROLE !== 'MANAGER') return;
    try {
      const res = await EHeart.api('/eheart/api/heart_card/quota.php');
      const used = document.getElementById('managerQuotaUsed');
      const limit = document.getElementById('managerQuotaLimit');
      if (used) used.textContent = res.data.used;
      if (limit) limit.textContent = res.data.limit;

      const box = document.querySelector('.eh-quota-box');
      if (box) box.classList.toggle('eh-quota-exceeded', !!res.data.is_exceeded);
    } catch (e) {
      console.error('Failed to load manager quota:', e);
    }
  },

  async init() {
    await this.loadCoreValues();
    await this.load();
    await this.loadManagerQuota();
    this.bindEvents();
    this.bindEmployeeLookup();
    if (window.lucide) lucide.createIcons();
    const referenceId = new URLSearchParams(window.location.search).get('reference_id');
    if (referenceId && Number(referenceId) > 0) await this.viewCard(null, Number(referenceId));
  },

  bindEvents() {
    document.getElementById('newRequestBtn')?.addEventListener('click', () => this.openPanel('create'));
    document.getElementById('cancelRequestBtn')?.addEventListener('click', () => this.closePanel());
    document.getElementById('closeDetailsBtn')?.addEventListener('click', () => this.closePanel());
    document.getElementById('requestForm')?.addEventListener('submit', e => this.submitRequest(e));
    document.getElementById('exportDataBtn')?.addEventListener('click', () => this.exportToExcel());

    const searchInput = document.getElementById('searchInput');
    const searchClear = document.getElementById('searchInputClear');

    if (searchInput) {
      searchInput.addEventListener('input', e => {
        this.filters.q = e.target.value.trim();
        this.page = 1;
        this.renderRows(this._rowsCache || []);
        searchClear?.classList.toggle('visible', !!this.filters.q);
        this.updateClearFiltersButton();
      });
    }

    searchClear?.addEventListener('click', () => {
      if (searchInput) searchInput.value = '';
      this.filters.q = '';
      this.page = 1;
      this.renderRows(this._rowsCache || []);
      searchClear.classList.remove('visible');
      this.updateClearFiltersButton();
      searchInput?.focus();
    });

    document.getElementById('statusFilter')?.addEventListener('change', e => {
      this.filters.status = e.target.value;
      this.page = 1;
      this.updateClearFiltersButton();
      this.load();
    });

    document.getElementById('departmentFilter')?.addEventListener('change', e => {
      this.filters.department = e.target.value;
      this.page = 1;
      this.updateClearFiltersButton();
      this.load();
    });

    document.getElementById('dateFrom')?.closest('.eh-date-range')?.addEventListener('eh:date-range-change', () => {
      const dateFrom = document.getElementById('dateFrom');
      const dateTo = document.getElementById('dateTo');
      this.filters.date_from = dateFrom?.dataset.isoValue || dateFrom?.value || '';
      this.filters.date_to = dateTo?.dataset.isoValue || dateTo?.value || '';
      this.page = 1;
      this.updateClearFiltersButton();
      this.validateDateRange();
      this.load();
    });

    document.getElementById('clearFiltersBtn')?.addEventListener('click', () => {
      const searchInput = document.getElementById('searchInput');
      const searchClear = document.getElementById('searchInputClear');
      const statusFilter = document.getElementById('statusFilter');
      const departmentFilter = document.getElementById('departmentFilter');
      const dateFrom = document.getElementById('dateFrom');
      const dateTo = document.getElementById('dateTo');

      if (searchInput) searchInput.value = '';
      statusFilter?.ehDropdown?.reset();
      departmentFilter?.ehDropdown?.reset();

      const dateFromDisplay = document.getElementById('dateFromDisplay');

      if (dateFrom) {
        dateFrom.value = '';
        dateFrom.dataset.isoValue = '';
      }

      if (dateTo) {
        dateTo.value = '';
        dateTo.dataset.isoValue = '';
      }

      if (dateFromDisplay) {
        dateFromDisplay.value = '';
      }

      const calInst = window.EHCalendar?.instances?.dateFrom;
      if (calInst) {
        const today = new Date();
        calInst.range = { from: null, to: null };
        calInst.viewYear = today.getFullYear();
        calInst.viewMonth = today.getMonth();
        window.EHCalendar.render(calInst);
      }

      this.filters = { status: '', department: '', date_from: '', date_to: '', q: '' };
      this.page = 1;
      searchClear?.classList.remove('visible');
      this.updateClearFiltersButton();
      this.load();
    });

    this.bindCoreValuePicker();
    document.getElementById('dvRemarksCancel')?.addEventListener('click', () => this.hideRemarksBox());
    document.getElementById('dvRemarksConfirm')?.addEventListener('click', () => this.confirmRemarksAction());

    this.requestChecklistIds.forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.updateRequestSubmitState());
    });

    this.approveChecklistIds.forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => this.updateApproveGateState());
    });

    this.updateRequestSubmitState();
    this.updateClearFiltersButton();

    if (searchInput && searchClear) searchClear.classList.toggle('visible', !!searchInput.value.trim());

    if (window.lucide) lucide.createIcons();
  },

  bindEmployeeLookup() {
    const nameInput = document.getElementById('receiverFullName');
    const bidInput = document.getElementById('receiverBiometricId');
    const suggestionsBox = document.getElementById('employeeNameSuggestions');
    if (!nameInput || !bidInput || !suggestionsBox) return;

    const anchorSuggestionsBox = activeInput => {
      activeInput.insertAdjacentElement('afterend', suggestionsBox);
    };

    const handleTyping = sourceInput => {
      anchorSuggestionsBox(sourceInput);
      const query = sourceInput.value.trim();
      this.employeeLookupValid = false;
      const departmentInput = document.getElementById('receiverDepartment');

      if (sourceInput !== bidInput) bidInput.value = '';
      if (sourceInput !== nameInput) nameInput.value = '';
      if (departmentInput) departmentInput.value = '';

      clearTimeout(this.employeeLookupTimer);
      suggestionsBox.classList.add('hidden');
      suggestionsBox.innerHTML = '';

      document.getElementById('employeeLookupMessage')?.classList.add('hidden');
      nameInput.classList.remove('eh-input-error');
      bidInput.classList.remove('eh-input-error');

      if (query.length < 2) return;

      this.employeeLookupTimer = setTimeout(() => {
        this.searchEmployeesByName(query);
      }, 400);
    };

    nameInput.addEventListener('input', () => handleTyping(nameInput));
    bidInput.addEventListener('input', () => handleTyping(bidInput));

    document.addEventListener('click', e => {
      if (e.target !== nameInput && e.target !== bidInput && !suggestionsBox.contains(e.target)) {
        suggestionsBox.classList.add('hidden');
      }
    });

    nameInput.addEventListener('focus', () => {
      anchorSuggestionsBox(nameInput);
      if (!this.employeeLookupValid && suggestionsBox.innerHTML.trim() !== '') suggestionsBox.classList.remove('hidden');
    });

    bidInput.addEventListener('focus', () => {
      anchorSuggestionsBox(bidInput);
      if (!this.employeeLookupValid && suggestionsBox.innerHTML.trim() !== '') suggestionsBox.classList.remove('hidden');
    });
  },

  async searchEmployeesByName(query) {
    const status = document.getElementById('employeeLookupStatus');
    const message = document.getElementById('employeeLookupMessage');
    const suggestionsBox = document.getElementById('employeeNameSuggestions');
    const input = document.getElementById('receiverFullName');

    if (!suggestionsBox) return;

    status?.classList.remove('hidden');
    status?.classList.add('loading');
    message?.classList.add('hidden');
    if (message) message.textContent = '';

    try {
      const url = '/eheart/api/users/lookup_employee.php?q=' + encodeURIComponent(query);
      const res = await EHeart.api(url);
      const employees = Array.isArray(res.data) ? res.data : (res.data ? [res.data] : []);

      if (!employees.length) {
        suggestionsBox.innerHTML = '';
        suggestionsBox.classList.add('hidden');

        if (message) {
          message.textContent = 'No employee found.';
          message.classList.remove('hidden');
        }

        input?.classList.add('eh-input-error');
        return;
      }

      suggestionsBox.innerHTML = employees.map(emp => {
        const fullName =
          emp.full_name ||
          emp.name ||
          ([emp.first_name, emp.middle_name, emp.last_name].filter(Boolean).join(' ')) ||
          ([emp.FirstName, emp.MiddleName, emp.LastName].filter(Boolean).join(' ')) ||
          '';

        const department =
          emp.department ||
          emp.Department ||
          emp.department_name ||
          emp.DepartmentName ||
          '';

        const biometricId =
          emp.biometric_id ||
          emp.BiometricID ||
          emp.employee_id ||
          emp.EmployeeID ||
          '';

        const employeeId =
          emp.employee_id ||
          emp.EmployeeID ||
          '';

        const photoUrl = employeeId
          ? `http://10.2.0.8/lrnph/emp_photos/${employeeId}.jpg`
          : '';

        return `
          <div class="eh-employee-suggestion-item"
               data-biometric-id="${this.escapeHtml(biometricId)}"
               data-employee-id="${this.escapeHtml(employeeId)}"
               data-full-name="${this.escapeHtml(fullName)}"
               data-department="${this.escapeHtml(department)}"
               role="option"
               tabindex="0">
            <img
                src="${photoUrl || '/eheart/assets/default_avatar.jpg'}"
                alt=""
                class="eh-employee-suggestion-photo"
                onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">
            <div class="eh-employee-suggestion-text">
                <div class="eh-employee-suggestion-name">${this.escapeHtml(fullName) || '—'}</div>
                <div class="eh-employee-suggestion-department">${this.escapeHtml(department) || '—'}</div>
            </div>
          </div>
        `;
      }).join('');

      suggestionsBox.classList.remove('hidden');

      suggestionsBox.querySelectorAll('.eh-employee-suggestion-item').forEach(item => {
        item.addEventListener('click', () => this.selectEmployeeSuggestion(item));
        item.addEventListener('keydown', ev => {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            this.selectEmployeeSuggestion(item);
          }
        });
      });

      input?.classList.remove('eh-input-error');
    } catch (error) {
      suggestionsBox.innerHTML = '';
      suggestionsBox.classList.add('hidden');

      const errorMessage = error?.message || 'Employee not found.';

      if (message) {
        message.textContent = errorMessage;
        message.classList.remove('hidden');
      }

      input?.classList.add('eh-input-error');
    } finally {
      status?.classList.add('hidden');
      status?.classList.remove('loading');
    }
  },

  selectEmployeeSuggestion(item) {
    const fullName = item.dataset.fullName || '';
    const department = item.dataset.department || '';
    const biometricId = item.dataset.biometricId || '';

    const nameInput = document.getElementById('receiverFullName');
    const biometricInput = document.getElementById('receiverBiometricId');
    const departmentInput = document.getElementById('receiverDepartment');
    const suggestionsBox = document.getElementById('employeeNameSuggestions');
    const message = document.getElementById('employeeLookupMessage');

    if (nameInput) nameInput.value = fullName;
    if (biometricInput) biometricInput.value = biometricId;
    if (departmentInput) departmentInput.value = department;

    message?.classList.add('hidden');
    if (message) message.textContent = '';
    suggestionsBox?.classList.add('hidden');

    nameInput?.classList.remove('eh-input-error');
    biometricInput?.classList.remove('eh-input-error');

    document.getElementById('employeeInfoCard')?.classList.add('employee-found');
    this.employeeLookupValid = true;
  },

  resetEmployeeLookup(clearName = false) {
    clearTimeout(this.employeeLookupTimer);

    const nameInput = document.getElementById('receiverFullName');
    const biometricInput = document.getElementById('receiverBiometricId');
    const departmentInput = document.getElementById('receiverDepartment');
    const message = document.getElementById('employeeLookupMessage');
    const infoCard = document.getElementById('employeeInfoCard');
    const suggestionsBox = document.getElementById('employeeNameSuggestions');

    if (clearName && nameInput) nameInput.value = '';
    if (biometricInput) biometricInput.value = '';
    if (departmentInput) departmentInput.value = '';

    message?.classList.add('hidden');
    if (message) message.textContent = '';
    suggestionsBox?.classList.add('hidden');
    if (suggestionsBox) suggestionsBox.innerHTML = '';

    infoCard?.classList.remove('employee-found');
    nameInput?.classList.remove('eh-input-error');
    this.employeeLookupValid = false;
  },

  updateClearFiltersButton() {
    const btn = document.getElementById('clearFiltersBtn');
    if (!btn) return;

    const hasFilter =
      !!this.filters.q ||
      !!this.filters.status ||
      !!this.filters.department ||
      !!this.filters.date_from ||
      !!this.filters.date_to;

    btn.classList.toggle('hidden', !hasFilter);
  },

  validateDateRange() {
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');

    if (!dateFrom || !dateTo) return true;

    dateFrom.classList.remove('eh-input-error');
    dateTo.classList.remove('eh-input-error');

    const fromIso = dateFrom.dataset.isoValue || '';
    const toIso = dateTo.dataset.isoValue || '';

    if (fromIso && toIso && fromIso > toIso) {
      dateFrom.classList.add('eh-input-error');
      dateTo.classList.add('eh-input-error');
      EHeart.toast('The start date cannot be later than the end date.', 'error');
      return false;
    }

    return true;
  },

  async loadCoreValues() {
    try {
      const res = await EHeart.api('/eheart/api/core_values/list.php');
      this._coreValues = res.data || [];
    } catch (e) { }
  },

  bindCoreValuePicker() {
    const btn = document.getElementById('cv-select-btn');
    const picker = document.getElementById('cvPicker');
    const list = document.getElementById('cv-select-list');
    if (!btn || !picker || !list) return;

    btn.addEventListener('click', e => {
      e.stopPropagation();
      const isOpen = picker.classList.toggle('open');
      list.classList.toggle('open', isOpen);
    });

    list.addEventListener('click', e => {
      const li = e.target.closest('li[data-value]');
      if (!li) return;
      this.toggleCoreValue(li.dataset.value, li.dataset.label);
    });

    document.addEventListener('click', e => {
      if (!picker.contains(e.target)) {
        picker.classList.remove('open');
        list.classList.remove('open');
      }
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && picker.classList.contains('open')) {
        picker.classList.remove('open');
        list.classList.remove('open');
        btn.focus();
      }
    });
  },

  toggleCoreValue(id, label) {
    const existingIndex = this.selectedCoreValues.findIndex(cv => cv.id === id);
    if (existingIndex > -1) this.selectedCoreValues.splice(existingIndex, 1);
    else this.selectedCoreValues.push({ id, label });
    this.renderChips();
  },

  renderChips() {
    const wrap = document.getElementById('cv-chips');

    if (wrap) {
      wrap.innerHTML = this.selectedCoreValues.map(cv => `
      <span class="chip">
        ${this.escapeHtml(cv.label)}
        <button type="button" data-id="${this.escapeHtml(cv.id)}" onclick="HeartCardPage.removeChip('${this.escapeHtml(cv.id)}')" aria-label="Remove ${this.escapeHtml(cv.label)}">&times;</button>
      </span>
    `).join('');
    }

    const hiddenInput = document.getElementById('core_values');
    if (hiddenInput) hiddenInput.value = JSON.stringify(this.selectedCoreValues.map(c => parseInt(c.id, 10)));

    const list = document.getElementById('cv-select-list');

    if (list) {
      list.querySelectorAll('li[data-value]').forEach(li => {
        const selected = this.selectedCoreValues.some(cv => cv.id === li.dataset.value);
        li.classList.toggle('selected', selected);
      });
    }
  },

  removeChip(id) {
    this.selectedCoreValues = this.selectedCoreValues.filter(cv => cv.id !== id);
    this.renderChips();
  },

  clearChips() {
    this.selectedCoreValues = [];
    this.renderChips();
  },

  async load() {
    if (!this.validateDateRange()) return;

    const params = new URLSearchParams();

    if (this.filters.status) params.set('status', this.filters.status);
    if (this.filters.department) params.set('department', this.filters.department);
    if (this.filters.date_from) params.set('date_from', this.filters.date_from);
    if (this.filters.date_to) params.set('date_to', this.filters.date_to);

    try {
      const query = params.toString();
      const url = '/eheart/api/heart_card/list.php' + (query ? '?' + query : '');
      const res = await EHeart.api(url);

      this._rowsCache = res.data || [];
      this.renderRows(this._rowsCache);

      const cardCount = document.getElementById('cardCount');
      const cardCount2 = document.getElementById('cardCount2');
      const cardCount3 = document.getElementById('cardCount3');

      if (cardCount) cardCount.textContent = this._rowsCache.length;

      const q = (this.filters.q || '').toLowerCase().trim();

      const filtered = q
        ? this._rowsCache.filter(r => this.getSearchableText(r).includes(q))
        : this._rowsCache;

      if (cardCount2) {
        const start = (this.page - 1) * this.pageSize;
        const visibleCount = Math.max(0, Math.min(this.pageSize, filtered.length - start));
        cardCount2.textContent = visibleCount;
      }

      if (cardCount3) cardCount3.textContent = filtered.length;
    } catch (e) { }
  },

  getSearchableText(r) {
    return [
      r.control_number,
      r.receiver_biometric_id,
      r.receiver_full_name,
      r.receiver_department,
      r.status,
      this.coreValueNames(r.core_values)
    ].filter(Boolean).join(' ').toLowerCase();
  },

  renderRows(rows) {
    const q = (this.filters.q || '').toLowerCase().trim();
    const filtered = q ? rows.filter(r => this.getSearchableText(r).includes(q)) : rows;
    const totalPages = Math.max(1, Math.ceil(filtered.length / this.pageSize));

    if (this.page > totalPages) this.page = totalPages;

    const start = (this.page - 1) * this.pageSize;
    const pageRows = filtered.slice(start, start + this.pageSize);
    const tbody = document.getElementById('cardTableBody');

    if (!tbody) return;

    if (pageRows.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8">
            <div class="eh-empty">
              <div class="eh-empty-icon"><i data-lucide="search-x"></i></div>
              <div class="eh-empty-title">No Heart Cards found</div>
              <div class="eh-empty-text">Try adjusting your search or filters.</div>
            </div>
          </td>
        </tr>
      `;

      this.renderPagination(totalPages, filtered.length);

      if (window.lucide) lucide.createIcons();
      return;
    }

    tbody.innerHTML = pageRows.map(r => {
      const controlNumber = this.escapeHtml(r.control_number || '—');
      const biometricId = this.escapeHtml(r.receiver_biometric_id || '—');
      const department = this.escapeHtml(r.receiver_department || '—');
      const employeeName = this.escapeHtml(r.receiver_full_name || biometricId);
      const employeePhotoUrl = r.receiver_employee_id
        ? `http://10.2.0.8/lrnph/emp_photos/${r.receiver_employee_id}.jpg`
        : '';
      const coreValues = this.escapeHtml(this.coreValueNames(r.core_values));
      const date = this.escapeHtml(EHeart.formatDate(r.date_issued || r.created_at) || '—');
      const status = String(r.status || '').toUpperCase();
      const statusClass = ehStatusBadgeClass(status);
      const statusLabel = status.replace(/_/g, ' ');

      return `
        <tr>
          <td class="control-no">${controlNumber}</td>
          <td>
            <div class="eh-table-employee">
              <img
                src="${employeePhotoUrl || '/eheart/assets/default_avatar.jpg'}"
                alt=""
                class="eh-table-employee-photo"
                onerror="this.onerror=null;this.src='/eheart/assets/default_avatar.jpg';">
              <div class="eh-table-employee-name">${employeeName}</div>
            </div>
          </td>
          <td>${department}</td>
          <td>${coreValues}</td>
          <td>${date}</td>
          <td><span class="eh-badge eh-badge-${statusClass}">${this.escapeHtml(statusLabel)}</span></td>
          <td class="eh-col-actions">${this.buildActions(r)}</td>
        </tr>
      `;
    }).join('');

    const cardCount2 = document.getElementById('cardCount2');
    const cardCount3 = document.getElementById('cardCount3');

    if (cardCount2) cardCount2.textContent = pageRows.length;
    if (cardCount3) cardCount3.textContent = filtered.length;

    this.renderPagination(totalPages, filtered.length);

    if (window.lucide) lucide.createIcons();
  },

  renderPagination(totalPages, totalRecords) {
    EHPagination.render({
      container: document.getElementById('cardPagination'),
      gotoInputId: 'cardGotoPage',
      totalPages,
      currentPage: this.page,
      onChange: page => {
        this.page = page;
        this.renderRows(this._rowsCache || []);
        document.querySelector('.eh-table-scroll')?.scrollTo({ top: 0, behavior: 'smooth' });
      }
    });
  },

  buildActions(r) {
    const role = window.EH_ROLE;
    let html = '';

    if (role === 'HR_ADMIN' || role === 'SYSTEM_ADMIN' || role === 'MANAGER') {
      html += `
        <button type="button" class="eh-icon-btn action-view" data-id="${this.escapeHtml(r.heart_card_id)}" title="View Heart Card" aria-label="View Heart Card" onclick="HeartCardPage.viewCard(this, ${Number(r.heart_card_id)})">
          <i data-lucide="eye"></i>
        </button>
      `;
    }

    return html;
  },

  async viewCard(button, id) {
    this.setButtonLoading(button, true, 'Loading...');

    try {
      const res = await EHeart.api('/eheart/api/heart_card/detail.php?id=' + encodeURIComponent(id));
      this.openPanel('view', res.data);
    } catch (e) {
    } finally {
      this.setButtonLoading(button, false);
    }
  },

  openPanel(mode, data = null) {
    const splitLayout = document.getElementById('splitLayout');
    const form = document.getElementById('requestForm');
    const detailsView = document.getElementById('detailsView');
    const title = document.getElementById('panelTitle');

    if (!splitLayout || !form || !detailsView || !title) return;

    if (mode === 'view' && data) {
      title.textContent = 'Heart Card Details';
      form.classList.add('hidden');
      detailsView.classList.remove('hidden');
      this.fillDetails(data);
    } else {
      title.textContent = 'Request Heart Card';
      detailsView.classList.add('hidden');
      form.classList.remove('hidden');
      form.reset();
      this.resetEmployeeLookup(true);
      this.clearChips();
      this.resetChecklist(this.requestChecklistIds);
      this.updateRequestSubmitState();
      this.loadManagerQuota();
      document.getElementById('dvRemarksBox')?.classList.add('hidden');
      document.getElementById('dvActions')?.classList.remove('hidden');
    }

    splitLayout.classList.add('panel-open');

    if (window.lucide) lucide.createIcons();
  },

  fillDetails(data) {
    this.currentCardData = data;

    const control = document.getElementById('dvControl');
    if (control) control.textContent = data.control_number || '—';

    const status = (data.status || '').toUpperCase();
    const badge = document.getElementById('dvStatusBadge');

    if (badge) {
      badge.textContent = status + ' REQUEST';
      badge.className = 'eh-details-status eh-badge eh-badge-' + ehStatusBadgeClass(status);
    }

    const action = document.getElementById('dvAction');
    if (action) action.textContent = data.action_performed || '—';

    const requestedBy = document.getElementById('dvRequestedBy');

    if (requestedBy) {
      requestedBy.textContent = data.requester_full_name || data.requester_biometric_id || '—';
    }

    const requestedByPhoto = document.getElementById('dvRequestedByPhoto');

    if (requestedByPhoto) {
      requestedByPhoto.src = data.requester_employee_id
        ? `http://10.2.0.8/lrnph/emp_photos/${data.requester_employee_id}.jpg`
        : '/eheart/assets/default_avatar.jpg';
    }

    const noteRow = document.getElementById('dvInspiringNoteRow');

    if (status === 'FOR_REDEMPTION' || status === 'REDEEMED') {
      noteRow?.classList.remove('hidden');

      const note = document.getElementById('dvInspiringNote');
      if (note) note.textContent = data.short_inspiring_note || '—';
    } else {
      noteRow?.classList.add('hidden');
    }

    // ============================================
    // PROCESSED BY / PROCESSED DATE
    // Shared row for REDEEMED (redemption), APPROVED
    // and REJECTED (HR review) — labels swap per status
    // ============================================
    const redeemedByRow = document.getElementById('dvRedeemedByRow');
    const redeemedDateRow = document.getElementById('dvRedeemedDateRow');
    const redeemedByLabel = document.getElementById('dvRedeemedByLabel');
    const redeemedDateLabel = document.getElementById('dvRedeemedDateLabel');
    const redeemedBy = document.getElementById('dvRedeemedBy');
    const redeemedByPhoto = document.getElementById('dvRedeemedByPhoto');
    const redeemedDate = document.getElementById('dvRedeemedDate');

    const rejectReasonRow = document.getElementById('dvRejectionReasonRow');
    const rejectReasonVal = document.getElementById('dvRejectionReason');

    if (status === 'REJECTED') {
      rejectReasonRow?.classList.remove('hidden');
      if (rejectReasonVal) rejectReasonVal.textContent = data.review_feedback || '—';
    } else {
      rejectReasonRow?.classList.add('hidden');
    }

    if (status === 'REDEEMED' || status === 'APPROVED' || status === 'REJECTED' || status === 'FOR_REDEMPTION') {
      redeemedByRow?.classList.remove('hidden');
      redeemedDateRow?.classList.remove('hidden');

      let processedByName, processedByEmployeeId, processedDate, byLabel, dateLabel;

      if (status === 'REDEEMED') {
        processedByName = data.processed_by_name || data.processed_by_biometric_id;
        processedByEmployeeId = data.processed_by_employee_id;
        processedDate = data.redeemed_at;
        byLabel = 'Processed by';
        dateLabel = 'Redeemed Date';
      } else {
        // APPROVED, REJECTED, and FOR_REDEMPTION (already approved) all show the reviewer
        processedByName = data.reviewed_by_full_name || data.reviewed_by_biometric_id;
        processedByEmployeeId = data.reviewed_by_employee_id;
        processedDate = data.reviewed_at;
        byLabel = status === 'REJECTED' ? 'Rejected by' : 'Approved by';
        dateLabel = status === 'REJECTED' ? 'Date Rejected' : 'Date Approved';
      }

      if (redeemedByLabel) redeemedByLabel.textContent = byLabel;
      if (redeemedDateLabel) redeemedDateLabel.textContent = dateLabel;
      if (redeemedBy) redeemedBy.textContent = processedByName || '—';
      if (redeemedDate) redeemedDate.textContent = EHeart.formatDate(processedDate) || '—';

      if (redeemedByPhoto) {
        redeemedByPhoto.src = processedByEmployeeId
          ? `http://10.2.0.8/lrnph/emp_photos/${processedByEmployeeId}.jpg`
          : '/eheart/assets/default_avatar.jpg';
      }
    } else {
      redeemedByRow?.classList.add('hidden');
      redeemedDateRow?.classList.add('hidden');
    }

    const isEditable = status === 'FOR_REVISION' && window.EH_ROLE === 'MANAGER';
    const impactText = document.getElementById('dvBusinessImpact');
    const impactInput = document.getElementById('dvBusinessImpactInput');
    const whyText = document.getElementById('dvWhyBeyond');
    const whyInput = document.getElementById('dvWhyBeyondInput');
    const revisionRow = document.getElementById('dvRevisionNoteRow');
    const revisionNote = document.getElementById('dvRevisionNote');
    const otherRowIds = ['dvActionRow', 'dvRequestedByRow'];

    if (isEditable) {
      impactText?.classList.add('hidden');
      impactInput?.classList.remove('hidden');
      if (impactInput) impactInput.value = data.business_impact || '';

      whyText?.classList.add('hidden');
      whyInput?.classList.remove('hidden');
      if (whyInput) whyInput.value = data.why_beyond_normal || '';

      revisionRow?.classList.remove('hidden');
      if (revisionNote) revisionNote.textContent = data.review_feedback || '—';

      otherRowIds.forEach(rowId => document.getElementById(rowId)?.classList.add('hidden'));
    } else {
      impactText?.classList.remove('hidden');
      impactInput?.classList.add('hidden');
      if (impactText) impactText.textContent = data.business_impact || '—';

      whyText?.classList.remove('hidden');
      whyInput?.classList.add('hidden');
      if (whyText) whyText.textContent = data.why_beyond_normal || '—';

      revisionRow?.classList.add('hidden');
      otherRowIds.forEach(rowId => document.getElementById(rowId)?.classList.remove('hidden'));
    }

    if (window.EH_ROLE === 'MANAGER') {
      document.getElementById('dvRequestedByRow')?.classList.add('hidden');
    }

    this.renderDetailsActions(data);

    const giveBox = document.getElementById('dvGiveBox');

    if (giveBox) {
      const canGive = window.EH_ROLE === 'MANAGER' && status === 'APPROVED';
      giveBox.classList.toggle('hidden', !canGive);

      if (canGive) {
        const noteInput = document.getElementById('dvGiveNoteInput');

        if (noteInput) {
          noteInput.value = '';
          noteInput.classList.remove('eh-input-error');
        }
      }
    }

    if (window.lucide) lucide.createIcons();
  },

  renderDetailsActions(data) {
    const wrap = document.getElementById('dvActions');
    if (!wrap) return;

    this.hideRemarksBox();

    const role = window.EH_ROLE;
    const status = (data.status || '').toUpperCase();
    const id = Number(data.heart_card_id);
    let html = '';

    if ((role === 'HR_ADMIN' || role === 'SYSTEM_ADMIN') && status === 'PENDING') {
      html += `
        <button type="button" class="eh-btn eh-btn-outline-warn" onclick="HeartCardPage.openRemarksBox('revision', ${id})">Send for Revision</button>
        <button type="button" class="eh-btn eh-btn-outline-danger" onclick="HeartCardPage.openRemarksBox('reject', ${id})">Reject</button>
        <button type="button" class="eh-btn eh-btn-primary" onclick="HeartCardPage.openRemarksBox('approve', ${id})">Approve</button>
      `;
    }

    if (role === 'MANAGER' && status === 'FOR_REVISION') {
      html += `<button type="button" class="eh-btn eh-btn-primary" id="dvResubmitBtn" onclick="HeartCardPage.resubmitCard(${id})">Resubmit for Approval</button>`;
    }

    wrap.innerHTML = html;

    if (window.lucide) lucide.createIcons();
  },

  openRemarksBox(type, id) {
    this.pendingAction = { type, id };

    const box = document.getElementById('dvRemarksBox');
    const label = document.getElementById('dvRemarksLabel');
    const input = document.getElementById('dvRemarksInput');
    const confirmBtn = document.getElementById('dvRemarksConfirm');
    const approveChecklist = document.getElementById('dvApproveChecklist');

    if (!box || !label || !input || !confirmBtn) return;

    const labels = {
      approve: 'Remarks',
      reject: 'Reason for Rejection',
      revision: 'Revision Notes'
    };

    const placeholders = {
      approve: 'Add any remarks for this approval...',
      reject: 'State why this request is rejected...',
      revision: 'Tell the Manager what needs to be revised...'
    };

    const confirmLabels = {
      approve: 'Confirm Approve',
      reject: 'Confirm Reject',
      revision: 'Send for Revision'
    };

    const confirmClass = {
      approve: 'eh-btn-primary',
      reject: 'eh-btn-danger',
      revision: 'eh-btn-warn'
    };

    label.textContent = labels[type];
    input.value = '';
    input.classList.remove('eh-input-error');
    input.placeholder = placeholders[type];
    confirmBtn.textContent = confirmLabels[type];
    confirmBtn.className = 'eh-btn ' + confirmClass[type];

    if (approveChecklist) {
      approveChecklist.classList.toggle('hidden', type !== 'approve');

      if (type === 'approve') this.resetChecklist(this.approveChecklistIds);
    }

    if (type === 'approve') {
      input.disabled = true;
      confirmBtn.disabled = true;
    } else {
      input.disabled = false;
      confirmBtn.disabled = false;
    }

    box.classList.remove('hidden');
    document.getElementById('dvActions')?.classList.add('hidden');
    input.focus();
  },

  hideRemarksBox() {
    document.getElementById('dvRemarksBox')?.classList.add('hidden');
    document.getElementById('dvApproveChecklist')?.classList.add('hidden');
    document.getElementById('dvActions')?.classList.remove('hidden');

    const input = document.getElementById('dvRemarksInput');
    const confirmBtn = document.getElementById('dvRemarksConfirm');

    if (input) input.disabled = false;
    if (confirmBtn) confirmBtn.disabled = false;

    this.pendingAction = null;
  },

  async confirmRemarksAction() {
    if (!this.pendingAction) return;

    const input = document.getElementById('dvRemarksInput');
    const confirmBtn = document.getElementById('dvRemarksConfirm');
    if (!input || !confirmBtn) return;

    const reason = input.value.trim();
    const { type, id } = this.pendingAction;

    if (type === 'approve' && !this.checklistPassed(this.approveChecklistIds)) {
      EHeart.toast('Please confirm all checklist items before approving.', 'error');
      return;
    }

    if (type !== 'approve' && !reason) {
      input.classList.add('eh-input-error');
      EHeart.toast('Remarks are required.', 'error');
      input.focus();
      return;
    }

    input.classList.remove('eh-input-error');
    this.setButtonLoading(confirmBtn, true, 'Processing...');

    const config = {
      approve: {
        endpoint: '/eheart/api/heart_card/approve.php',
        msg: 'Heart Card approved.'
      },
      reject: {
        endpoint: '/eheart/api/heart_card/reject.php',
        msg: 'Heart Card rejected.'
      },
      revision: {
        endpoint: '/eheart/api/heart_card/request_revision.php',
        msg: 'Sent back to Manager for revision.'
      }
    }[type];

    if (!config) {
      this.setButtonLoading(confirmBtn, false);
      return;
    }

    const body = type === 'approve'
      ? { heart_card_id: id, remarks: reason }
      : { heart_card_id: id, reason };

    try {
      await EHeart.api(config.endpoint, {
        method: 'POST',
        body
      });

      EHeart.toast(config.msg, 'success');
      this.pendingAction = null;
      this.closePanel();
      await this.load();
    } catch (e) {
    } finally {
      this.setButtonLoading(confirmBtn, false);
    }
  },

  async resubmitCard(id) {
    const data = this.currentCardData;
    if (!data) return;

    const impactInput = document.getElementById('dvBusinessImpactInput');
    const whyInput = document.getElementById('dvWhyBeyondInput');

    const businessImpact = impactInput?.value.trim() || '';
    const whyBeyond = whyInput?.value.trim() || '';

    if (!businessImpact || !whyBeyond) {
      EHeart.toast('Business Impact and Why Beyond Normal are required.', 'error');
      return;
    }

    const payload = {
      heart_card_id: id,
      receiver_biometric_id: data.receiver_biometric_id,
      receiver_department: data.receiver_department,
      receiver_full_name: data.receiver_full_name,
      core_values: data.core_values,
      action_performed: data.action_performed,
      business_impact: businessImpact,
      why_beyond_normal: whyBeyond
    };

    const button = document.getElementById('dvResubmitBtn');
    this.setButtonLoading(button, true, 'Resubmitting...');

    try {
      await EHeart.api('/eheart/api/heart_card/resubmit.php', {
        method: 'POST',
        body: payload
      });

      EHeart.toast('Heart Card resubmitted for approval.', 'success');
      this.closePanel();
      await this.load();
    } catch (e) {
    } finally {
      this.setButtonLoading(button, false);
    }
  },

  async confirmGive() {
    const data = this.currentCardData;
    if (!data) return;

    if (window.EH_ROLE !== 'MANAGER') {
      EHeart.toast('Only the Manager can send this Heart Card to the employee.', 'error');
      return;
    }

    if (String(data.status || '').toUpperCase() !== 'APPROVED') {
      EHeart.toast('Only HR-approved Heart Cards can be sent to the employee.', 'error');
      return;
    }

    const noteInput = document.getElementById('dvGiveNoteInput');
    if (!noteInput) return;

    const note = noteInput.value.trim();

    if (!note) {
      noteInput.classList.add('eh-input-error');
      EHeart.toast('Short Inspiring Note is required.', 'error');
      noteInput.focus();
      return;
    }

    if (note.length > 140) {
      noteInput.classList.add('eh-input-error');
      EHeart.toast('Short Inspiring Note must not exceed 140 characters.', 'error');
      noteInput.focus();
      return;
    }

    noteInput.classList.remove('eh-input-error');

    const button = document.getElementById('dvGiveConfirm');
    this.setButtonLoading(button, true, 'Sending...');

    try {
      await EHeart.api('/eheart/api/heart_card/manager_give.php', {
        method: 'POST',
        body: {
          heart_card_id: data.heart_card_id,
          short_inspiring_note: note
        }
      });

      EHeart.toast('Heart Card sent to the employee for redemption.', 'success');
      this.closePanel();
      await this.load();
    } catch (e) {
    } finally {
      this.setButtonLoading(button, false);
    }
  },

  closePanel() {
    clearTimeout(this.employeeLookupTimer);

    document.getElementById('splitLayout')?.classList.remove('panel-open');
    document.getElementById('detailsView')?.classList.add('hidden');
    document.getElementById('requestForm')?.classList.remove('hidden');

    const form = document.getElementById('requestForm');
    if (form) form.reset();

    this.resetEmployeeLookup(true);
    this.clearChips();
    this.resetChecklist(this.requestChecklistIds);
    this.updateRequestSubmitState();
    this.hideRemarksBox();

    document.getElementById('dvGiveBox')?.classList.add('hidden');

    this.currentCardData = null;

    const title = document.getElementById('panelTitle');
    if (title) title.textContent = 'Request Heart Card';
  },

  exportToExcel() {
    const rows = this._rowsCache || [];
    const issued = rows.length;
    const redeemed = rows.filter(r => r.status === 'REDEEMED').length;
    const pool = rows.filter(r => r.status === 'FOR_REDEMPTION').length;

    const typeLabel = status => {
      const map = {
        PENDING: 'Pending Approval',
        FOR_REVISION: 'For Revision',
        APPROVED: 'Approved - Awaiting Manager',
        FOR_REDEMPTION: 'Given to Employee',
        REDEEMED: 'Redeemed',
        REJECTED: 'Rejected',
        CANCELLED: 'Cancelled',
        EXPIRED: 'Expired'
      };
      return map[status] || status;
    };

    EHeartExport.toExcel({
      fileName: 'heart_card_tracker',
      sheetName: 'Heart Card Tracker',
      title: 'MASTER TRACKER',
      subtitle: 'Central record of all Heart transactions and balances',
      topRight: 'LAST UPDATED: ' + (EHeart.formatDate(new Date()) || ''),
      stats: [
        ['Total Hearts Issued', issued],
        ['Total Hearts Redeemed', redeemed],
        ['Hearts Available (Pool)', pool],
        ['Last Updated', EHeart.formatDate(new Date()) || '']
      ],
      columns: [
        'Control No.',
        'Date Issued',
        'Transaction Type',
        'Manager (Department)',
        'Employee Recognized',
        'Core Values',
        'Qty',
        'Status',
        'Date Redeemed',
        'Action Performed',
        'Recorded By'
      ],
      statusColumn: 'Status',
      rows: rows.map(r => [
        r.control_number || '',
        EHeart.formatDate(r.date_issued || r.created_at) || '',
        typeLabel(String(r.status || '').toUpperCase()),
        (r.requester_full_name || r.requester_biometric_id || '') +
        (r.requester_department ? ` (${r.requester_department})` : ''),
        r.receiver_full_name || r.receiver_biometric_id || '',
        this.coreValueNames(r.core_values),
        1,
        String(r.status || '').toUpperCase(),
        r.status === 'REDEEMED' ? (EHeart.formatDate(r.redeemed_at) || '') : '',
        r.action_performed || '',
        r.status === 'REDEEMED'
          ? (r.processed_by_name || r.processed_by_biometric_id || '')
          : (r.reviewed_by_full_name || r.reviewed_by_biometric_id || '')
      ])
    });
  },

  coreValueNames(ids) {
    if (!ids || !Array.isArray(ids) || !ids.length) return '—';

    const list = this._coreValues || [];

    const names = ids.map(id => {
      const found = list.find(cv => Number(cv.core_value_id) === Number(id));
      return found ? found.core_value_name : id;
    });

    return names.join(', ');
  },

  async submitRequest(e) {
    e.preventDefault();

    const form = e.target;
    if (!form) return;

    if (!this.employeeLookupValid) {
      EHeart.toast('Please select a valid employee from the suggestions.', 'error');
      document.getElementById('receiverFullName')?.focus();
      return;
    }

    if (!this.checklistPassed(this.requestChecklistIds)) {
      EHeart.toast('Please confirm all checklist items before submitting.', 'error');
      return;
    }

    const fd = new FormData(form);

    const payload = {
      receiver_biometric_id: fd.get('receiver_biometric_id'),
      receiver_department: fd.get('receiver_department'),
      receiver_full_name: fd.get('receiver_full_name'),
      core_values: this.selectedCoreValues.map(cv => parseInt(cv.id, 10)),
      action_performed: fd.get('specific_action'),
      business_impact: fd.get('business_operational_request'),
      why_beyond_normal: fd.get('why_beyond_normal')
    };

    if (!payload.core_values.length) {
      EHeart.toast('Select at least one Core Value.', 'error');
      return;
    }

    const button = document.getElementById('submitRequestBtn');
    this.setButtonLoading(button, true, 'Submitting...');

    try {
      await EHeart.api('/eheart/api/heart_card/create.php', {
        method: 'POST',
        body: payload
      });

      EHeart.toast('Heart Card request submitted.', 'success');

      form.reset();
      this.resetEmployeeLookup(true);
      this.clearChips();
      this.resetChecklist(this.requestChecklistIds);
      this.updateRequestSubmitState();
      this.closePanel();
      await this.load();
      await this.loadManagerQuota();
    } catch (e) {
      // EHeart.api already surfaces the backend error message via toast.
      // Don't show a second toast here.
    } finally {
      this.setButtonLoading(button, false);
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
  HeartCardPage.init();
});