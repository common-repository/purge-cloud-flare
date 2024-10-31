/**
 * Created by Phantom on 18.03.2016.
 */

(function ($) {
    $(document).ready(function () {

        var visibility_toggle = $('#visibility_toggle');
        visibility_toggle.click(function () {
            if ($(this).attr('data-label') == "Show") {
                $('input#API_key').attr('type', 'text');
                $(this).attr('data-label', "Hide");
                $(this).find('.dashicons-visibility').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $(this).find('.text').html('Hide');
            } else if ($(this).attr('data-label') == "Hide") {
                $('input#API_key').attr('type', 'password');
                $(this).attr('data-label', "Show");
                $(this).find('.dashicons-hidden').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $(this).find('.text').html('Hide');
            }
        });
        toggle_modal();
        var purge_button = $('#wp-admin-bar-cf-purger-clear>a, a.cfp-modal__button-submit');
        purge_button.on('click', function () {
            var rel = $(this).attr('rel');
            var files = $('.cfp-modal__textarea').val();
            make_cf_request( rel, files );
            return false;
        });

        function make_cf_request( rel, files ) {
            var plpage_button = $('.cf-purger-clear');
            var urls = [];
            if( files.length !== 0 ) {
                var lines = files.split(/\n/);
                for (var i=0; i < lines.length; i++) {
                    if (/\S/.test(lines[i])) {
                        urls.push($.trim(lines[i]));
                    }
                }
            }
            $.ajax({
                url: cfp_ajaxurl.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'purge_cf_cache',
                    rel: rel,
                    files: urls
                },
                beforeSend: function () {
                    purge_button.toggleClass('spin');
                    if (plpage_button.length !== 0)
                        plpage_button.find('.dashicons-cloud').toggleClass('is-active spinner dashicons dashicons-cloud');
                },
                success: function (data) {
                    animate_buttons(data);
                },
                error: function (response) {

                }
            });
        }

        function animate_buttons( data ) {
            var plpage_button = $('.cf-purger-clear');
            if( data.success ) {
                purge_button.toggleClass('spin').addClass('success');
                setTimeout(function () {
                    purge_button.removeClass('success')
                }, 2000);
                if (plpage_button.length !== 0) {
                    plpage_button.find('.spinner').toggleClass('is-active spinner dashicons dashicons-thumbs-up');
                    setTimeout(function () {
                        plpage_button.find('.dashicons').toggleClass('dashicons-thumbs-up dashicons-cloud')
                    }, 2000);
                }
            } else {
                purge_button.toggleClass('spin').addClass('error');
                setTimeout(function () {
                    purge_button.removeClass('error');
                    alert(data.msg)
                }, 2000);
                if (plpage_button.length !== 0) {
                    plpage_button.find('.spinner').toggleClass('is-active spinner dashicons dashicons-thumbs-down');
                    setTimeout(function () {
                        plpage_button.find('.dashicons').toggleClass('dashicons-thumbs-down dashicons-cloud')
                    }, 2000);
                }
            }
            //window.tb_remove()
        }

        function toggle_modal() {
            var modal          = $('#cloudflare-purger-modal');
            var trigger_button = $('.cloudflare_clear_files_thickbox_trigger');
            var close_button = $('.cfp-modal__button-close');
            trigger_button.on('click',function(){
                _toggle_modal( $(this).find('a').attr('href'));
                return false;
            });
            close_button.on('click',function(e){
                e.preventDefault();
                modal.removeClass('md-show');
            });

            function _toggle_modal( target ) {
                $(target).toggleClass('md-show');
            }
        }

    });

})(jQuery);
