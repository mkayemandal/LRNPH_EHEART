document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const toggle = document.getElementById("sidebarToggle");
    const brand = document.getElementById("sidebarBrand");
    const mobileToggle = document.getElementById("mobileSidebarToggle");

    if (!sidebar) return;

    function refreshIcons() {
        if (window.lucide) lucide.createIcons();
    }

    /* =========================================================
       BACKDROP (mobile only) — click outside to close
    ========================================================= */
    let backdrop = document.querySelector(".eh-sidebar-backdrop");
    if (!backdrop) {
        backdrop = document.createElement("div");
        backdrop.className = "eh-sidebar-backdrop";
        document.body.appendChild(backdrop);
    }

    function openMobileSidebar() {
        sidebar.classList.add("open");
        backdrop.classList.add("visible");
        updateMobileToggleIcon(true);
    }

    function closeMobileSidebar() {
        sidebar.classList.remove("open");
        backdrop.classList.remove("visible");
        updateMobileToggleIcon(false);
    }

    function updateMobileToggleIcon(isOpen) {
        if (!mobileToggle) return;
        const icon = mobileToggle.querySelector("[data-lucide]");
        if (!icon) return;
        icon.setAttribute("data-lucide", isOpen ? "x" : "menu");
        mobileToggle.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
        mobileToggle.setAttribute("title", isOpen ? "Close menu" : "Open menu");
        refreshIcons();
    }

    /* =========================================================
       DESKTOP COLLAPSE / EXPAND
    ========================================================= */
    function updateIcon(collapsed) {
        if (!toggle) return;
        const icon = toggle.querySelector("[data-lucide]");
        if (!icon) return;

        icon.setAttribute("data-lucide", collapsed ? "panel-left-open" : "panel-left-close");
        toggle.setAttribute("title", collapsed ? "Open sidebar" : "Collapse sidebar");
        toggle.setAttribute("aria-label", collapsed ? "Open sidebar" : "Collapse sidebar");

        refreshIcons();
    }

    function setSidebarState(collapsed, save = true) {
        sidebar.classList.toggle("collapsed", collapsed);
        document.body.classList.toggle("sidebar-collapsed", collapsed);
        if (save) localStorage.setItem("sidebarCollapsed", collapsed ? "true" : "false");
        updateIcon(collapsed);
    }

    function initializeSidebar() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove("collapsed", "open");
            document.body.classList.remove("sidebar-collapsed");
            backdrop.classList.remove("visible");
            updateIcon(false);
            updateMobileToggleIcon(false);
            return;
        }
        const savedState = localStorage.getItem("sidebarCollapsed") === "true";
        setSidebarState(savedState, false);
    }

    /* DESKTOP TOGGLE BUTTON — collapse only (open moved to logo click) */
    toggle?.addEventListener("click", (event) => {
        event.stopPropagation();

        if (window.innerWidth <= 768) {
            // fallback safety, shouldn't normally trigger since button hidden on mobile
            sidebar.classList.contains("open") ? closeMobileSidebar() : openMobileSidebar();
            return;
        }

        const collapsed = sidebar.classList.contains("collapsed");
        if (!collapsed) setSidebarState(true, true);
    });

    /* MOBILE HAMBURGER BUTTON — open/close sidebar drawer */
    mobileToggle?.addEventListener("click", (event) => {
        event.stopPropagation();
        sidebar.classList.contains("open") ? closeMobileSidebar() : openMobileSidebar();
    });

    /* BACKDROP CLICK — close drawer */
    backdrop.addEventListener("click", closeMobileSidebar);

    /* CLOSE ON ESCAPE */
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && window.innerWidth <= 768) {
            closeMobileSidebar();
        }
    });

    /* CLOSE DRAWER AFTER TAPPING A NAV LINK (mobile) */
    sidebar.querySelectorAll(".eh-nav-item").forEach((link) => {
        link.addEventListener("click", () => {
            if (window.innerWidth <= 768) closeMobileSidebar();
        });
    });

    /* LOGO — desktop: open when collapsed. */
    if (brand) {
        brand.addEventListener("click", (event) => {
            if (event.target.closest("#sidebarToggle")) return;

            if (window.innerWidth > 768 && sidebar.classList.contains("collapsed")) {
                setSidebarState(false, true);
            }
        });
    }

    /* =========================================================
       RESIZE HANDLING
    ========================================================= */
    let resizeTimer;
    window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove("collapsed");
                document.body.classList.remove("sidebar-collapsed");
                updateIcon(false);
                return;
            }
            sidebar.classList.remove("open");
            backdrop.classList.remove("visible");
            updateMobileToggleIcon(false);
            const savedState = localStorage.getItem("sidebarCollapsed") === "true";
            setSidebarState(savedState, false);
        }, 100);
    });

    initializeSidebar();
    refreshIcons();
});