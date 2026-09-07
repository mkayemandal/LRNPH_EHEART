document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadCharts();
    loadCoreValues();
    loadRecentActivity();
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    observeSidebar();
    bindRefreshButton();
    bindStatCardClicks();
    bindStatModalClose();
});

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

let selectedCoreValue = null;

const coreValueNames = {
    1: 'Excellence',
    2: 'Efficiency',
    3: 'Teamwork',
    4: 'Professionalism',
    5: 'Passion'
};

const coreValueIcons = {
    1: 'star',
    2: 'gauge',
    3: 'users',
    4: 'shield',
    5: 'flame'
};

function getCoreValueIcon(id) {
    return coreValueIcons[String(id).trim()] || 'heart-handshake';
}

function mapCoreValue(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return raw;
    }
    if (Array.isArray(raw)) {
        return raw.map(v => coreValueNames[String(v).trim()] || v).join(', ');
    }
    const str = String(raw).replace(/[\[\]]/g, '').trim();
    if (!str) {
        return raw;
    }
    if (str.includes(',')) {
        return str.split(',').map(v => {
            const key = v.trim();
            return coreValueNames[key] || key;
        }).join(', ');
    }
    return coreValueNames[str] || raw;
}

function bindRefreshButton() {
    const btn = document.getElementById('btn-refresh-dashboard');
    if (!btn) {
        return;
    }
    btn.addEventListener('click', () => refreshDashboard(btn));
}

function refreshDashboard(btn) {
    const icon = btn.querySelector('svg') || btn.querySelector('i');
    btn.disabled = true;
    if (icon) {
        icon.classList.add('eh-spin');
    }
    // Refresh the entire dashboard page
    window.location.reload();
}

// add a cache-busting param so GET requests are never served stale from cache
function withCacheBust(url) {
    const sep = url.includes('?') ? '&' : '?';
    return url + sep + '_t=' + Date.now();
}

async function loadCoreValues() {
    const container = document.getElementById('core-value-cards');
    if (!container) {
        return;
    }
    try {
        const res = await EHeart.api(withCacheBust('/eheart/api/reports/core_values.php'));
        const data = res.data || [];
        container.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
            container.innerHTML = '<div class="eh-report-empty">No Core Values found.</div>';
            return;
        }
        data.forEach(item => {
            const recognition = Number(item.recognition_count || 0);
            const card = document.createElement('div');
            card.className = 'eh-core-value-card';
            card.dataset.coreValueId = item.core_value_id;
            card.style.cursor = 'pointer';
            if (selectedCoreValue !== null && Number(selectedCoreValue) === Number(item.core_value_id)) {
                card.classList.add('active');
            }
            card.innerHTML = `
                <div class="eh-core-value-top">
                    <div>
                        <div class="eh-core-value-name">${escapeHtml(item.core_value_name)}</div>
                        <div class="eh-core-value-count">${recognition.toLocaleString()}</div>
                        <div class="eh-core-value-description">Recognitions</div>
                    </div>
                    <div class="eh-core-value-icon"><i data-lucide="${getCoreValueIcon(item.core_value_id)}"></i></div>
                </div>
            `;
            card.addEventListener('click', () => onCoreValueClick(item.core_value_id, card));
            container.appendChild(card);
        });
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    } catch (error) {
        console.error('EHEART CORE VALUES ERROR:', error);
        container.innerHTML = '<div class="eh-report-empty">Unable to load Core Values.</div>';
    }
}

function onCoreValueClick(id, cardEl) {
    const container = document.getElementById('core-value-cards');
    if (Number(selectedCoreValue) === Number(id)) {
        selectedCoreValue = null;
    } else {
        selectedCoreValue = id;
    }
    if (container) {
        container.querySelectorAll('.eh-core-value-card').forEach(c => c.classList.remove('active'));
    }
    if (selectedCoreValue !== null) {
        cardEl.classList.add('active');
    }
    loadStats();
    loadCharts();
    loadRecentActivity();
}

