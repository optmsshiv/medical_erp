/* ==========================================================================
   Optms Rx — Chart Configurations (Stage 1)
   Chart.js wrappers. Each chart reads demo data from MF_DATA.
   ========================================================================== */

window.MFCharts = window.MFCharts || {};

(function () {
  if (typeof Chart === 'undefined') return;

  Chart.defaults.font.family = getComputedStyle(document.documentElement).getPropertyValue('--mf-font');
  Chart.defaults.font.size = 11;
  Chart.defaults.color = '#6B7280';
  Chart.defaults.borderColor = 'rgba(229, 231, 235, 0.7)';

  const PRIMARY = '#176B5B';
  const ACCENT = '#2E8B78';
  const AMBER = '#F59E0B';
  const INFO = '#0EA5E9';

  const inrTicks = (v) => v >= 1000 ? '₹' + (v / 1000).toFixed(v % 1000 ? 1 : 0) + 'k' : '₹' + v;

  /* ---------- Dashboard: Sales Overview (Retail vs Wholesale) ---------- */
  MFCharts.salesOverview = function (canvasId, period) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const data = window.MF_DATA.dashboard.salesTrend[period];
    const cfg = {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          {
            label: 'Retail Sales', data: data.retail, backgroundColor: PRIMARY,
            borderRadius: 6, maxBarThickness: 26, order: 2
          },
          {
            label: 'Wholesale Sales', data: data.wholesale, backgroundColor: AMBER,
            borderRadius: 6, maxBarThickness: 26, order: 3
          },
          {
            label: 'Combined', type: 'line',
            data: data.retail.map((v, i) => v + data.wholesale[i]),
            borderColor: INFO, backgroundColor: 'rgba(14,165,233,.08)',
            tension: 0.35, pointRadius: 3, pointBackgroundColor: INFO, borderWidth: 2,
            fill: false, order: 1
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 7, padding: 16 } },
          tooltip: {
            backgroundColor: '#172026', padding: 10, cornerRadius: 8, usePointStyle: true,
            callbacks: { label: (c) => ` ${c.dataset.label}: ₹${c.parsed.y.toLocaleString('en-IN')}` }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: { grid: { color: 'rgba(229,231,235,.55)' }, ticks: { callback: inrTicks }, border: { display: false } }
        }
      }
    };
    if (canvas._mfChart) { canvas._mfChart.data = cfg.data; canvas._mfChart.update(); return canvas._mfChart; }
    canvas._mfChart = new Chart(canvas, cfg);
    return canvas._mfChart;
  };

  /* ---------- Dashboard: Retail vs Wholesale split (donut) ---------- */
  MFCharts.salesSplit = function (canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const split = window.MF_DATA.dashboard.salesSplit;
    return new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: ['Retail', 'Wholesale'],
        datasets: [{
          data: [split.retail, split.wholesale],
          backgroundColor: [ACCENT, AMBER],
          borderWidth: 3, borderColor: '#fff', hoverOffset: 6
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '72%',
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 7, padding: 16 } },
          tooltip: {
            backgroundColor: '#172026', padding: 10, cornerRadius: 8,
            callbacks: { label: (c) => ` ${c.label}: ₹${c.parsed.toLocaleString('en-IN')}` }
          }
        }
      }
    });
  };

  /* ---------- Reports: GST output/input summary (bar) ---------- */
  MFCharts.gstSummary = function (canvasId, values) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const v = values || [0, 0, 0, 0, 0];
    return new Chart(canvas, {
      type: 'bar',
      data: {
        labels: ['CGST (Out)', 'SGST (Out)', 'IGST (Out)', 'CGST (In/ITC)', 'SGST (In/ITC)'],
        datasets: [{
          label: 'Tax amount',
          data: v,
          backgroundColor: [PRIMARY, ACCENT, INFO, 'rgba(23,107,91,.35)', 'rgba(46,139,120,.35)'],
          borderRadius: 6, maxBarThickness: 44
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: '#172026', padding: 10, cornerRadius: 8, callbacks: { label: (c) => ` ₹${c.parsed.y.toLocaleString('en-IN')}` } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { ticks: { callback: inrTicks }, grid: { color: 'rgba(229,231,235,.55)' }, border: { display: false } }
        }
      }
    });
  };

  /* ---------- Reports: Profit trend (line) ---------- */
  MFCharts.profitTrend = function (canvasId, points) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const pts = points || [];
    return new Chart(canvas, {
      type: 'line',
      data: {
        labels: pts.map((p) => p.label),
        datasets: [{
          label: 'Gross Profit',
          data: pts.map((p) => p.value),
          borderColor: PRIMARY, backgroundColor: 'rgba(23,107,91,.10)',
          fill: true, tension: 0.35, pointRadius: 3, pointBackgroundColor: PRIMARY, borderWidth: 2
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: '#172026', padding: 10, cornerRadius: 8, callbacks: { label: (c) => ` GP: ₹${c.parsed.y.toLocaleString('en-IN')}` } }
        },
        scales: {
          x: { grid: { display: false } },
          y: { ticks: { callback: inrTicks }, grid: { color: 'rgba(229,231,235,.55)' }, border: { display: false } }
        }
      }
    });
  };
})();
