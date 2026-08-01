/* ============================================================
   MAIN JS - RENDER GRAFIK DASHBOARD ANALISIS STORY CAFE
   Data diambil dari window.CHART_DATA yang diisi oleh
   analisis_full.php (PHP) sebelum memuat file ini.
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    const D = window.CHART_DATA;

    /* jika tidak ada data, jangan render grafik */
    if (!D || !D.hasData) return;

    const colors = ['#0d6efd', '#6f42c1', '#198754', '#ffc107', '#dc3545',
                    '#0dcaf0', '#fd7e14', '#20c997', '#e83e8c', '#6c757d'];

    Chart.defaults.font.family = 'Segoe UI, Arial, sans-serif';
    Chart.defaults.font.size = 11;

    /* Pendapatan per Tanggal (bar + line) */
    new Chart(document.getElementById('chartTren'), {
        type: 'bar',
        data: {
            labels: D.dateLabels,
            datasets: [
                { label: 'Pendapatan (Rp)', data: D.dateRev, backgroundColor: '#0d6efd', borderRadius: 3 },
                { label: 'Transaksi', data: D.dateN, type: 'line', borderColor: '#198754',
                  backgroundColor: '#198754', yAxisID: 'y1', tension: .3 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { beginAtZero: true },
                y1: { beginAtZero: true, position: 'right', grid: { display: false } }
            }
        }
    });

    /* Jumlah Transaksi per Jam */
    new Chart(document.getElementById('chartJam'), {
        type: 'bar',
        data: {
            labels: D.jamLabels,
            datasets: [{ label: 'Transaksi', data: D.jamVals, backgroundColor: '#6f42c1', borderRadius: 3 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    /* Transaksi per Hari (Mingguan) */
    new Chart(document.getElementById('chartHari'), {
        type: 'bar',
        data: {
            labels: D.hariLabels,
            datasets: [{ label: 'Transaksi', data: D.hariVals, backgroundColor: '#20c997', borderRadius: 3 }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    /* Top 10 Menu berdasarkan Pendapatan (horizontal) */
    new Chart(document.getElementById('chartTopMenu'), {
        type: 'bar',
        data: {
            labels: D.topLabels,
            datasets: [{
                label: 'Pendapatan (Rp)',
                data: D.topVals,
                backgroundColor: colors.slice(0, D.topVals.length),
                borderRadius: 3
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });

    /* Porsi Pendapatan per Kategori */
    new Chart(document.getElementById('chartKategori'), {
        type: 'doughnut',
        data: {
            labels: D.katLabels,
            datasets: [{ data: D.katVals, backgroundColor: ['#ffc107', '#0d6efd'] }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