function rowHasCoreValue(raw, id) {
    if (raw === null || raw === undefined || raw === '') {
        return false;
    }
    const target = String(id).trim();
    let values;
    if (Array.isArray(raw)) {
        values = raw.map(v => String(v).trim());
    } else {
        const str = String(raw).trim();
        try {
            const parsed = JSON.parse(str);
            values = Array.isArray(parsed) ? parsed.map(v => String(v).trim()) : [String(parsed).trim()];
        } catch (e) {
            values = str.replace(/[\[\]"]/g, '').split(',').map(v => v.trim());
        }
    }
    return values.includes(target);
}

async function loadRecentActivity() {
    const tbody = document.getElementById('activity-table-body');
    if (!tbody) {
        return;
    }
    const activityTable = tbody.closest('.eh-activity-table');
    const isManager = activityTable?.dataset.role === 'MANAGER';
    try {
        const res = await EHeart.api(withCacheBust('/eheart/api/heart_card/list.php'));
        let rows = res.data || [];
        if (selectedCoreValue !== null) {
            rows = rows.filter(row => rowHasCoreValue(pick(row, ['core_values', 'core_value']), selectedCoreValue));
        }
        rows = rows.slice().sort((a, b) => {
            return (pick(b, ['heart_card_id', 'id']) || 0) - (pick(a, ['heart_card_id', 'id']) || 0);
        }).slice(0, 5);
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="${isManager ? 4 : 5}" class="eh-activity-empty">No recent activity.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(row => {
            const cardNo = pick(row, ['control_number', 'card_no']) || ('HC-' + String(pick(row, ['heart_card_id', 'id']) || '').padStart(6, '0'));
            const employee = pick(row, ['receiver_full_name', 'employee_name', 'employee']);
            const requester = pick(row, ['requester_full_name', 'requester_name']);
            const coreValueRaw = pick(row, ['core_values', 'core_value']);
            const coreValue = mapCoreValue(coreValueRaw);
            const status = String(pick(row, ['status']) || '').toUpperCase();
            const statusClass = ehStatusBadgeClass(status);
            const statusLabel = status.replace(/_/g, ' ');
            return '<tr>'
                + '<td>' + escapeHtml(cardNo) + '</td>'
                + '<td>' + escapeHtml(employee) + '</td>'
                + (isManager ? '' : '<td>' + escapeHtml(requester) + '</td>')
                + '<td>' + escapeHtml(coreValue) + '</td>'
                + '<td><span class="eh-badge eh-badge-' + statusClass + '">' + escapeHtml(statusLabel) + '</span></td>'
                + '</tr>';
        }).join('');
    } catch (error) {
        console.error('EHEART RECENT ACTIVITY ERROR:', error);
        const isManager = document.querySelector('.eh-activity-table')?.dataset.role === 'MANAGER';
        tbody.innerHTML = `<tr><td colspan="${isManager ? 4 : 5}" class="eh-activity-empty">Unable to load activity.</td></tr>`;
    }
}

function pick(obj, keys) {
    for (const key of keys) {
        if (obj && obj[key] !== undefined && obj[key] !== null && obj[key] !== '') {
            return obj[key];
        }
    }
    return null;
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function buildUrl(base) {
    let url = base;
    if (selectedCoreValue !== null && selectedCoreValue !== undefined) {
        url += '?core_value_id=' + encodeURIComponent(selectedCoreValue);
    }
    return withCacheBust(url);
}

async function loadStats() {
    try {
        const res = await EHeart.api(buildUrl('/eheart/api/dashboard/summary.php'));
        const data = res.data || {};
        Object.keys(data).forEach((key) => {
            const element = document.getElementById('stat-' + key);
            if (!element) {
                return;
            }
            const value = data[key] ?? 0;
            if (typeof value === 'number' && Number.isInteger(value)) {
                element.textContent = value.toLocaleString();
            } else {
                element.textContent = value;
            }
        });
    } catch (error) {
        console.error('EHEART DASHBOARD STATS ERROR:', error);
    }
}

/* =========================================================
   STAT CARD -> MODAL TABLE
   ========================================================= */

function bindStatCardClicks() {
    document.querySelectorAll('.eh-dashboard-stat[data-stat]').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => openStatModal(card));
    });
}

function bindStatModalClose() {
    const overlay = document.getElementById('stat-detail-modal');
    const closeBtn = document.getElementById('stat-modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            overlay.style.display = 'none';
        });
    }
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.style.display = 'none';
            }
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && overlay && overlay.style.display !== 'none') {
            overlay.style.display = 'none';
        }
    });
}

const statVariantClasses = ['approved', 'pending', 'rejected', 'redeemed', 'expired', 'money'];

