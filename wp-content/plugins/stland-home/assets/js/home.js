(function () {
    'use strict';
    document.querySelectorAll('.st-faq-light-list').forEach(function (list) {
        var items = list.querySelectorAll('.st-faq-light-item');
        function close(item) {
            item.classList.remove('active');
            item.querySelector('.st-faq-light-question').setAttribute('aria-expanded', 'false');
            item.querySelector('.st-faq-light-answer').style.maxHeight = '';
        }
        items.forEach(function (item) {
            var btn = item.querySelector('.st-faq-light-question');
            var ans = item.querySelector('.st-faq-light-answer');
            btn.addEventListener('click', function () {
                var wasOpen = item.classList.contains('active');
                items.forEach(close);
                if (!wasOpen) {
                    item.classList.add('active');
                    btn.setAttribute('aria-expanded', 'true');
                    ans.style.maxHeight = ans.scrollHeight + 'px';
                }
            });
        });
    });
})();

/*
 * حرکتِ خودکارِ ردیف‌های محصول — اگر در تنظیمات روشن باشد (window.stlhAuto ثانیه).
 * فقط ردیفی که دیده می‌شود جلو می‌رود؛ با لمس، موس یا فوکوس ۸ ثانیه می‌ایستد؛
 * به آخر که رسید از اول. «کاهشِ حرکت» در سیستمِ مشتری روشن باشد، هیچ.
 */
(function () {
    'use strict';
    var sec = +window.stlhAuto || 0;
    if (sec < 1 || !('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }
    document.querySelectorAll('.st-pr-row').forEach(function (row) {
        var visible = false, pausedUntil = 0;
        function pause() { pausedUntil = Date.now() + 8000; }
        ['pointerdown', 'touchstart', 'wheel', 'focusin', 'mouseenter'].forEach(function (ev) {
            row.addEventListener(ev, pause, { passive: true });
        });
        new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }, { threshold: 0.5 }).observe(row);
        setInterval(function () {
            if (!visible || Date.now() < pausedUntil || row.scrollWidth <= row.clientWidth + 4) {
                return;
            }
            var card = row.firstElementChild;
            var gap = parseFloat(getComputedStyle(row).columnGap) || 0;
            var step = (card ? card.getBoundingClientRect().width : row.clientWidth * 0.4) + gap;
            var rtl = getComputedStyle(row).direction === 'rtl';
            // در راست‌به‌چپ scrollLeft از صفر منفی می‌شود
            var atEnd = Math.abs(row.scrollLeft) + row.clientWidth >= row.scrollWidth - 4;
            if (atEnd) {
                row.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                row.scrollBy({ left: rtl ? -step : step, behavior: 'smooth' });
            }
        }, sec * 1000);
    });
})();
