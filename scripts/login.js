const statusBox = document.getElementById('loginStatus');
const errBox = document.getElementById('loginError');
const urlParams = new URLSearchParams(window.location.search);

if (urlParams.get('reason') === 'idle_timeout') {
    statusBox.textContent = 'Your session timed out after 15 minutes of inactivity. Please sign in again.';
    statusBox.style.display = 'block';
    window.history.replaceState({}, '', '/eheart/auth/login.php');
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(e.target);
    const data = Object.fromEntries(fd);
    errBox.style.display = 'none';
    statusBox.style.display = 'none';

    try {
        const res = await fetch('/eheart/api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data),
        });

        const json = await res.json();

        if (!json.success) {
            if (res.status === 401) {
                errBox.textContent = 'Username and password do not match.';
            } else {
                errBox.textContent = json.message;
            }
            errBox.style.display = 'block';
            return;
        }

        window.location = '/eheart/pages/manager/dashboard.php';
    } catch (err) {
        errBox.textContent = 'Unable to reach the server.';
        errBox.style.display = 'block';
    }
});

const pwInput = document.getElementById('password');
const toggleBtn = document.getElementById('togglePassword');

if (pwInput && toggleBtn) {
    toggleBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
}