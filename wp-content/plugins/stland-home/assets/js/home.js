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