async function openStatModal(card) {
    const statKey = card.dataset.stat;
    let statuses = [];
    try {
        statuses = JSON.parse(card.dataset.statuses || '[]');
    } catch (e) {
        statuses = [];
    }

    const titleEl = document.getElementById('stat-modal-title');
    const subtitleEl = document.getElementById('stat-modal-subtitle');
    const iconEl = document.getElementById('stat-modal-icon');
    const bodyEl = document.getElementById('stat-modal-table-body');
    const overlay = document.getElementById('stat-detail-modal');
    const labelEl = card.querySelector('.eh-stat-label');
    const descEl = card.querySelector('.eh-stat-description');
    const cardIconEl = card.querySelector('.eh-stat-icon');
    const label = labelEl ? labelEl.textContent.trim() : statKey;
    const description = descEl ? descEl.textContent.trim() : '';

    if (!titleEl || !bodyEl || !overlay) {
        return;
    }

    titleEl.textContent = label;
    subtitleEl.textContent = 'Loading…';

    const tableEl = document.getElementById('stat-modal-table');
    const showAmount = statKey === 'total_gc_amount';
    if (tableEl) {
        tableEl.classList.toggle('show-amount', showAmount);
    }

    if (iconEl) {
        iconEl.className = 'eh-stat-modal-icon';
        const variant = statVariantClasses.find(v => card.classList.contains(v));
        if (variant) {
            iconEl.classList.add(variant);
        }
        iconEl.innerHTML = cardIconEl ? cardIconEl.innerHTML : '<i data-lucide="heart"></i>';
    }

    bodyEl.innerHTML = `<tr><td colspan="${showAmount ? 6 : 5}" class="eh-activity-empty">Loading…</td></tr>`;
    overlay.style.display = 'flex';
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    try {
        const res = await EHeart.api(withCacheBust('/eheart/api/heart_card/list.php'));
        let rows = res.data || [];

        if (statuses.length > 0) {
            rows = rows.filter(row => statuses.includes(String(pick(row, ['status']) || '').toUpperCase()));
        }

        if (selectedCoreValue !== null) {
            rows = rows.filter(row => rowHasCoreValue(pick(row, ['core_values', 'core_value']), selectedCoreValue));
        }

        rows = rows.slice().sort((a, b) => {
            return (pick(b, ['heart_card_id', 'id']) || 0) - (pick(a, ['heart_card_id', 'id']) || 0);
        });

        const recordWord = rows.length === 1 ? 'record' : 'records';
        subtitleEl.textContent = description
            ? `${rows.length.toLocaleString()} ${recordWord} · ${description}`
            : `${rows.length.toLocaleString()} ${recordWord}`;

        renderStatModalTable(rows, showAmount);
    } catch (error) {
        console.error('EHEART STAT MODAL ERROR:', error);
        subtitleEl.textContent = description || '';
        bodyEl.innerHTML = `<tr><td colspan="${showAmount ? 6 : 5}" class="eh-activity-empty">Unable to load data.</td></tr>`;
    }
}

function renderStatModalTable(rows, showAmount) {
    const bodyEl = document.getElementById('stat-modal-table-body');
    if (!bodyEl) {
        return;
    }
    if (!rows.length) {
        bodyEl.innerHTML = `<tr><td colspan="${showAmount ? 6 : 5}" class="eh-activity-empty">No records found.</td></tr>`;
        return;
    }
    bodyEl.innerHTML = rows.map(row => {
        const cardNo = pick(row, ['control_number', 'card_no']) || ('HC-' + String(pick(row, ['heart_card_id', 'id']) || '').padStart(6, '0'));
        const employee = pick(row, ['receiver_full_name', 'employee_name', 'employee']);
        const requester = pick(row, ['requester_full_name', 'requester_name']);
        const coreValue = mapCoreValue(pick(row, ['core_values', 'core_value']));
        const status = String(pick(row, ['status']) || '').toUpperCase();
        const statusClass = ehStatusBadgeClass(status);
        const statusLabel = status.replace(/_/g, ' ');
        let amountCell = '';
        if (showAmount) {
            const amountRaw = pick(row, ['amount', 'gc_amount', 'redemption_amount']);
            const amountText = (amountRaw !== null && amountRaw !== undefined && amountRaw !== '')
                ? '₱' + Math.round(Number(amountRaw)).toLocaleString()
                : '—';
            amountCell = '<td class="col-amount">' + escapeHtml(amountText) + '</td>';
        }
        return '<tr>'
            + '<td>' + escapeHtml(cardNo) + '</td>'
            + '<td>' + escapeHtml(employee) + '</td>'
            + '<td>' + escapeHtml(requester) + '</td>'
            + '<td>' + escapeHtml(coreValue) + '</td>'
            + '<td><span class="eh-badge eh-badge-' + statusClass + '">' + escapeHtml(statusLabel) + '</span></td>'
            + amountCell
            + '</tr>';
    }).join('');
}

const chartInstances = {};

