const EHeart = {
  async api(path, options = {}) {
    const res = await fetch(path, {
      method: options.method || 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const raw = await res.text();
    let json;

    try {
      json = raw ? JSON.parse(raw) : {};
    } catch (error) {
      json = {
        success: false,
        message: raw.trim() || `Server returned HTTP ${res.status}`,
      };
    }

    if (!res.ok || !json.success) {
      const message = json.message || json.error || raw.trim() || `Server returned HTTP ${res.status}`;
      EHeart.toast(message, 'error');
      const error = new Error(message);
      error.status = res.status;
      error.response = json;
      error.raw = raw;
      throw error;
    }
    return json;
  },

  toast(message, type = 'info') {
    let wrap = document.querySelector('.eh-toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'eh-toast-wrap';
      document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    const icon = type === 'error' ? 'circle-x' : type === 'success' ? 'circle-check' : 'info';
    el.className = `eh-toast eh-toast-${type}`;
    el.innerHTML = `
      <i data-lucide="${icon}" class="eh-toast-icon" aria-hidden="true"></i>
      <span>${String(message).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[character]))}</span>
    `;
    wrap.appendChild(el);
    if (window.lucide) lucide.createIcons();
    setTimeout(() => el.remove(), 3500);
  },

  openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
  },

  closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  },

  formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  },
};

window.EHeart = EHeart;
