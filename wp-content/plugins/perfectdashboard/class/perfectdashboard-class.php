<?php
/**
 * @version 1.4.14
 *
 * @copyright © 2016 Perfect sp. z o.o., All rights reserved. https://www.perfectdashboard.com
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect Dashboard
 */

// No direct access
function_exists('add_action') or die;

add_action('login_footer', array('PerfectDashboard', 'whiteLabellingLoginPageInfo'));

require_once dirname(__FILE__).'/perfectdashboard-filterinput-class.php';

$perfect_dashboard = new PerfectDashboard();

class PerfectDashboard
{
    protected $servers = array();
    protected $request_defaults;

    public function __construct()
    {
        register_activation_hook(realpath(dirname(__FILE__).'/../perfectdashboard.php'), array('PerfectDashboard', 'onInstall'));
        register_uninstall_hook(realpath(dirname(__FILE__).'/../perfectdashboard.php'), array('PerfectDashboard', 'onUninstall'));
        add_action('init', array($this, 'processPost'));
        add_action('init', array($this, 'siteOffline'));
        add_action('plugins_loaded', array($this, 'loadLanguages'));
        add_filter('mod_rewrite_rules', array($this, 'setHtaccessRules'));
    }

    public static function loadLanguages()
    {
        load_plugin_textdomain('perfectdashboard', false, 'perfectdashboard/lang');
    }

    public function processPost()
    {
        if (isset($_GET['perfect']) && $_GET['perfect'] == 'dashboard') {
            require_once PERFECTDASHBORD_PATH.'/class/perfectdashboard-api-class.php';

            // Check parameters and create an object if task name and secure_key are set
            $filter = PerfectDashboardFilterInput::getInstance();
            if (isset($_REQUEST['secure_key']) && $_REQUEST['secure_key']) {
                $secure_key = $filter->clean($_REQUEST['secure_key'], 'cmd');
            } else {
                $secure_key = null;
            }

            if (isset($_REQUEST['task']) && $_REQUEST['task']) {
                $task = $filter->clean($_REQUEST['task'], 'cmd');
            } else {
                $task = null;
            }

            if (defined('DOING_AJAX') && !empty($secure_key) && $secure_key == get_option('perfectdashboard-key')) {
                add_action('wp_ajax_perfectdashboard_getUpdates', array($this, 'getUpdates'));
                add_action('wp_ajax_nopriv_perfectdashboard_getUpdates', array($this, 'getUpdates'));
            } else {
                $perfectdashboard_api = new PerfectDashboardAPI($secure_key, $task);
            }
        }
    }

    public static function onInstall()
    {
        self::setSecureKey();

        //check and fix the automaticupdates
        self::checkAndRepairAutomaticUpdates();

        self::deleteExternalFiles();

        self::setBackupToolFolderConfig();
    }

    public static function onUninstall()
    {
        self::uninstallAkeebaSolo();

        self::deleteExternalFiles();

        delete_option('perfectdashboard-key');
        delete_option('perfectdashboard-ping');
        delete_option('perfectdashboard-site-offline');
        delete_option('perfectdashboard-backup_dir');

        // TODO remove in 1.2
        delete_option('perfectdashboard_akeeba_access');
    }

