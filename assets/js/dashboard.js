/* TrackShield - Dashboard charts (Chart.js). Data comes from <script type="application/json" id="..."> blocks. */
(function () {
    'use strict';
    if (typeof Chart === 'undefined') return;

    var palette = ['#0f2147', '#f47b20', '#d62828', '#1f9d55', '#2563eb', '#7c3aed', '#0f8b8d', '#b7791f', '#5b6577', '#e11d48'];
    var statusColors = {
        'Submitted': '#2563eb', 'Under Verification': '#b7791f', 'Assigned': '#7c3aed',
        'Response Team Dispatched': '#e0670c', 'In Progress': '#0f8b8d', 'Resolved': '#1f9d55', 'Rejected': '#9aa4b5'
    };
    var priorityColors = { 'Critical': '#d62828', 'High': '#f47b20', 'Medium': '#1f3f7a' };
    var rvpColors = { 'Resolved': '#1f9d55', 'Pending': '#f47b20', 'Rejected': '#9aa4b5' };

    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.color = '#5b6577';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.maintainAspectRatio = false;

    function data(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }
    function colorsFor(labels, map) {
        return labels.map(function (l, i) { return (map && map[l]) || palette[i % palette.length]; });
    }

    document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
        var d = data(canvas.dataset.source);
        if (!d || !d.labels) return;
        var type = canvas.dataset.chart;
        var map = canvas.dataset.colors === 'status' ? statusColors : canvas.dataset.colors === 'priority' ? priorityColors : canvas.dataset.colors === 'rvp' ? rvpColors : null;
        var cfg;

        if (type === 'doughnut' || type === 'pie') {
            cfg = {
                type: type,
                data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: colorsFor(d.labels, map), borderWidth: 2, borderColor: '#fff' }] },
                options: { cutout: type === 'doughnut' ? '62%' : 0, plugins: { legend: { position: 'right' } } }
            };
        } else if (type === 'line') {
            cfg = {
                type: 'line',
                data: { labels: d.labels, datasets: (d.datasets || [{ label: d.label || 'Emergencies', data: d.values }]).map(function (ds, i) {
                    var c = ['#f47b20', '#1f9d55', '#0f2147'][i % 3];
                    return { label: ds.label, data: ds.data, borderColor: c, backgroundColor: c + '22', fill: i === 0, tension: .35, pointRadius: 3 };
                }) },
                options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } }, plugins: { legend: { display: !!d.datasets } } }
            };
        } else {
            var horizontal = canvas.dataset.horizontal === '1';
            cfg = {
                type: 'bar',
                data: { labels: d.labels, datasets: (d.datasets || [{ label: d.label || 'Emergencies', data: d.values }]).map(function (ds, i) {
                    return { label: ds.label, data: ds.data, backgroundColor: d.datasets ? ['#0f2147', '#1f9d55', '#f47b20'][i % 3] : colorsFor(d.labels, map), borderRadius: 6, maxBarThickness: 38 };
                }) },
                options: {
                    indexAxis: horizontal ? 'y' : 'x',
                    scales: { x: { beginAtZero: true, grid: { display: horizontal }, ticks: { precision: 0 } }, y: { beginAtZero: true, grid: { display: !horizontal }, ticks: { precision: 0 } } },
                    plugins: { legend: { display: !!d.datasets } }
                }
            };
        }
        new Chart(canvas, cfg);
    });
})();
