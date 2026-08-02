/* ============================================================
   DISPLAY.JS - LAYAR MONITOR CAFE STORY CAFE
   - Jam & tanggal realtime
   - Hitung mundur auto-refresh lalu muat ulang halaman
   - Carousel rotasi slide (Menu Terlaris <-> Promo & Rekomendasi)
   Interval refresh diambil dari window.AUTO_REFRESH (detik),
   interval rotasi dari window.DISPLAY_ROTASI (detik).
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Carousel slide ---------- */
    const carousel = document.getElementById('carousel');
    if (carousel) {
        const slides = carousel.querySelectorAll('.slide');
        const dots   = carousel.querySelectorAll('.dot');
        const rotasi = window.DISPLAY_ROTASI || 8;
        let idx = 0, timer = null;

        /* animasi angka harga hero (count-up) */
        const heroPrice = document.querySelector('.hero-price');
        const heroNum   = heroPrice ? heroPrice.querySelector('.num') : null;
        let counted = false;

        function countUp() {
            if (!heroPrice || !heroNum || counted) return;
            counted = true;
            const target = parseInt(heroPrice.dataset.count, 10) || 0;
            const dur = 1400, t0 = performance.now();
            (function step(t) {
                const p = Math.min(1, (t - t0) / dur);
                const eased = 1 - Math.pow(1 - p, 3);
                heroNum.textContent = Math.round(target * eased).toLocaleString('id-ID');
                if (p < 1) requestAnimationFrame(step);
            })(t0);
        }

        function show(n) {
            idx = (n + slides.length) % slides.length;
            slides.forEach(function (s, k) { s.classList.toggle('slide-active', k === idx); });
            dots.forEach(function (d, k) { d.classList.toggle('dot-active', k === idx); });
            if (idx === 0) countUp();
        }

        function next() { show(idx + 1); }
        function start() { timer = setInterval(next, rotasi * 1000); }
        function stop() { clearInterval(timer); timer = null; }
        function restart() { stop(); start(); }

        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', restart);
        dots.forEach(function (d, k) {
            d.addEventListener('click', function () { show(k); restart(); });
        });

        start();
    }


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

    /* hitung mundur auto-refresh */
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