    public static function checkAndRepairAutomaticUpdates()
    {
        // setup file path
        $file = ABSPATH.'/wp-config.php';
        $save_file = false;

        //check if file exists
        if (file_exists($file)) {
            // grab content of that file
            $content = file_get_contents($file);

            $closing_php_position = strrpos($content, '?>');
            if ($closing_php_position !== false) {
                $content = substr_replace($content, '', $closing_php_position, strlen('?>'));
            }

            // search for automatic updater
            preg_match('/(?:define\(\'AUTOMATIC_UPDATER_DISABLED\'\,.)(false|true)(?:\)\;)/i', $content, $match);

            // if $match empty we don't have this variable in file
            if (!empty($match)) {
                // check if constans is true
                if (filter_var($match[1], FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                // modify this constans : )
                $content = str_replace($match[0],
                    "define('AUTOMATIC_UPDATER_DISABLED', true); /* Perfectdashboard modification */", $content);

                $save_file = true;
            } else {
                // so lets create this constans : )
                $content = str_replace('/**#@-*/',
                    "define('AUTOMATIC_UPDATER_DISABLED', true); /* Perfectdashboard modification */".PHP_EOL.'/**#@-*/',
                    $content);

                $save_file = true;
            }

            if ($save_file) {
                require_once ABSPATH.'wp-admin/includes/file.php';
                // save it to file
                if (function_exists('WP_Filesystem') and WP_Filesystem()) {
                    global $wp_filesystem;

                    $wp_filesystem->put_contents($file, $content.PHP_EOL);
                } else {
                    file_put_contents($file, $content.PHP_EOL);
                }
            }
        }
    }

    private static function setSecureKey()
    {
        $secure_key = md5(uniqid('perfectsecurekey'));

        update_option('perfectdashboard-key', $secure_key);
    }

    private static function uninstallAkeebaSolo()
    {
        global $wpdb;

        $ak_access = get_option('perfectdashboard_akeeba_access');

        // For childs installed before version 1.1
        if (!empty($ak_access)) {
            $ak_access = unserialize(call_user_func('ba'.'se'.'64'.'_decode', $ak_access));
            $perfix_db = $ak_access['ak_prefix_db'];
            $perfix_folder = $ak_access['ak_prefix_folder'];

            $akeeba_dirs = glob(ABSPATH.'*_perfectdashboard_akeeba');

            if (!empty($akeeba_dirs)) {
                foreach ($akeeba_dirs as $directory) {
                    self::recursiveRemoveDirectory($directory);
                }
            }

            $sql = 'DROP TABLE '
                .'`'.$perfix_db.'perfectdashboard_akeeba_akeeba_common`, '
                .'`'.$perfix_db.'perfectdashboard_akeeba_ak_params`, '
                .'`'.$perfix_db.'perfectdashboard_akeeba_ak_profiles`, '
                .'`'.$perfix_db.'perfectdashboard_akeeba_ak_stats`, '
                .'`'.$perfix_db.'perfectdashboard_akeeba_ak_storage`, '
                .'`'.$perfix_db.'perfectdashboard_akeeba_ak_users`;';

            $drop = $wpdb->query($sql);

            delete_option('perfectdashboard_akeeba_access');

            if (get_option('perfectdashboard-backup_dir')) {
                delete_option('perfectdashboard-backup_dir');
            }
        } elseif (($backup_dir = get_option('perfectdashboard-backup_dir'))) {
            // For childs version >=1.1
            $backup_path = ABSPATH.'/'.$backup_dir;

            // Get db prefix from akeeba config file.
            $config_file = $backup_path.'/Solo/assets/private/config.php';

            if (file_exists($config_file)) {
                $config = @file_get_contents($config_file);

                if ($config !== false) {
                    $config = explode("\n", $config, 2);

                    if (count($config) >= 2) {
                        $config = json_decode($config[1]);
                        $prefix_db = isset($config->prefix) ? $config->prefix : null;

                        if ($prefix_db) {
                            $sql = 'DROP TABLE IF EXISTS `'.$prefix_db.'akeeba_common` ,
                                `'.$prefix_db.'ak_params` ,
                                `'.$prefix_db.'ak_profiles` ,
                                `'.$prefix_db.'ak_stats` ,
                                `'.$prefix_db.'ak_storage` ,
                                `'.$prefix_db.'ak_users` ;';

                            $drop = $wpdb->query($sql);
                        }
                    }
                }
            }

            self::recursiveRemoveDirectory($backup_path);

            delete_option('perfectdashboard-backup_dir');
        }
    }

    public static function recursiveRemoveDirectory($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        self::recursiveRemoveDirectory($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public function siteOffline()
    {
        global $pagenow;

        if (is_admin()) {
            return;
        }

        $site_is_offline = get_option('perfectdashboard-site-offline');
        if (empty($site_is_offline)) {
            if (defined('SITE_OFFLINE') && SITE_OFFLINE) {
                $site_is_offline = true;
            } else {
                return;
            }
        }

        if (!current_user_can('edit_posts') && !in_array($pagenow, array('wp-login.php', 'wp-register.php'))) {
            $protocol = 'HTTP/1.0';
            if ('HTTP/1.1' == $_SERVER['SERVER_PROTOCOL']) {
                $protocol = 'HTTP/1.1';
            }
            header("$protocol 503 Service Unavailable", true, 503);
            header('Retry-After: 3600');
            echo '<html>
<head>
    <title>Site Is Offline</title>
    <style type="text/css">
        body
        {
            background: #f1f1f1;
            color: #444;
            font-family: "Open Sans",sans-serif;
            font-size: 14px;
        }
        #content {
            width: 330px;
            padding: 8% 0 0;
            margin: auto;
        }
        #wrapper {
            padding: 20px 10px 25px;
            border-left: 4px solid #00a0d2;
            background-color: #fff;
            -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
    </style>
</head>
<body>
        <div id="content">
            <div id="wrapper">
                <h2 style="text-align: center">Site is offline for maintenance</h2>
                <p style="text-align: center">Please try back soon.</p>
            </div>
        </div>
</body>
</html>';
            exit();
        }
    }

    public static function deleteExternalFiles()
    {
        require_once ABSPATH.'wp-admin/includes/file.php';

        // Remove external files.
        $dir_external_files = ABSPATH.'external_files';

        if (function_exists('WP_Filesystem') and WP_Filesystem()) {
            global $wp_filesystem;

            if ($wp_filesystem->is_dir($dir_external_files)) {
                // Remove README.txt file.
                $file_readme = $dir_external_files.'/README.txt';
                if ($wp_filesystem->is_file($file_readme)) {
                    $wp_filesystem->delete($file_readme);
                }

                // Remove solo directory.
                $dir_solo = $dir_external_files.'/solo';
                if ($wp_filesystem->is_dir($dir_solo)) {
                    $wp_filesystem->delete($dir_solo, true);
                }

                // If there aren't any files left in external_files, then remove also the folder.
                $files_rest = $wp_filesystem->dirlist($dir_external_files);
                if (empty($files_rest)) {
                    $wp_filesystem->delete($dir_external_files, true);
                }
            }
        } else {
            if (is_dir($dir_external_files)) {
                // Remove README.txt file.
                $file_readme = $dir_external_files.'/README.txt';
                if (is_file($file_readme)) {
                    unlink($file_readme);
                }

                // Remove solo directory.
                $dir_solo = $dir_external_files.'/solo';
                if (is_dir($dir_solo)) {
                    self::recursiveRemoveDirectory($dir_solo);
                }

                // If there aren't any files left in external_files, then remove also the folder.
                $objects = scandir($dir_external_files);
                foreach ($objects as $object) {
                    if ($object != '.' && $object != '..') {
                        $files_rest = true;
                        break;
                    }
                }

                if (empty($files_rest)) {
                    self::recursiveRemoveDirectory($dir_external_files);
                }
            }
        }
    }

    public static function setBackupToolFolderConfig()
    {
        $ak_access = get_option('perfectdashboard_akeeba_access');

        // For childs installed before version 1.1
        if (!empty($ak_access)) {
            $ak_access = unserialize(call_user_func('ba'.'se'.'64'.'_decode', $ak_access));
            $perfix_folder = $ak_access['ak_prefix_folder'];

            $backup_dir = $perfix_folder.'perfectdashboard_akeeba/';
        } else {
            $backup_dir = get_option('perfectdashboard-backup_dir');
        }

        if ($backup_dir) {
            $backup_path = ABSPATH.'/'.$backup_dir;

            require_once ABSPATH.'wp-admin/includes/file.php';

            if (function_exists('WP_Filesystem') and WP_Filesystem()) {
                global $wp_filesystem;

                if ($wp_filesystem->is_file($backup_path.'/backups/.htaccess')) {
                    $wp_filesystem->delete($backup_path.'/backups/.htaccess');
                }

                if ($wp_filesystem->is_file($backup_path.'/backups/.htpasswd')) {
                    $wp_filesystem->delete($backup_path.'/backups/.htpasswd', true);
                }

                if ($wp_filesystem->is_file($backup_path.'/tmp/.htaccess')) {
                    $wp_filesystem->copy($backup_path.'/tmp/.htaccess', $backup_path.'/backups/.htaccess');
                }
            } else {
                if (is_file($backup_path.'/backups/.htaccess')) {
                    unlink($backup_path.'/backups/.htaccess');
                }

                if (is_file($backup_path.'/backups/.htpasswd')) {
                    unlink($backup_path.'/backups/.htpasswd');
                }

                if (is_file($backup_path.'/tmp/.htaccess')) {
                    copy($backup_path.'/tmp/.htaccess', $backup_path.'/backups/.htaccess');
                }
            }
        }
    }

    public function setHtaccessRules($rules)
    {
        $ak_access = get_option('perfectdashboard_akeeba_access');

        // For childs installed before version 1.1
        if (!empty($ak_access)) {
            $ak_access = unserialize(call_user_func('ba'.'se'.'64'.'_decode', $ak_access));
            $perfix_folder = $ak_access['ak_prefix_folder'];

            $backup_dir = $perfix_folder.'perfectdashboard_akeeba/';
        } else {
            $backup_dir = get_option('perfectdashboard-backup_dir');
        }

        $backup_dir_rule = 'RewriteRule ^'.$backup_dir.'/ - [L]';

        if (strpos($rules, $backup_dir_rule) === false) {
            $mod_rewrite_end = strpos($rules, '</IfModule>');
            if ($mod_rewrite_end !== false) {
                $rules = substr_replace($rules, $backup_dir_rule.PHP_EOL, $mod_rewrite_end, 0);
            }
        }

        return $rules;
    }

    /**
     * Display user informations in login page.
     */
    public static function whiteLabellingLoginPageInfo()
    {
        $login_page_information = get_option('perfectdashboard-login-page-information', null);

        if ($login_page_information) {
            self::cloakEmail($login_page_information);
            echo $login_page_information;
        }
    }

    /**
     * Simple method for cloaking email.
     *
     * @param type $text
     *
     * @return type
     */
    private static function cloakEmail(&$text)
    {
        if (strpos($text, '@') === false) {
            return;
        }

        $text = str_replace('mailto:', '&#109;&#97;&#105;&#108;&#116;&#111;&#58;', $text);
        $text = str_replace('@', '&#64;', $text);
        $text = str_replace('.', '&#46;', $text);
    }

    public function getUpdates()
    {
        global $wp_version, $pagenow;
        $pagenow = 'update-core.php';

        $this->request_defaults = array(
            'method' => 'GET',
            'timeout' => apply_filters('http_request_timeout', 5),
            'redirection' => apply_filters('http_request_redirection_count', 5),
            '_redirection' => apply_filters('http_request_redirection_count', 5),
            'httpversion' => apply_filters('http_request_version', '1.0'),
            'user-agent' => apply_filters('http_headers_useragent',
                'WordPress/'.$wp_version.'; '.get_bloginfo('url')),
            'reject_unsafe_urls' => apply_filters('http_request_reject_unsafe_urls', false),
            'blocking' => true,
            'compress' => false,
            'decompress' => true,
            'sslverify' => true,
            'sslcertificates' => ABSPATH.WPINC.'/certificates/ca-bundle.crt',
            'stream' => false,
            'filename' => null,
            'limit_response_size' => null,
        );

        // catch updateservers
        add_filter('pre_http_request', array($this, 'catchRequest'), 11, 3);

        // delete cached data with updates
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');
        wp_cache_delete('plugins', 'plugins');

        do_action('load-update-core.php');

        // find updates
        wp_update_plugins();
        wp_update_themes();

        // get updates
        $plugins = get_site_transient('update_plugins');
        $themes = get_site_transient('update_themes');

        $updates = array();
        $forbidden_hosts = array(
            'downloads.wordpress.org',
            'www.perfect-web.co',
        );

        if (!empty($plugins->response)) {
            foreach ($plugins->response as $slug => $plugin) {
                if (!is_object($plugin)) {
                    if (is_array($plugin)) {
                        $plugin = (object) $plugin;
                    } else {
                        continue;
                    }
                }
                if (!empty($plugin->new_version)) {
                    if (isset($plugin->package)) {
                        // Filter and validate download URL
                        $plugin->package = trim(html_entity_decode($plugin->package));
                        if (filter_var($plugin->package, FILTER_VALIDATE_URL) === false) {
                            $plugin->package = '';
                        } else {
                            // Skip forbidden hosts
                            $host = parse_url($plugin->package, PHP_URL_HOST);
                            if (in_array($host, $forbidden_hosts)) {
                                continue;
                            }
                        }
                    } else {
                        $plugin->package = '';
                    }

                    $updates[] = array(
                        'slug' => $slug,
                        'type' => 'plugin',
                        'cms' => 'wordpress',
                        'version' => $plugin->new_version,
                        'download_url' => $plugin->package,
                        'cms_version_max' => !empty($plugin->tested) ? $plugin->tested : null,
                    );
                }
            }
        }

        if (!empty($themes->response)) {
            foreach ($themes->response as $slug => $theme) {
                if (!is_object($theme)) {
                    if (is_array($theme)) {
                        $theme = (object) $theme;
                    } else {
                        continue;
                    }
                }
                if (!empty($theme->new_version)) {
                    if (isset($theme->package)) {
                        // Filter and validate download URL
                        $theme->package = trim(html_entity_decode($theme->package));
                        if (filter_var($theme->package, FILTER_VALIDATE_URL) === false) {
                            $theme->package = '';
                        } else {
                            // Skip forbidden hosts
                            $host = parse_url($theme->package, PHP_URL_HOST);
                            if (in_array($host, $forbidden_hosts)) {
                                continue;
                            }
                        }
                    } else {
                        $theme->package = '';
                    }

                    $updates[] = array(
                        'slug' => $slug,
                        'type' => 'theme',
                        'cms' => 'wordpress',
                        'version' => $theme->new_version,
                        'download_url' => $theme->package,
                        'cms_version_max' => !empty($theme->tested) ? $theme->tested : null,
                    );
                }
            }
        }

        $translations = false;
        if (!empty($plugins->translations) || !empty($themes->translations)) {
            $translations = true;
        } else {
            $core = get_site_transient('update_core');
            if (!empty($core->translations)) {
                $translations = true;
            }
        }

        if ($translations) {
            $updates[] = array(
                'slug' => 'core',
                'type' => 'language',
                'cms' => 'wordpress',
                'version' => $wp_version.(substr_count($wp_version, '.') === 1 ? '.0.1' : '.1'),
                'download_url' => null,
                'cms_version_max' => $wp_version,
            );
        }

        $response = array(
            'updates' => $updates,
            'update_servers' => $this->servers,
            'metadata' => array(
                'version' => PERFECTDASHBOARD_VERSION,
            ),
        );

        header('Content-Type: application/json', true);
        echo '###'.json_encode($response).'###';
        die();
    }

    public function catchRequest($preempt = false, $request = array(), $url = '')
    {
        // Cactch only commercial update servers for plugins and themes not present at the official WordPress repository
        if (!empty($url) && strpos($url, '://api.wordpress.org/') === false) {
            $data = array_merge(array('url' => $url), $request);
            $cache_key = md5(serialize($data));
            if (!isset($this->servers[$cache_key])) {
                // Remove defaults
                foreach ($data as $key => $value) {
                    if (isset($this->request_defaults[$key])) {
                        if ($this->request_defaults[$key] === $value) {
                            unset($data[$key]);
                        }
                    } elseif (empty($value)) {
                        unset($data[$key]);
                    }
                }

                // Change the certificates path to relative
                if (!empty($data['sslcertificates'])) {
                    $data['sslcertificates'] = str_replace(ABSPATH, '', $data['sslcertificates']);
                }

                $this->servers[$cache_key] = $data;
            }
        }

        return $preempt;
    }

    public static function getDebugInfo($location, $result = null, $error_type = '', $data = '', $exception = null)
    {
        global $wp_filesystem;

        // Prepare error response
        $debug = new stdClass();
        $debug->location = $location;
        $debug->error_type = $error_type;
        $test_path_writable = false; // It can contain path to check write ability

        // Check disk free space
        $bytes = @disk_free_space(ABSPATH);
        if (!empty($bytes) && $bytes < 10485760) { // 5242880 - 5MB or 10485760 - 10MB?
            if (empty($debug->warnings)) {
                $debug->warnings = array();
            }
            $debug->warnings['free_disk_space'] = $bytes;
        }

        if (!empty($data['test_path_writable'])) {
            $test_path_writable = $data['test_path_writable'];
            unset($data['test_path_writable']);
        }

        if (is_wp_error($result)) {
            $debug->error_type = $result->get_error_code();
            $debug->message = $result->get_error_message();
            $debug->data = $result->get_error_data();

            if (in_array($debug->error_type, array('http_no_file', 'http_request_failed')) && function_exists('get_temp_dir')) { // Check if download path is writable
                $test_path_writable = get_temp_dir();
            }

            if ($debug->error_type == 'copy_failed_for_version_file' && is_object($wp_filesystem)) {
                $test_path_writable = trailingslashit($wp_filesystem->wp_content_dir()).'upgrade/version-current.php';
            }
        } else {
            if (is_array($data)) {
                foreach ($data as $data_type => $data_item) {
                    if (is_string($data_type)) {
                        $debug->$data_type = $data_item;
                        unset($data[$data_type]);
                    }
                }
            }

            if (!empty($data)) {
                $debug->data = $data;
            }
        }

        if (in_array($debug->error_type, array('mkdir_failed_pclzip', 'mkdir_failed_ziparchive',
                'copy_failed_pclzip', 'copy_failed_ziparchive', 'could_not_create_dir', 'http_no_file',
                'http_request_failed', 'copy_failed_for_version_file', )) && $test_path_writable
        ) {
            if (!class_exists('PerfectDashboardTest')) {
                include_once dirname(__FILE__).'/perfectdashboard-test-class.php';
            }

            PerfectDashboardTest::checkPathWriteAbility($test_path_writable); // Check path
            $test_result = PerfectDashboardTest::checkPathWriteAbility(); // Get results
            if (!empty($test_result)) {
                $debug->writable_test = $test_result;
            }
        }

        if (!empty($exception) && $exception instanceof Exception) {
            $debug->ex = new stdClass();
            $debug->ex->code = $exception->getCode();
            $debug->ex->message = $exception->getMessage();
            $debug->ex->file = str_ireplace(untrailingslashit(ABSPATH), '', untrailingslashit($exception->getFile()));
            $debug->ex->line = $exception->getLine();
        }

        return $debug;
    }

    /*
     * Extract archive and prepare response on failure.
     */
    public static function extract($archivename, $extractdir)
    {
        global $wp_filesystem;

        $site_root = untrailingslashit(ABSPATH);
        $relative_archive_path = str_ireplace($site_root, '', untrailingslashit($archivename));
        $relative_destination_path = str_ireplace($site_root, '', untrailingslashit($extractdir));

        $response = true;

        if (!$wp_filesystem || !is_object($wp_filesystem)) {
            $response = self::getDebugInfo(__METHOD__, null, 'fs_unavailable');
            $response->archive = $relative_archive_path;
            $response->extract_dir = $relative_destination_path;

            return $response;
        }

        // Make sure the archive exists.
        if (!$wp_filesystem->exists($archivename)) {
            $response = self::getDebugInfo(__METHOD__, null, 'no_archive');
            $response->archive = $relative_archive_path;
            $response->extract_dir = $relative_destination_path;

            return $response;
        }

        $result = unzip_file($archivename, $extractdir); // WP_Error on failure, True on success

        if (!$result || is_wp_error($result)) {
            $response = self::getDebugInfo(__METHOD__, $result, 'unzip_file_return_not_true', array('test_path_writable' => $extractdir));

            $response->archive = $relative_archive_path;
            $response->extract_dir = $relative_destination_path;
        }

        return $response;
    }
}
