(function($) {
    'use strict';

    $(document).ready(function() {
        var frame = null;

        $('#inblue-title-icon-select').on('click', function(e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: 'Icono del título',
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function() {
                var att = frame.state().get('selection').first().toJSON();
                var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                $('#inblue-title-icon-preview').html('<img src="' + url + '" style="max-width:64px;height:auto">');
                $('#inblue-title-icon-id').val(att.id);
            });
            frame.open();
        });

        $('#inblue-title-icon-remove').on('click', function(e) {
            e.preventDefault();
            $('#inblue-title-icon-preview').empty();
            $('#inblue-title-icon-id').val('');
        });
    });
})(jQuery);
