/* Geoventure Analytics — graphiques (Chart.js 2.9 déjà chargé par admin.js), carte des
 * trajectoires, mise à jour automatique. Aucun innerHTML avec des données : textContent / DOM. */
(function () {
    'use strict';
    var root = document.getElementById('an-root');
    if (!root) { return; }
    if (typeof Chart === 'undefined') { console.warn('Chart.js indisponible'); }
    var L = window.ANL || {};
    var URL_DATA = root.getAttribute('data-url');
    var LOCALE = root.getAttribute('data-locale') || 'fr';
    var charts = [];
    var nf = new Intl.NumberFormat(LOCALE, { maximumFractionDigits: 2 });
    var TIER = { 1: '#60a5fa', 2: '#4ade80', 3: '#facc15', 4: '#fb923c', 5: '#f87171' };

    function period() { return root.getAttribute('data-period') || '7d'; }
    function text() { return getComputedStyle(document.body).color || '#888'; }
    function grid() { return 'rgba(127,127,127,.18)'; }
    function alpha(hex, a) { return /^#[0-9a-f]{6}$/i.test(hex) ? hex + a : hex; }

    function fmtTs(ts, bucket) {
        var d = new Date(ts * 1000);
        var o = bucket >= 86400 ? { day: '2-digit', month: 'short' }
            : (period() === '24h' ? { hour: '2-digit', minute: '2-digit' } : { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        return new Intl.DateTimeFormat(LOCALE, o).format(d);
    }

    function setEmpty(canvas, empty) {
        var box = canvas.parentNode && canvas.parentNode.querySelector('.an-empty');
        if (box) { box.classList.toggle('d-none', !empty); }
        canvas.style.visibility = empty ? 'hidden' : 'visible';
    }

    function build(canvas, spec, labels, datasets, bucket) {
        var hbar = spec.type === 'hbar';
        var type = hbar ? 'bar' : spec.type;
        var stacked = !!spec.stacked;
        var unit = spec.unit || '';
        var ds = datasets.map(function (d) {
            var c = d.color || '#4ade80';
            var o = { label: d.label, data: d.data };
            if (d.axis === 'right') { o.yAxisID = 'y1'; }
            if (type === 'line') {
                o.borderColor = c; o.backgroundColor = alpha(c, '22'); o.fill = !!d.fill; o.tension = 0.3;
                o.pointRadius = labels.length > 60 ? 0 : 2; o.borderWidth = 2; o.spanGaps = true;
                if (d.dashed) { o.borderDash = [5, 4]; }
            } else {
                o.backgroundColor = c; o.borderColor = c; o.borderRadius = 2;
            }
            return o;
        });
        var valueAxis = { stacked: stacked, beginAtZero: true, ticks: { color: text(), callback: function (v) { return nf.format(v) + unit; } }, grid: { color: grid() } };
        if (typeof spec.min === 'number') { valueAxis.min = spec.min; }
        if (typeof spec.max === 'number') { valueAxis.max = spec.max; }
        var catAxis = { stacked: stacked, ticks: { color: text(), maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false } };
        var scales = hbar ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis };
        if (spec.dual && !hbar) { scales.y1 = { position: 'right', beginAtZero: true, ticks: { color: text() }, grid: { display: false } }; }
        var chart = new Chart(canvas, {
            type: type,
            data: { labels: labels, datasets: ds },
            options: {
                indexAxis: hbar ? 'y' : 'x',
                responsive: true, maintainAspectRatio: false, animation: { duration: 250 },
                interaction: { mode: hbar ? 'nearest' : 'index', intersect: false },
                plugins: {
                    legend: { display: ds.length > 1, labels: { color: text(), boxWidth: 12 } },
                    tooltip: { callbacks: { label: function (c) { var v = hbar ? c.parsed.x : c.parsed.y; return (c.dataset.label || '') + ' : ' + nf.format(v) + unit; } } }
                },
                scales: scales
            }
        });
        charts.push(chart);
        var any = datasets.some(function (d) { return d.data.some(function (v) { return v !== null && v !== undefined; }); });
        setEmpty(canvas, !labels.length || !any);
    }

    function initCanvas(canvas) {
        var spec;
        try { spec = JSON.parse(canvas.getAttribute('data-an-chart')); } catch (e) { return; }
        if (spec.inline) {
            var labels = spec.inline.labels;
            if (spec.ts) { labels = labels.map(function (t) { return fmtTs(t, bucketOf(labels)); }); }
            build(canvas, spec, labels, spec.inline.datasets, 0);
            return;
        }
        var series = (spec.series || []);
        var q = series.map(function (s) { return 'series[]=' + encodeURIComponent([s.scope, s.metric, s.ref, s.agg || 'avg'].join('|')); }).join('&');
        fetch(URL_DATA + '?kind=series&period=' + encodeURIComponent(period()) + '&' + q, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (j) {
                var labels = (j.labels || []).map(function (t) { return fmtTs(t, j.bucket); });
                var ds = series.map(function (s, i) {
                    return { label: s.label, color: s.color, fill: s.fill, dashed: s.dashed, axis: s.axis, data: (j.series && j.series[i]) || [] };
                });
                build(canvas, spec, labels, ds, j.bucket);
            })
            .catch(function () { setEmpty(canvas, true); });
    }

    function bucketOf(labels) { return labels.length > 1 ? labels[1] - labels[0] : 0; }

    function initCharts(scope) {
        (scope || document).querySelectorAll('canvas[data-an-chart]').forEach(initCanvas);
    }

    function destroyCharts() {
        charts.forEach(function (c) { try { c.destroy(); } catch (e) { /* ignore */ } });
        charts = [];
    }

    // ── Carte des trajectoires ─────────────────────────────────────────
    var NS = 'http://www.w3.org/2000/svg';
    var missiles = [];

    function el(name, attrs, txt) {
        var e = document.createElementNS(NS, name);
        Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
        if (txt !== undefined) { e.textContent = txt; }
        return e;
    }

    function drawMap() {
        var svg = document.getElementById('an-map');
        if (!svg) { return; }
        while (svg.firstChild) { svg.removeChild(svg.firstChild); }
        var sel = document.getElementById('an-map-world');
        var world = sel ? sel.value : '';
        var rows = missiles.filter(function (m) {
            if (!m.from || !m.target) { return false; }
            return !world || m.from.world === world || m.target.world === world;
        });
        var empty = document.getElementById('an-map-empty');
        if (empty) { empty.classList.toggle('d-none', rows.length > 0); }
        var legend = document.getElementById('an-map-legend');
        if (legend) {
            legend.textContent = '';
            [1, 2, 3, 4, 5].forEach(function (t) {
                var s = document.createElement('span');
                s.className = 'me-3';
                var dot = document.createElement('span');
                dot.style.cssText = 'display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:4px;background:' + TIER[t];
                s.appendChild(dot);
                s.appendChild(document.createTextNode((L.tier || 'T') + ' ' + t));
                legend.appendChild(s);
            });
        }
        if (!rows.length) { return; }
        var xs = [], zs = [];
        rows.forEach(function (m) { xs.push(m.from.x, m.target.x); zs.push(m.from.z, m.target.z); });
        var minX = Math.min.apply(null, xs), maxX = Math.max.apply(null, xs), minZ = Math.min.apply(null, zs), maxZ = Math.max.apply(null, zs);
        var dx = Math.max(maxX - minX, 100), dz = Math.max(maxZ - minZ, 100);
        var pad = 40, W = 800, H = 420;
        var sc = Math.min((W - 2 * pad) / dx, (H - 2 * pad) / dz);
        var ox = (W - dx * sc) / 2 - minX * sc + ((dx - (maxX - minX)) * sc) / 2;
        var oz = (H - dz * sc) / 2 - minZ * sc + ((dz - (maxZ - minZ)) * sc) / 2;
        function px(x) { return x * sc + ox; }
        function pz(z) { return z * sc + oz; }
        // grille
        var step = Math.pow(10, Math.floor(Math.log10(Math.max(dx, dz) / 4)));
        step = step * (Math.max(dx, dz) / 4 / step > 5 ? 5 : (Math.max(dx, dz) / 4 / step > 2 ? 2 : 1));
        for (var gx = Math.ceil(minX / step) * step; gx <= maxX; gx += step) {
            svg.appendChild(el('line', { x1: px(gx), y1: 0, x2: px(gx), y2: H, stroke: 'rgba(127,127,127,.2)' }));
            svg.appendChild(el('text', { x: px(gx) + 2, y: H - 4, fill: 'currentColor', 'font-size': 10, opacity: .6 }, String(Math.round(gx))));
        }
        for (var gz = Math.ceil(minZ / step) * step; gz <= maxZ; gz += step) {
            svg.appendChild(el('line', { x1: 0, y1: pz(gz), x2: W, y2: pz(gz), stroke: 'rgba(127,127,127,.2)' }));
            svg.appendChild(el('text', { x: 2, y: pz(gz) - 2, fill: 'currentColor', 'font-size': 10, opacity: .6 }, String(Math.round(gz))));
        }
        rows.forEach(function (m) {
            var c = TIER[m.tier] || '#a78bfa';
            var g = el('g', {});
            var ln = el('line', { class: 'tr', 'data-id': m.id, x1: px(m.from.x), y1: pz(m.from.z), x2: px(m.target.x), y2: pz(m.target.z), stroke: c });
            ln.appendChild(el('title', {}, (m.ref || '?') + ' → ' + (m.target_faction || '?') + ' · T' + m.tier + ' · ' + (m.warhead || '')));
            g.appendChild(ln);
            g.appendChild(el('circle', { cx: px(m.from.x), cy: pz(m.from.z), r: 3.5, fill: c }));
            var tx = px(m.target.x), tz = pz(m.target.z);
            g.appendChild(el('path', { d: 'M' + (tx - 4) + ' ' + (tz - 4) + 'L' + (tx + 4) + ' ' + (tz + 4) + 'M' + (tx + 4) + ' ' + (tz - 4) + 'L' + (tx - 4) + ' ' + (tz + 4), stroke: c, 'stroke-width': 2, fill: 'none' }));
            svg.appendChild(g);
        });
    }

    function loadMap() {
        if (!document.getElementById('an-map')) { return; }
        fetch(URL_DATA + '?kind=missiles&period=' + encodeURIComponent(period()), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (j) { missiles = j.missiles || []; drawMap(); })
            .catch(function () { missiles = []; drawMap(); });
    }

    function fmtPoint(p) { return p ? (p.world ? p.world + ' ' : '') + Math.round(p.x) + ' / ' + Math.round(p.z) : '—'; }

    function showMissile(id) {
        var box = document.getElementById('an-missile-detail');
        if (!box) { return; }
        fetch(URL_DATA + '?kind=missile&id=' + encodeURIComponent(id) + '&period=' + encodeURIComponent(period()), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (j) {
                var m = j.missile;
                document.querySelectorAll('#an-map .tr').forEach(function (n) { n.classList.toggle('sel', n.getAttribute('data-id') === String(id)); });
                document.getElementById('an-md-title').textContent = (L.launch || 'Tir') + ' #' + m.id + ' — T' + m.tier + ' ' + (m.warhead || '');
                var dl = document.getElementById('an-md-facts');
                dl.textContent = '';
                var facts = [['ref', m.ref], ['tier', 'T' + m.tier], ['carrier', m.carrier], ['warhead', m.warhead], ['flight', m.flight], ['airburst', m.airburst ? '✓' : '—'],
                    ['from', fmtPoint(m.from)], ['target', fmtPoint(m.target)], ['target_faction', m.target_faction], ['distance', m.distance === null ? '—' : nf.format(m.distance)],
                    ['eta_s', m.eta_s === null ? '—' : nf.format(m.eta_s) + ' s'], ['ts', new Date(m.ts * 1000).toLocaleString(LOCALE)]];
                facts.forEach(function (f) {
                    var dt = document.createElement('dt'); dt.className = 'col-4 col-md-3'; dt.textContent = (L.f && L.f[f[0]]) || f[0];
                    var dd = document.createElement('dd'); dd.className = 'col-8 col-md-9'; dd.textContent = f[1] === '' || f[1] === undefined ? '—' : f[1];
                    dl.appendChild(dt); dl.appendChild(dd);
                });
                var ul = document.getElementById('an-md-related');
                ul.textContent = '';
                (j.related || []).forEach(function (e) {
                    var li = document.createElement('li');
                    li.textContent = new Date(e.ts * 1000).toLocaleTimeString(LOCALE) + ' · ' + e.type + ' · ' + JSON.stringify(e.data);
                    ul.appendChild(li);
                });
                if (!(j.related || []).length) { var li = document.createElement('li'); li.className = 'text-muted'; li.textContent = L.none || '—'; ul.appendChild(li); }
                document.getElementById('an-md-raw').textContent = JSON.stringify(j.raw || {}, null, 2);
                box.hidden = false;
                box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            })
            .catch(function () { /* détail indisponible : rien à afficher */ });
    }

    // ── Usage : courbe d'une métrique dans une fenêtre ──────────────────
    var usageChart = null;
    function showUsage(spec, title) {
        var modalEl = document.getElementById('an-usage-modal');
        if (!modalEl || typeof bootstrap === 'undefined') { return; }
        document.getElementById('an-usage-title').textContent = title;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        var canvas = document.getElementById('an-usage-canvas');
        if (usageChart) { usageChart.destroy(); usageChart = null; }
        fetch(URL_DATA + '?kind=series&period=' + encodeURIComponent(period()) + '&series[]=' + encodeURIComponent(['usage', spec.metric, spec.ref, 'avg'].join('|')),
            { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var labels = (j.labels || []).map(function (t) { return fmtTs(t, j.bucket); });
                var data = (j.series && j.series[0]) || [];
                usageChart = new Chart(canvas, { type: 'line', data: { labels: labels, datasets: [{ label: title, data: data, borderColor: '#4ade80', backgroundColor: '#4ade8022', fill: true, spanGaps: true, pointRadius: 0, tension: .3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { color: text() }, grid: { color: grid() } }, x: { ticks: { color: text(), maxRotation: 0 } } } } });
                setEmpty(canvas, !labels.length);
            })
            .catch(function () { setEmpty(canvas, true); });
    }

    // ── Délégation d'événements (survit aux rafraîchissements) ─────────
    root.addEventListener('click', function (e) {
        var line = e.target.closest && e.target.closest('#an-map .tr');
        if (line) { showMissile(line.getAttribute('data-id')); return; }
        var row = e.target.closest && e.target.closest('tr[data-missile]');
        if (row) { showMissile(row.getAttribute('data-missile')); return; }
        var u = e.target.closest && e.target.closest('tr[data-usage-series]');
        if (u) {
            try { var s = JSON.parse(u.getAttribute('data-usage-series')); showUsage(s, u.cells[0].textContent.trim() + (s.ref ? ' · ' + s.ref : '')); } catch (x) { /* ignore */ }
            return;
        }
        if (e.target.id === 'an-md-close') { document.getElementById('an-missile-detail').hidden = true; }
    });
    root.addEventListener('change', function (e) {
        if (e.target.id === 'an-map-world') { drawMap(); return; }
        if (e.target.closest && e.target.closest('#an-countries-form')) {
            clearTimeout(root._t);
            root._t = setTimeout(function () { document.getElementById('an-countries-form').submit(); }, 600);
        }
    });

    // ── Mise à jour automatique (30 s) ─────────────────────────────────
    var auto = document.getElementById('an-auto');
    try { if (localStorage.getItem('an_auto') === '0') { auto.checked = false; } } catch (e) { /* stockage indisponible */ }
    auto.addEventListener('change', function () { try { localStorage.setItem('an_auto', auto.checked ? '1' : '0'); } catch (e) { /* ignore */ } });

    function refresh() {
        if (!auto.checked || document.hidden) { return; }
        var detail = document.getElementById('an-missile-detail');
        if ((detail && !detail.hidden) || document.querySelector('.modal.show') || (document.activeElement && /^(INPUT|SELECT|TEXTAREA)$/.test(document.activeElement.tagName) && document.activeElement.id !== 'an-auto')) { return; }
        fetch(location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.getElementById('an-live');
                var live = document.getElementById('an-live');
                if (!fresh || !live) { return; }
                destroyCharts();
                live.innerHTML = fresh.innerHTML; // HTML rendu par le serveur (Blade échappe les données)
                initCharts(live);
                loadMap();
            })
            .catch(function () { /* session expirée / réseau : on garde l'affichage */ });
    }

    initCharts(document);
    loadMap();
    window.__anRefresh = refresh;
    setInterval(refresh, 30000);
})();
