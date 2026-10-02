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

/*
 * دکمه‌های قبلی/بعدی روی ردیف‌ها — فقط وقتی موس هست. هر کلیک تقریباً یک
 * صفحه جلو می‌رود؛ دکمه‌ای که راهی ندارد پنهان می‌شود.
 */
(function () {
    'use strict';
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }
    var icon = function (d) {
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + d + '"/></svg>';
    };
    document.querySelectorAll('.st-pr-track').forEach(function (track) {
        var row = track.querySelector('.st-pr-row');
        if (!row) {
            return;
        }
        var rtl = getComputedStyle(row).direction === 'rtl';
        function make(cls, label, d, dir) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'st-pr-nav ' + cls;
            b.setAttribute('aria-label', label);
            b.innerHTML = icon(d);
            b.addEventListener('click', function () {
                var step = row.clientWidth * 0.8 * dir;
                row.scrollBy({ left: rtl ? -step : step, behavior: 'smooth' });
            });
            track.appendChild(b);
            return b;
        }
        var prev = make('st-pr-nav-prev', 'قبلی', 'M9 6l6 6-6 6', -1);
        var next = make('st-pr-nav-next', 'بعدی', 'M15 6l-6 6 6 6', 1);
        function update() {
            var pos = Math.abs(row.scrollLeft);
            prev.hidden = pos < 4;
            next.hidden = pos + row.clientWidth >= row.scrollWidth - 4;
        }
        row.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    });
})();
