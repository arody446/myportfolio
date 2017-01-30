<?php
/**
 * Plugin Name: Perfect Dashboard
 * Plugin URI: https://perfectdashboard.com/?utm_source=backend&utm_medium=installer&utm_campaign=WP
 * Description:
 * Version: 1.6.0
 * Text Domain: perfectdashboard
 * Author: Perfect Dashboard
 * Author URI: https://perfectdashboard.com/?utm_source=backend&utm_medium=installer&utm_campaign=WP
 * License: GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 */

// No direct access
function_exists('add_action') or die;

if (version_compare($GLOBALS['wp_version'], '3.5', '>=') and version_compare(PHP_VERSION, '5.2.4', '>=')) {
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    $data = get_plugin_data(__FILE__, false, false);
    define('PERFECTDASHBORD_PATH', dirname(__FILE__));
    define('PERFECTDASHBOARD_VERSION', $data['Version']);
    define('PERFECTDASHBOARD_ADDWEBSITE_URL', 'https://app.perfectdashboard.com/site/connect');

    require_once PERFECTDASHBORD_PATH.'/class/perfectdashboard-class.php';

    if (is_admin()) {
        require_once PERFECTDASHBORD_PATH.'/class/perfectdashboard-admin-class.php';
    }
} else {
    function perfectRequirementsNotice()
    {
        ?>
        <div class="error">
            <p><?php printf(__('Perfect Dashboard plugin requires WordPress %s and PHP %s', 'pwebcore'), '3.5+', '5.2.4+'); ?></p>
         </div>
        <?php

    }
    add_action('admin_notices', 'perfectRequirementsNotice');
}
