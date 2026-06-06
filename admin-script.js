
jQuery(document).ready(function($) {
    // media uploader
    $('#upload_icon_btn').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({
            title: 'انتخاب تصویر آیکون',
            button: { text: 'انتخاب' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#icon_url').val(attachment.url);
        });
        frame.open();
    });

    // add icon
    $('#add-icon-form').on('submit', function(e) {
        e.preventDefault();
        var formData = {
            action: 'save_floating_icon',
            nonce: floatingIconsAjax.nonce,
            icon_name: $('#icon_name').val(),
            icon_url: $('#icon_url').val(),
            messenger_link: $('#messenger_link').val()
        };
        $.post(floatingIconsAjax.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data);
                location.reload();
            } else {
                alert('خطا: ' + response.data);
            }
        });
    });
    
    // delete
    $(document).on('click', '.delete-icon', function() {
        if (!confirm('آیا از حذف این ایکون اطمینان دارید؟')) return;
        var iconId = $(this).data('id');
        var row = $(this).closest('tr');
        $.post(floatingIconsAjax.ajax_url, {
            action: 'delete_floating_icon',
            nonce: floatingIconsAjax.nonce,
            icon_id: iconId
        }, function(response) {
            if (response.success) {
                row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('خطا: ' + response.data);
            }
        });
    });

    // sortable order
    $('#icons-list-body').sortable({
        update: function() {
            var order = [];
            $('#icons-list-body tr').each(function(i){
                order.push($(this).data('id'));
                $(this).find('.icon-order').text(i+1);
            });
            $.post(floatingIconsAjax.ajax_url, {
                action: 'update_icon_order',
                nonce: floatingIconsAjax.nonce,
                order: order
            });
        }
    });

    // settings save
    $('#settings-form').on('submit', function(e) {
        e.preventDefault();
        var formData = {
            action: 'update_settings',
            nonce: floatingIconsAjax.nonce,
            position: $('#position').val(),
            layout: $('#layout').val(),
            icon_size: $('#icon_size').val(),
            spacing: $('#spacing').val(),
            offset_x: $('#offset_x').val(),
            offset_y: $('#offset_y').val()
        };
        $.post(floatingIconsAjax.ajax_url, formData, function(response) {
            if (response.success) {
                alert(response.data);
            } else {
                alert('خطا: ' + response.data);
            }
        });
    });

    // live preview
    function updatePreview(){
        var wrap = $('#floating-icons-live-preview');
        wrap.attr('data-position', $('#position').val());
        wrap.attr('data-layout', $('#layout').val());
        wrap.css({
            '--icon-size': $('#icon_size').val() + 'px',
            '--spacing': $('#spacing').val() + 'px',
            '--offset-x': $('#offset_x').val() + 'px',
            '--offset-y': $('#offset_y').val() + 'px'
        });
    }
    $('#position, #layout, #icon_size, #spacing, #offset_x, #offset_y').on('input change', updatePreview);

    // nudge buttons
    $('.nudge').on('click', function(){
        var dir = $(this).data('dir');
        var step = 10;
        if(dir === 'up') $('#offset_y').val(Math.max(0, parseInt($('#offset_y').val()||0) - step));
        if(dir === 'down') $('#offset_y').val(parseInt($('#offset_y').val()||0) + step);
        if(dir === 'left') $('#offset_x').val(Math.max(0, parseInt($('#offset_x').val()||0) - step));
        if(dir === 'right') $('#offset_x').val(parseInt($('#offset_x').val()||0) + step);
        updatePreview();
    });

    updatePreview();
});
