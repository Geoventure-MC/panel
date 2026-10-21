/* Modération — graphiques (Chart.js 4 vendorisé). Aucune donnée injectée via innerHTML. */
(function () {
    'use strict';
    var root = document.getElementById('mod-root');
    if (!root || typeof Chart === 'undefined') { return; }
    var COLORS = ['#fb923c', '#60a5fa', '#a78bfa', '#f87171', '#4ade80', '#facc15'];
    var text = getComputedStyle(document.body).color || '#888';
    var grid = 'rgba(127,127,127,.18)';

    document.querySelectorAll('canvas[data-mod-chart]').forEach(function (canvas) {
        var spec;
        try { spec = JSON.parse(canvas.getAttribute('data-mod-chart')); } catch (e) { return; }
        var d = spec.data || { labels: [], datasets: [] };
        var empty = !d.datasets.length || d.datasets.every(function (s) { return s.data.every(function (v) { return !v; }); });
        var box = canvas.parentNode.querySelector('.mod-empty');
        if (empty) {
            if (box) { box.classList.remove('d-none'); }
            canvas.style.visibility = 'hidden';
            return;
        }
        var round = spec.type === 'doughnut';
        var ds = d.datasets.map(function (s, i) {
            var c = s.color || COLORS[i % COLORS.length];
            return { label: s.label, data: s.data, backgroundColor: round ? (s.colors || COLORS) : c, borderColor: round ? 'transparent' : c, borderRadius: round ? 0 : 2 };
        });
        var opts = { responsive: true, maintainAspectRatio: false, animation: { duration: 250 }, plugins: { legend: { display: round, labels: { color: text, boxWidth: 12 } } } };
        if (!round) {
            opts.scales = {
                x: { ticks: { color: text, maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: text, precision: 0 }, grid: { color: grid } }
            };
        }
        new Chart(canvas, { type: spec.type, data: { labels: d.labels, datasets: ds }, options: opts });
    });
})();
