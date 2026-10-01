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