const chartPalette = {
    pink: '#e91e63',
    blue: '#3f51b5',
    teal: '#009688',
    orange: '#ff9800',
    purple: '#9c27b0',
    green: '#4caf50',
    gray: '#607d8b',
    grid: '#edf0f4',
    text: '#737985'
};

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: {
        duration: 500
    },
    plugins: {
        legend: {
            labels: {
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 18,
                color: chartPalette.text,
                font: {
                    size: 11,
                    weight: '600'
                }
            }
        },
        tooltip: {
            backgroundColor: '#20242c',
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            padding: 12,
            cornerRadius: 8,
            displayColors: true,
            titleFont: {
                size: 12,
                weight: '700'
            },
            bodyFont: {
                size: 11
            }
        }
    },
    scales: {
        x: {
            border: {
                display: false
            },
            grid: {
                display: false
            },
            ticks: {
                color: chartPalette.text,
                font: {
                    size: 10
                }
            }
        },
        y: {
            border: {
                display: false
            },
            grid: {
                color: chartPalette.grid
            },
            ticks: {
                color: chartPalette.text,
                font: {
                    size: 10
                },
                precision: 0
            }
        }
    }
};

function renderChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) {
        return;
    }
    if (chartInstances[id]) {
        chartInstances[id].destroy();
    }
    chartInstances[id] = new Chart(canvas, config);
}

async function loadCharts() {
    try {
        const res = await EHeart.api(buildUrl('/eheart/api/dashboard/charts.php'));
        const charts = res.data || {};

        if (charts.monthly) {
            renderChart('chart-monthly', {
                type: 'line',
                data: {
                    labels: charts.monthly.map(row => row.ym),
                    datasets: [
                        {
                            label: 'Cards',
                            data: charts.monthly.map(row => Number(row.cnt)),
                            borderColor: chartPalette.pink,
                            backgroundColor: 'rgba(233, 30, 99, .08)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: true,
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    ...commonOptions
                }
            });
        }

        if (charts.status) {
            renderChart('chart-status', {
                type: 'doughnut',
                data: {
                    labels: charts.status.map(row => row.status),
                    datasets: [
                        {
                            data: charts.status.map(row => Number(row.cnt)),
                            backgroundColor: [
                                chartPalette.pink,
                                chartPalette.blue,
                                chartPalette.orange,
                                chartPalette.green,
                                chartPalette.gray,
                                chartPalette.purple
                            ],
                            borderWidth: 0,
                            hoverOffset: 6
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    cutout: '68%',
                    scales: {
                        x: {
                            display: false
                        },
                        y: {
                            display: false
                        }
                    }
                }
            });
        }

        if (charts.core_values) {
            renderChart('chart-core_values', {
                type: 'bar',
                data: {
                    labels: charts.core_values.map(row => mapCoreValue(row.core_value)),
                    datasets: [
                        {
                            label: 'Recognitions',
                            data: charts.core_values.map(row => Number(row.cnt)),
                            backgroundColor: chartPalette.blue,
                            borderRadius: 6,
                            borderSkipped: false,
                            barThickness: 20
                        }
                    ]
                },
                options: {
                    ...commonOptions,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            ...commonOptions.scales.x,
                            grid: {
                                color: chartPalette.grid
                            }
                        },
                        y: {
                            ...commonOptions.scales.y,
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        if (charts.redemption_trend) {
            renderChart('chart-redemption_trend', {
                type: 'line',
                data: {
                    labels: charts.redemption_trend.map(row => row.ym),
                    datasets: [
                        {
                            label: 'Redeemed',
                            data: charts.redemption_trend.map(row => Number(row.cnt)),
                            borderColor: chartPalette.teal,
                            backgroundColor: 'rgba(0, 150, 136, .08)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            fill: true,
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    ...commonOptions
                }
            });
        }

        if (charts.by_department) {
            renderChart('chart-by_department', {
                type: 'bar',
                data: {
                    labels: charts.by_department.map(row => row.dept),
                    datasets: [
                        {
                            label: 'Total',
                            data: charts.by_department.map(row => Number(row.cnt)),
                            backgroundColor: chartPalette.orange,
                            borderRadius: 6,
                            borderSkipped: false
                        },
                        {
                            label: 'Redeemed',
                            data: charts.by_department.map(row => Number(row.redeemed)),
                            backgroundColor: chartPalette.purple,
                            borderRadius: 6,
                            borderSkipped: false
                        }
                    ]
                },
                options: {
                    ...commonOptions
                }
            });
        }

        setTimeout(() => {
            resizeAllCharts();
        }, 250);
    } catch (error) {
        console.error('EHEART DASHBOARD CHART ERROR:', error);
    }
}

function resizeAllCharts() {
    Object.values(chartInstances).forEach(chart => {
        if (chart) {
            chart.resize();
        }
    });
}

// end chart/stat logic
function observeSidebar() {
    const sidebar = document.querySelector('.eh-sidebar');
    if (!sidebar) {
        return;
    }
    const observer = new MutationObserver(() => {
        setTimeout(() => {
            resizeAllCharts();
        }, 280);
    });
    observer.observe(sidebar, {
        attributes: true,
        attributeFilter: ['class']
    });
    window.addEventListener('resize', resizeAllCharts);
}