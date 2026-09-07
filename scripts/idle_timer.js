(function () {
  const IDLE_LIMIT_MS = 15 * 60 * 1000; // 15 min. Match auth.php IDLE_LIMIT.
  const WARN_BEFORE_MS = 60 * 1000;     // warn 1 min before kick
  const HEARTBEAT_MS = 4 * 60 * 1000;   // ping server every 4 min while active

  let idleTimer = null;
  let warnTimer = null;
  let lastHeartbeat = 0;

  function goLogout() {
    window.location.href = '/eheart/auth/logout.php?reason=idle_timeout';
  }

  function warnUser() {
    // simple warn, swap for modal if want fancy
    console.warn('Session about to expire from inactivity.');
  }

  function pingServer() {
    fetch('/eheart/auth/keep_alive.php', { credentials: 'same-origin' })
      .then((res) => {
        if (!res.ok) goLogout(); // server say session dead already
      })
      .catch(() => {}); // network fail, no kill, just skip
  }

  function resetTimers() {
    clearTimeout(idleTimer);
    clearTimeout(warnTimer);

    warnTimer = setTimeout(warnUser, IDLE_LIMIT_MS - WARN_BEFORE_MS);
    idleTimer = setTimeout(goLogout, IDLE_LIMIT_MS);

    const now = Date.now();
    if (now - lastHeartbeat > HEARTBEAT_MS) {
      lastHeartbeat = now;
      pingServer();
    }
  }

  ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach((evt) => {
    window.addEventListener(evt, resetTimers, { passive: true });
  });

  resetTimers();
})();