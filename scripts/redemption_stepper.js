const RedemptionStepper = {
  state: {
    employee: null,
    heartCard: null,
    redemptionId: null,
    gc: null,
    searchMode: 'employee_id',
    qrStream: null,
    qrRafId: null,
    qrScanning: false
  },
  els: {},
  init() {
    this.els = {
      step1: document.getElementById('step1'),
      step2: document.getElementById('step2'),
      step3: document.getElementById('step3'),
      step4: document.getElementById('step4'),
      indicator: document.getElementById('stepperIndicator'),
      searchModeToggle: document.getElementById('searchModeToggle'),
      searchInputCard: document.getElementById('searchInputCard'),
      searchInputLabel: document.getElementById('searchInputLabel'),
      employeeIdInput: document.getElementById('employeeIdInput'),
      searchEmployeeBtn: document.getElementById('searchEmployeeBtn'),
      qrScanCard: document.getElementById('qrScanCard'),
      qrVideo: document.getElementById('qrVideo'),
      qrCanvas: document.getElementById('qrCanvas'),
      qrStatus: document.getElementById('qrStatus'),
      empName: document.getElementById('empName'),
      empId: document.getElementById('empId'),
      empDept: document.getElementById('empDept'),
      empInitial: document.getElementById('empInitial'),
      hcCode: document.getElementById('hcCode'),
      hcBrand: document.getElementById('hcBrand'),
      hcReward: document.getElementById('hcReward'),
      hcIssueDate: document.getElementById('hcIssueDate'),
      hcStatus: document.getElementById('hcStatus'),
      validationNotes: document.getElementById('validationNotes'),
      step1Error: document.getElementById('step1Error'),
      step2Error: document.getElementById('step2Error'),
      cancelStep2Btn: document.getElementById('cancelStep2Btn'),
      proceedStep2Btn: document.getElementById('proceedStep2Btn'),
      gcSerialNumber: document.getElementById('gcSerialNumber'),
      step3Error: document.getElementById('step3Error'),
      backStep3Btn: document.getElementById('backStep3Btn'),
      releaseGcBtn: document.getElementById('releaseGcBtn'),
      sEmployee: document.getElementById('sEmployee'),
      sHeartCard: document.getElementById('sHeartCard'),
      sReward: document.getElementById('sReward'),
      sGcSerial: document.getElementById('sGcSerial'),
      sRedeemedAt: document.getElementById('sRedeemedAt'),
      sProcessedBy: document.getElementById('sProcessedBy'),
      doneBtn: document.getElementById('doneBtn')
    };

    this.els.searchModeToggle.addEventListener('click', (e) => {
      const btn = e.target.closest('.eh-toggle-btn');
      if (!btn) return;
      this.setSearchMode(btn.dataset.mode);
    });

    this.els.searchEmployeeBtn.addEventListener('click', () => this.searchEmployee());
    this.els.employeeIdInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.searchEmployee();
      }
    });

    this.els.cancelStep2Btn.addEventListener('click', () => this.reset());
    this.els.proceedStep2Btn.addEventListener('click', () => this.validateRedemption());
    this.els.backStep3Btn.addEventListener('click', () => this.cancelValidation());
    this.els.releaseGcBtn.addEventListener('click', () => this.releaseGc());
    this.els.doneBtn.addEventListener('click', () => this.reset());
  },

  // ===================================================================
  // SEARCH MODE SWITCHING (Employee ID / Heart Card / Scan QR)
  // ===================================================================
  setSearchMode(mode) {
    this.state.searchMode = mode;

    this.els.searchModeToggle.querySelectorAll('.eh-toggle-btn').forEach((b) => {
      b.classList.toggle('active', b.dataset.mode === mode);
    });

    this.setError(this.els.step1Error, '');

    if (mode === 'qr_scan') {
      this.els.searchInputCard.hidden = true;
      this.els.qrScanCard.hidden = false;
      this.startQrScanner();
      return;
    }

    // Leaving QR mode (or was never in it) - make sure camera is off.
    this.stopQrScanner();
    this.els.qrScanCard.hidden = true;
    this.els.searchInputCard.hidden = false;

    const isEmployee = mode === 'employee_id';
    this.els.searchInputLabel.textContent = isEmployee ? 'Biometrics ID' : 'Heart Card Control No.';
    this.els.employeeIdInput.placeholder = isEmployee ? 'Enter Biometrics ID' : 'Enter Control No.';
    this.els.employeeIdInput.value = '';
    this.els.employeeIdInput.focus();
  },

  // ===================================================================
  // QR SCANNING (camera + jsQR)
  // ===================================================================
  async startQrScanner() {
    if (this.state.qrScanning) return;

    if (typeof jsQR !== 'function') {
      this.setQrStatus('QR scanning library failed to load. Please refresh the page.', 'error');
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      this.setQrStatus('Camera access is not supported in this browser.', 'error');
      return;
    }

    this.setQrStatus('Requesting camera access...');

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
        audio: false
      });

      this.state.qrStream = stream;
      this.els.qrVideo.srcObject = stream;
      await this.els.qrVideo.play();

      this.state.qrScanning = true;
      this.setQrStatus('Point the camera at the QR code on the Heart Card.');
      this.qrScanLoop();
    } catch (err) {
      console.error('Camera error:', err);
      this.setQrStatus('Unable to access the camera. Check permissions and try again.', 'error');
    }
  },

  stopQrScanner() {
    this.state.qrScanning = false;

    if (this.state.qrRafId) {
      cancelAnimationFrame(this.state.qrRafId);
      this.state.qrRafId = null;
    }

    if (this.state.qrStream) {
      this.state.qrStream.getTracks().forEach((track) => track.stop());
      this.state.qrStream = null;
    }

    if (this.els.qrVideo) {
      this.els.qrVideo.pause();
      this.els.qrVideo.srcObject = null;
    }
  },

  qrScanLoop() {
    if (!this.state.qrScanning) return;

    const video = this.els.qrVideo;
    const canvas = this.els.qrCanvas;

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;

      const ctx = canvas.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert'
      });

      if (code && code.data) {
        this.handleQrDecoded(code.data.trim());
        return;
      }
    }

    this.state.qrRafId = requestAnimationFrame(() => this.qrScanLoop());
  },

  setQrStatus(msg, type) {
    if (!this.els.qrStatus) return;
    this.els.qrStatus.textContent = msg;
    this.els.qrStatus.classList.remove('is-error', 'is-success');
    if (type === 'error') this.els.qrStatus.classList.add('is-error');
    if (type === 'success') this.els.qrStatus.classList.add('is-success');
  },

  async handleQrDecoded(code) {
    if (!code) return;

    // Stop scanning immediately so we don't fire this multiple times
    // while the lookup request is in flight.
    this.state.qrScanning = false;
    if (this.state.qrRafId) {
      cancelAnimationFrame(this.state.qrRafId);
      this.state.qrRafId = null;
    }

    this.setQrStatus('QR code detected: ' + code, 'success');

    // Heart Card QR codes encode the control number directly
    // (see HeartCardService::generateAndStoreQrCode). Reuse the same
    // lookup flow as the manual "Heart Card" search mode.
    this.els.employeeIdInput.value = code;

    const found = await this.performSearch('control_number', code);

    if (!found) {
      // Give the user a moment to read the status, then resume scanning
      // so they can retry without re-selecting the mode.
      setTimeout(() => {
        if (this.state.searchMode === 'qr_scan') {
          this.setQrStatus('Point the camera at the QR code on the Heart Card.');
          this.state.qrScanning = true;
          this.qrScanLoop();
        }
      }, 1500);
    } else {
      this.stopQrScanner();
    }
  },

  // ===================================================================
  // SEARCH (shared by manual entry and QR scan)
  // ===================================================================
  async searchEmployee() {
    const query = this.els.employeeIdInput.value.trim();
    const isEmployee = this.state.searchMode === 'employee_id';

    if (!query) {
      this.setError(this.els.step1Error, isEmployee ? 'Enter an Biometrics ID.' : 'Enter a Heart Card Control No.');
      return;
    }

    const param = isEmployee ? 'employee_id' : 'control_number';
    await this.performSearch(param, query);
  },

  /**
   * Runs the lookup for a given param ('employee_id' | 'control_number')
   * and value. Returns true if a Heart Card was found and step 2 was shown.
   */
  async performSearch(param, query) {
    this.setError(this.els.step1Error, '');

    const isControlLookup = param === 'control_number';
    const originalButtonText = this.els.searchEmployeeBtn.innerHTML;

    try {
      this.els.searchEmployeeBtn.disabled = true;
      this.els.searchEmployeeBtn.textContent = 'Searching...';

      const res = await EHeart.api('/eheart/api/heart_card/lookup.php?' + param + '=' + encodeURIComponent(query), { method: 'GET' });
      const data = res.data;

      if (!data.heart_card) {
        const msg = isControlLookup
          ? 'No Heart Card found for this Control No.'
          : 'No available Heart Card found for this employee.';
        this.setError(this.els.step1Error, msg);
        this.setQrStatus(msg, 'error');
        return false;
      }

      this.state.employee = data.employee;
      this.state.heartCard = data.heart_card;
      this.fillStep2();
      this.goToStep(2);
      return true;
    } catch (e) {
      console.error('Search error:', e);
      const msg = e instanceof Error && e.message
        ? e.message
        : (isControlLookup ? 'Not found. Verify the Control No.' : 'Not found. Verify the Biometrics ID.');
      this.setError(this.els.step1Error, msg);
      this.setQrStatus(msg, 'error');
      return false;
    } finally {
      this.els.searchEmployeeBtn.disabled = false;
      this.els.searchEmployeeBtn.innerHTML = originalButtonText;
    }
  },

  goToStep(n) {
    // Camera should never stay on once we leave step 1.
    if (n !== 1) {
      this.stopQrScanner();
    }

    [1, 2, 3, 4].forEach((i) => {
      this.els[`step${i}`].hidden = i !== n;
    });
    this.els.indicator.querySelectorAll('.eh-stepper-node').forEach((node) => {
      const nodeNum = parseInt(node.dataset.node, 10);
      node.classList.toggle('active', nodeNum === n);
      node.classList.toggle('done', nodeNum < n);
    });
  },

  setError(el, msg) {
    if (!el) return;
    el.textContent = msg || '';
  },

  fillStep2() {
    const emp = this.state.employee;
    const hc = this.state.heartCard;
    this.els.empName.textContent = emp.name || '—';
    this.els.empId.textContent = emp.employee_number || emp.biometric_id || '—';
    this.els.empDept.textContent = emp.department || '—';
    if (this.els.empInitial && emp.name) {
      this.els.empInitial.textContent = emp.name.trim().charAt(0).toUpperCase();
    }
    this.els.hcCode.textContent = hc.code || '—';
    this.els.hcBrand.textContent = hc.brand || 'Heart Card';
    this.els.hcReward.textContent = hc.reward || 'Gift Certificate';
    const createdDate = hc.issue_date ? new Date(String(hc.issue_date).replace(' ', 'T')) : null;
    this.els.hcIssueDate.textContent = createdDate && !Number.isNaN(createdDate.getTime()) ? new Intl.DateTimeFormat('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
    }).format(createdDate).replace(' at ', ' - ') : '—';
    const status = String(hc.status || 'READY FOR REDEMPTION').toUpperCase();
    this.els.hcStatus.textContent = status.replace(/_/g, ' ');
    this.els.hcStatus.className = 'eh-status-pill status-' + status.toLowerCase().replace(/\s+/g, '_');
    this.setError(this.els.step2Error, '');
  },

  async validateRedemption() {
    this.setError(this.els.step2Error, '');
    try {
      const res = await EHeart.api('/eheart/api/redemption/validate.php', {
        method: 'POST',
        body: {
          heart_card_id: this.state.heartCard.heart_card_id,
          decision: 'APPROVE',
          notes: this.els.validationNotes.value.trim() || null
        }
      });
      this.state.redemptionId = res.data.redemption_id;
      this.fillStep3();
      this.goToStep(3);
    } catch (e) {
      console.error('Validation error:', e);
      this.setError(this.els.step2Error, e instanceof Error && e.message
        ? e.message
        : 'Unable to validate redemption. Please try again.');
    }
  },

  fillStep3() {
    const emp = this.state.employee;
    const hc = this.state.heartCard;
    document.getElementById('sumEmployee').textContent = emp.employee_number || emp.biometric_id || '—';
    document.getElementById('sumHeartCard').textContent = hc.code || '—';
    document.getElementById('sumReward').textContent = hc.reward || 'Gift Certificate';
    document.getElementById('sumAmount').textContent = hc.amount ? `₱${Number(hc.amount).toFixed(2)}` : '—';
    this.els.gcSerialNumber.value = '';
    this.setError(this.els.step3Error, '');
  },

  async cancelValidation() {
    if (!this.state.redemptionId) {
      this.reset();
      return;
    }
    try {
      await EHeart.api('/eheart/api/redemption/cancel.php', {
        method: 'POST',
        body: { redemption_id: this.state.redemptionId }
      });
      this.reset();
    } catch (e) {
      console.error('Cancellation error:', e);
      this.setError(this.els.step3Error, e instanceof Error && e.message
        ? e.message
        : 'Unable to cancel this redemption. Please try again.');
    }
  },

  async releaseGc() {
    this.setError(this.els.step3Error, '');
    const serial = this.els.gcSerialNumber.value.trim();
    if (!serial) {
      this.setError(this.els.step3Error, 'Enter the Gift Certificate serial number.');
      return;
    }
    const originalButtonText = this.els.releaseGcBtn.innerHTML;
    try {
      this.els.releaseGcBtn.disabled = true;
      this.els.releaseGcBtn.textContent = 'Releasing...';
      const res = await EHeart.api('/eheart/api/redemption/release.php', {
        method: 'POST',
        body: {
          redemption_id: this.state.redemptionId,
          gc_serial_number: serial
        }
      });
      this.state.gc = res.data;
      this.fillStep4();
      this.goToStep(4);
    } catch (e) {
      console.error('Gift Certificate release error:', e);
      this.setError(this.els.step3Error, e instanceof Error && e.message
        ? e.message
        : 'Unable to release Gift Certificate. It may already be released.');
    } finally {
      this.els.releaseGcBtn.disabled = false;
      this.els.releaseGcBtn.innerHTML = originalButtonText;
    }
  },

  fillStep4() {
    const emp = this.state.employee;
    const hc = this.state.heartCard;
    this.els.sEmployee.textContent = emp.name || '—';
    this.els.sHeartCard.textContent = hc.code || '—';
    this.els.sReward.textContent = hc.reward || 'Gift Certificate';
    this.els.sGcSerial.textContent = this.els.gcSerialNumber.value.trim() || '—';
    this.els.sRedeemedAt.textContent = new Date().toLocaleString();
    this.els.sProcessedBy.textContent = window.eHeartProcessedByName || (window.EHeart && EHeart.currentUser && EHeart.currentUser.name) || '—';
    EHeart.toast('Gift Certificate released.', 'success');
  },

  reset() {
    this.stopQrScanner();

    this.state = {
      employee: null,
      heartCard: null,
      redemptionId: null,
      gc: null,
      searchMode: 'employee_id',
      qrStream: null,
      qrRafId: null,
      qrScanning: false
    };

    this.els.employeeIdInput.value = '';
    this.els.validationNotes.value = '';
    if (this.els.empInitial) {
      this.els.empInitial.textContent = 'E';
    }
    this.els.gcSerialNumber.value = '';

    this.els.qrScanCard.hidden = true;
    this.els.searchInputCard.hidden = false;

    this.els.searchModeToggle.querySelectorAll('.eh-toggle-btn').forEach((b) => {
      b.classList.toggle('active', b.dataset.mode === 'employee_id');
    });
    this.els.searchInputLabel.textContent = 'Biometrics ID';
    this.els.employeeIdInput.placeholder = 'Enter Biometrics ID';

    this.setError(this.els.step1Error, '');
    this.setError(this.els.step2Error, '');
    this.setError(this.els.step3Error, '');

    this.goToStep(1);
    setTimeout(() => {
      this.els.employeeIdInput.focus();
    }, 150);
  }
};

document.addEventListener('DOMContentLoaded', () => RedemptionStepper.init());

// Stop the camera if the user navigates away without clicking anything else.
window.addEventListener('beforeunload', () => {
  if (RedemptionStepper.state.qrStream) {
    RedemptionStepper.state.qrStream.getTracks().forEach((track) => track.stop());
  }
});