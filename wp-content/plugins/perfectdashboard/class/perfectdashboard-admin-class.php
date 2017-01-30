<?php
/**
 * @version 1.4.10
 *
 * @copyright © 2016 Perfect sp. z o.o., All rights reserved. https://www.perfectdashboard.com
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect Dashboard
 */

// No direct access
function_exists('add_action') or die;

add_action('admin_menu', array('PerfectDashboardAdmin', 'adminMenu'));
add_action('admin_init', array('PerfectDashboardAdmin', 'init'));
add_filter('gettext', array('PerfectDashboardAdmin', 'gettext'), 10, 3);
add_filter('plugin_row_meta', array('PerfectDashboardAdmin', 'plugin_row_meta'), 10, 4);

class PerfectDashboardAdmin
{
    /**
     * Override 'Perfect Dashboard' name by filtering.
     *
     * @param type $translations
     * @param type $text
     * @param type $domain
     *
     * @return type
     */
    public static function gettext($translations, $text, $domain)
    {
        if ($domain == 'perfectdashboard') {
            if ($text == 'Perfect Dashboard') {
                return get_option('perfectdashboard-name', 'Perfect Dashboard');
            } elseif ($text == 'Perfect-Web' && get_option('perfectdashboard-name')) {
                return '';
            }
        }

        return $translations;
    }

    public static function plugin_row_meta($plugin_meta, $plugin_file, $plugin_data, $status)
    {
        if (dirname($plugin_file) == 'perfectdashboard' && get_option('perfectdashboard-name')) {
            foreach ($plugin_meta as $key => $meta) {
                if (strpos($meta, 'plugin-install.php?tab=plugin-information') !== false) {
                    unset($plugin_meta[$key]);
                }
            }
        }

        return $plugin_meta;
    }

    /**
     * Add menu entry with plug-in settings page.
     */
    public static function adminMenu()
    {
        $name = get_option('perfectdashboard-name');
        if (empty($name) || $name == 'Perfect Dashboard') {
            add_menu_page(__('Perfect Dashboard', 'perfectdashboard'), __('Perfect Dashboard', 'perfectdashboard'), 'manage_options', 'perfectdashboard-config', array(__CLASS__, 'displayConfiguration')
            );
        } else {
            add_submenu_page('tools.php', $name, $name, 'manage_options', 'perfectdashboard-config', array(__CLASS__, 'displayConfiguration')
            );
        }
    }

    /**
     * Add media and ajax actions.
     */
    public static function init()
    {
        wp_register_style('perfect-dashboard',
            plugins_url('media/css/style.css', PERFECTDASHBORD_PATH.'/perefectdashboard.php'),
            array(), '0.1');
        wp_enqueue_style('perfect-dashboard');
        wp_register_script('script',
            plugins_url('media/js/script.js', PERFECTDASHBORD_PATH.'/perefectdashboard.php'),
            array('jquery'), '1.11.3');
        wp_localize_script('script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
        wp_enqueue_script('script');

        if (defined('DOING_AJAX')) {
            add_action('wp_ajax_perfectdashboard_save_config', array(__CLASS__, 'saveConfig'));
        }

        add_action('admin_notices', array(__CLASS__, 'configNotice'), 0);
    }

    /**
     * Display template of settings page.
     */
    public static function displayConfiguration()
    {
        $key = get_option('perfectdashboard-key', null);

        $site_offline = get_option('perfectdashboard-site-offline', null);
        $ssl_verify = get_option('perfectdashboard-sslverify', 1);

        require_once PERFECTDASHBORD_PATH.'/tmpl/tmpl-admin.php';
    }

    /**
     * Save key into db.
     */
    public static function saveConfig()
    {
        require_once dirname(__FILE__).'/perfectdashboard-filterinput-class.php';

        $filter = PerfectDashboardFilterInput::getInstance();

        if (isset($_POST['key_value']) && $_POST['key_value']) {
            $key = $filter->clean($_POST['key_value'], 'cmd');
            update_option('perfectdashboard-key', $key);
        }

        if (isset($_POST['site_offline'])) {
            $site_offline = $filter->clean($_POST['site_offline'], 'int');
            update_option('perfectdashboard-site-offline', $site_offline);
        }

        if (isset($_POST['ssl_verify'])) {
            $ssl_verify = $filter->clean($_POST['ssl_verify'], 'int');
            update_option('perfectdashboard-sslverify', $ssl_verify);
        }
    }

    public static function configNotice()
    {
        global $hook_suffix, $user_email;

        $ping = get_option('perfectdashboard-ping');

        if ($hook_suffix == 'plugins.php' and empty($ping)) {
            $plugins = get_option('active_plugins');
            $active = false;
            foreach ($plugins as $i => $plugin) {
                if (strpos($plugin, '/perfectdashboard.php') !== false) {
                    $active = true;
                    break;
                }
            }
            if ($active) {
                $key = get_option('perfectdashboard-key');
                ?>
                <div class="updated">
                    <form action="<?php echo PERFECTDASHBOARD_ADDWEBSITE_URL; ?>?utm_source=backend&amp;utm_medium=installer&amp;utm_campaign=WP" method="post" style="margin: 0">
                        <p style="margin: 25px 0 0 80px; font-size: 16px; display: inline-block;">
                            <img src="https://perfectdashboard.com/assets/images/shield.svg" alt="Perfect Dashboard" style="float: left; width: 60px; margin: -10px 0 0 -70px;">
                            <strong><?php _e('Well done!', 'perfectdashboard'); ?></strong><br>
                            <?php printf(__('You are just a step away from automating updates & backups on this website with %s', 'perfectdashboard'), '<strong style="color:#0aa6bd">Perfect Dashboard</strong>'); ?>
                        </p>
                        <button type="submit" class="button button-primary button-hero" style="margin: 25px 0 25px 20px; vertical-align: top; font-size: 18px;"><?php _e('Finish configuration', 'perfectdashboard'); ?></button>

                        <input type="hidden" name="secure_key" value="<?php echo $key; ?>">
                        <input type="hidden" name="user_email" value="<?php echo $user_email; ?>">
                        <input type="hidden" name="site_frontend_url" value="<?php echo get_site_url(); ?>">
                        <input type="hidden" name="site_backend_url" value="<?php echo get_admin_url(); ?>">
                        <input type="hidden" name="cms_type" value="wordpress">
                        <input type="hidden" name="version" value="<?php echo PERFECTDASHBOARD_VERSION; ?>">
                    </form>
                </div>
            <?php

            }
        }
    }
}
