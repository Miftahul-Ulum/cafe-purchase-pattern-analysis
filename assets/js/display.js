/* ============================================================
   DISPLAY.JS - LAYAR MONITOR CAFE STORY CAFE
   - Jam & tanggal realtime
   - Hitung mundur auto-refresh lalu muat ulang halaman
   Interval refresh diambil dari window.AUTO_REFRESH (detik).
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    const clockEl  = document.getElementById('clock');
    const dateEl   = document.getElementById('date');
    const countEl  = document.getElementById('count');
    const interval = window.AUTO_REFRESH || 60;

    const pad = (n) => String(n).padStart(2, '0');

    function updateClock() {
        const now = new Date();
        if (clockEl) {
            clockEl.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        }
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString('id-ID', {
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });
        }
    }

    updateClock();
    setInterval(updateClock, 1000);

    let left = interval;
    if (countEl) {
        setInterval(function () {
            left--;
            if (left <= 0) {
                location.reload();
                return;
            }
            countEl.textContent = left;
        }, 1000);
    }
});
