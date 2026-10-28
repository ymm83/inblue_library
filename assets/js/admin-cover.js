(function($) {
    'use strict';

    $(document).on('click', '.inblue-get-cover', function(e) {
        e.preventDefault();
        var btn = $(this);
        var td = btn.closest('td');
        var spinner = td.find('.inblue-cover-spinner');
        var errorEl = td.find('.inblue-cover-error');
        var postId = btn.data('post-id');

        btn.hide();
        spinner.show();
        errorEl.hide();

        $.ajax({
            url: inblueAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'inblue_get_cover_list',
                nonce: inblueAdmin.nonce,
                post_id: postId,
            },
            dataType: 'json',
            timeout: 120000,
            success: function(resp) {
                spinner.hide();
                if (resp.success && resp.data.thumbnail_url) {
                    td.find('.inblue-get-cover, .inblue-cover-spinner, .inblue-cover-error').remove();
                    td.html('<img src="' + resp.data.thumbnail_url + '" style="max-width:70px;height:auto" alt="">');
                } else {
                    errorEl.text(resp.data || 'Error').show();
                    btn.show();
                }
            },
            error: function(xhr, status, err) {
                spinner.hide();
                var msg = 'Error de conexión';
                if (status === 'timeout') {
                    msg = 'La solicitud agotó el tiempo. El servidor puede tardar en llegar a Google Drive. Inténtalo de nuevo.';
                } else if (xhr && xhr.responseText) {
                    try {
                        var j = JSON.parse(xhr.responseText);
                        msg = j.data || 'Error';
                    } catch (e) {
                        msg = 'Error del servidor (HTTP ' + xhr.status + ')';
                    }
                }
                errorEl.text(msg).show();
                btn.show();
            }
        });
    });

})(jQuery);
