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
