/*!
 * @version 1.4.10
 * @package Perfect Dashboard
 * @copyright © 2016 Perfect sp. z o.o., All rights reserved. https://www.perfectdashboard.com
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect Dashboard
 */

jQuery(document).ready(function($) {
    var btn = $('.perfectdashboard-settings-button');

    if(btn.length) {
        btn.on('click', function(e) {
            e.preventDefault();
            var settingsScreen = $('.perfectdashboard-settings');
            var startScreen = $('.perfectdashboard-start');

            if(settingsScreen.hasClass('perfectdashboard-view-active')) {
                settingsScreen.removeClass('perfectdashboard-view-active');
                btn.html(btn.attr('data-open'));

                setTimeout(function() {
                    settingsScreen.addClass('perfectdashboard-view-inactive');
                    startScreen.removeClass('perfectdashboard-view-inactive');

                    setTimeout(function() {
                        startScreen.addClass('perfectdashboard-view-active');
                    }, 25);
                }, 200);
            } else {
                startScreen.removeClass('perfectdashboard-view-active');
                btn.html(btn.attr('data-close'));

                setTimeout(function() {
                    startScreen.addClass('perfectdashboard-view-inactive');
                    settingsScreen.removeClass('perfectdashboard-view-inactive');

                    setTimeout(function() {
                        settingsScreen.addClass('perfectdashboard-view-active');
                    }, 25);
                }, 200);
            }
        });

        $('#perfectdashboard_save_config').on('click', function(e) {
            e.preventDefault();
            var key = $('#perfectdashboard_key').val();
            var siteOffline = $('input[type="radio"][name="site_offline"]:checked').val();
            var sslVerify = $('input[type="radio"][name="ssl_verify"]:checked').val();

            var data = {
                'action': 'perfectdashboard_save_config',
                'key_value': key,
                'site_offline': siteOffline,
                'ssl_verify': sslVerify
            };

            $.ajax({
                url: ajax_object.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log(response);
                }
            });
        });
    }
});
