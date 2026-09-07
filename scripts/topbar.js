document.addEventListener('DOMContentLoaded', () => {
  const profileToggle = document.getElementById('topbarProfileToggle');
  const profileMenu = document.getElementById('topbarProfileMenu');
  const notifBtn = document.getElementById('topbarNotificationBtn');
  const notifBox = document.getElementById('topbarNotifBox');
  const notifList = document.getElementById('topbarNotifBoxList');
  const badge = document.getElementById('topbarNotifBadge');
  const boxBadge = document.getElementById('topbarNotifBoxBadge');
  let notifLoaded = false;

  if (profileToggle && profileMenu) {
    profileToggle.addEventListener('click', e => {
      e.stopPropagation();
      profileMenu.classList.toggle('open');
      notifBox?.classList.remove('open');
    });
  }

  function setBadge(count) {
    [badge, boxBadge].forEach(el => {
      if (!el) return;
      if (count > 0) {
        el.textContent = count > 99 ? '99+' : String(count);
        el.style.display = 'inline-flex';
      } else {
        el.style.display = 'none';
      }
    });
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function timeAgo(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  }

  function isRead(n) {
    return n.is_read === true || n.is_read === 1 || n.is_read === '1' || n.read === true || n.read === 1 || n.read === '1' || (n.read_at !== null && n.read_at !== undefined && n.read_at !== '');
  }

  function isToday(value) {
    if (!value) return false;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return false;
    const now = new Date();
    return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth() && date.getDate() === now.getDate();
  }

  function renderItem(n) {
    const id = n.notification_id ?? n.id ?? n.notif_id ?? '';
    const title = escapeHtml(n.title ?? n.subject ?? 'Notification');
    const message = escapeHtml(n.message ?? n.body ?? '');
    const when = timeAgo(n.created_at ?? n.date_created ?? n.createdAt ?? n.date);
    const unread = !isRead(n);
    return `<a href="/eheart/pages/manager/notifications.php?notification_id=${encodeURIComponent(id)}" class="eh-topbar-notif-box-item${unread ? ' is-unread' : ''}">
<div class="eh-topbar-notif-box-icon"><i data-lucide="bell"></i></div>
<div class="eh-topbar-notif-box-text">
<p class="eh-topbar-notif-box-title">${title}</p>
<p class="eh-topbar-notif-box-msg">${message || when}</p>
</div>
${unread ? '<span class="eh-topbar-notif-box-dot"></span>' : ''}
</a>`;
  }

  function renderPreview(items) {
    if (!notifList) return;
    const sorted = [...(items || [])].sort((a, b) => {
      const dateA = new Date(a.created_at ?? a.date_created ?? a.createdAt ?? a.date ?? 0);
      const dateB = new Date(b.created_at ?? b.date_created ?? b.createdAt ?? b.date ?? 0);
      return dateB - dateA;
    });
    const top = sorted.slice(0, 3);
    if (!top.length) {
      notifList.innerHTML = '<div class="eh-topbar-notif-box-empty">You are all caught up.</div>';
      return;
    }
    const todayItems = top.filter(n => isToday(n.created_at ?? n.date_created ?? n.createdAt ?? n.date));
    const recentItems = top.filter(n => !isToday(n.created_at ?? n.date_created ?? n.createdAt ?? n.date));
    let html = '';
    if (todayItems.length) {
      html += '<div class="eh-topbar-notif-box-group-label">Today</div>';
      html += todayItems.map(renderItem).join('');
    }
    if (recentItems.length) {
      html += '<div class="eh-topbar-notif-box-group-label">Recent</div>';
      html += recentItems.map(renderItem).join('');
    }
    notifList.innerHTML = html;
    if (window.lucide) lucide.createIcons();
  }

  function loadNotifications() {
    fetch('/eheart/api/notifications/list.php?unread=1')
      .then(res => res.json())
      .then(data => {
        const list = data.data || data.result || [];
        setBadge(Array.isArray(list) ? list.length : 0);
      })
      .catch(e => console.error('Topbar notification count failed', e));
  }

  function loadPreview() {
    if (notifLoaded) return;
    if (notifList) notifList.innerHTML = '<div class="eh-topbar-notif-box-empty">Loading...</div>';
    fetch('/eheart/api/notifications/list.php')
      .then(res => res.json())
      .then(data => {
        const list = data.data || data.result || [];
        renderPreview(list);
        notifLoaded = true;
      })
      .catch(e => {
        console.error('Topbar notification preview failed', e);
        if (notifList) notifList.innerHTML = '<div class="eh-topbar-notif-box-empty">Unable to load notifications.</div>';
      });
  }

  if (notifBtn && notifBox) {
    notifBtn.addEventListener('click', e => {
      e.stopPropagation();
      const willOpen = !notifBox.classList.contains('open');
      notifBox.classList.toggle('open');
      profileMenu?.classList.remove('open');
      if (willOpen) loadPreview();
    });
  }

  function initUserGuide() {
    const guideBtn = document.getElementById('topbarGuideBtn');
    const modal = document.getElementById('userGuideModal');
    const closeBtn = document.getElementById('userGuideClose');
    const doneBtn = document.getElementById('userGuideDone');
    const nextBtn = document.getElementById('userGuideNext');
    const prevBtn = document.getElementById('userGuidePrev');
    const progressText = document.getElementById('userGuideProgressText');
    const progressBar = document.getElementById('userGuideProgressBar');
    const navItems = [...document.querySelectorAll('.eh-guide-nav-item')];
    const steps = [...document.querySelectorAll('.eh-guide-step')];

    if (!guideBtn || !modal || !steps.length) return;

    let currentStep = 0;

    function updateGuide(step) {
      currentStep = Math.max(0, Math.min(step, steps.length - 1));
      steps.forEach((item, index) => item.classList.toggle('active', index === currentStep));
      navItems.forEach((item, index) => item.classList.toggle('active', index === currentStep));

      if (progressText) progressText.textContent = `Step ${currentStep + 1} of ${steps.length}`;
      if (progressBar) progressBar.style.width = `${((currentStep + 1) / steps.length) * 100}%`;

      if (prevBtn) prevBtn.style.display = currentStep === 0 ? 'none' : 'inline-flex';
      if (nextBtn) nextBtn.style.display = currentStep === steps.length - 1 ? 'none' : 'inline-flex';
      if (doneBtn) doneBtn.style.display = currentStep === steps.length - 1 ? 'inline-flex' : 'none';

      const content = document.querySelector('.eh-guide-content');
      if (content) content.scrollTop = 0;
      if (window.lucide) lucide.createIcons();
    }

    function openGuide() {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      updateGuide(0);
      if (window.lucide) lucide.createIcons();
    }

    function closeGuide() {
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }

    guideBtn.addEventListener('click', openGuide);
    closeBtn?.addEventListener('click', closeGuide);
    doneBtn?.addEventListener('click', closeGuide);
    nextBtn?.addEventListener('click', () => updateGuide(currentStep + 1));
    prevBtn?.addEventListener('click', () => updateGuide(currentStep - 1));

    navItems.forEach((item, index) => {
      item.addEventListener('click', () => updateGuide(index));
    });

    modal.addEventListener('click', e => {
      if (e.target === modal) closeGuide();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && modal.style.display !== 'none') closeGuide();
    });

    updateGuide(0);
  }

  document.addEventListener('click', e => {
    if (notifBox && !notifBox.contains(e.target) && notifBtn && !notifBtn.contains(e.target)) notifBox.classList.remove('open');
    if (profileMenu && !profileMenu.contains(e.target) && profileToggle && !profileToggle.contains(e.target)) profileMenu.classList.remove('open');
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      profileMenu?.classList.remove('open');
      notifBox?.classList.remove('open');
    }
  });

  initUserGuide();
  loadNotifications();
  if (window.lucide) lucide.createIcons();
});