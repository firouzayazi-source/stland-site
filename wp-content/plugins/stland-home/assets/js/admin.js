jQuery(function ($) {
    $('.stlh-media').each(function () {
        var box = $(this), input = box.find('input[type=hidden]'), prev = box.find('.stlh-prev'), frame;
        box.on('click', '.stlh-pick', function (e) {
            e.preventDefault();
            if (!frame) {
                frame = wp.media({ title: 'انتخاب تصویر', library: { type: 'image' }, multiple: false, button: { text: 'انتخاب' } });
                frame.on('select', function () {
                    var a = frame.state().get('selection').first().toJSON();
                    var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
                    input.val(a.id);
                    prev.empty().append($('<img>', { src: url, css: { maxWidth: '320px', height: 'auto', borderRadius: '8px' } }));
                });
            }
            frame.open();
        });
        box.on('click', '.stlh-clear', function (e) {
            e.preventDefault();
            input.val('0');
            prev.empty();
        });
    });
});

// ترتیبِ بخش‌ها: کشیدن و رها کردن + تیک → فیلدِ مخفیِ order (یک کلید در هر خط)
jQuery(function ($) {
    $('.stlh-sections').each(function () {
        var list = $(this), input = list.siblings('input[type=hidden]');
        function sync() {
            input.val(list.children('li').filter(function () {
                return $(this).find('input[type=checkbox]').is(':checked');
            }).map(function () { return $(this).data('key'); }).get().join('\n'));
        }
        list.sortable({ handle: '.stlh-grip', axis: 'y', update: sync });
        list.on('change', 'input[type=checkbox]', sync);
        sync();
    });
});

// ردیف‌های محصول: کشیدن، افزودن، حذف. نامِ فیلدها بعد از هر جابه‌جایی از نو
// شماره می‌خورد تا ترتیبِ ذخیره‌شده همان ترتیبِ روی صفحه باشد. «touched» فقط
// وقتی پر می‌شود که واقعاً دستی به ردیف‌ها خورده؛ وگرنه حالتِ خودکار می‌ماند.
jQuery(function ($) {
    $('.stlh-lines').each(function () {
        var list = $(this), cell = list.closest('td'), touched = cell.find('.stlh-lines-touched');
        var tpl = cell.find('.stlh-line-tpl').get(0);
        var base = touched.attr('name').replace('[lines_touched]', '[lines]');
        function renumber() {
            list.children('li').each(function (i) {
                $(this).find('[data-f]').each(function () {
                    $(this).attr('name', base + '[' + i + '][' + $(this).data('f') + ']');
                });
            });
        }
        function touch() {
            touched.val('1');
            renumber();
        }
        list.sortable({ handle: '.stlh-grip', axis: 'y', update: touch });
        list.on('change input', '[data-f]', function () {
            touched.val('1');
            $(this).closest('li').find('.stlh-line-info').text('بعد از ذخیره به‌روز می‌شود');
        });
        list.on('click', '.stlh-line-del', function () {
            $(this).closest('li').remove();
            touch();
        });
        cell.on('click', '.stlh-line-add', function () {
            var li = $(document.importNode(tpl.content, true)).children('li');
            list.append(li);
            touch();
            li.find('select[data-f=cat]').trigger('focus');
        });
        cell.on('click', '.stlh-line-reset', function (e) {
            if (!window.confirm('ردیف‌ها دوباره خودکار از درختِ دسته‌ها ساخته شوند؟ چیدمانِ فعلی پاک می‌شود.')) {
                e.preventDefault();
            }
        });
        renumber();
    });
});
