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

class PerfectDashboardAPI
{
    private $output = array();
    private $filter;

    public function __construct($secure_key, $task)
    {
        if (empty($secure_key)) {
            $this->output = array(
                'state' => 0,
                'message' => 'no secure_key given',
            );
        } elseif (empty($task)) {
            $this->output = array(
                'state' => 0,
                'message' => 'no task given',
            );
        } elseif ($secure_key == get_option('perfectdashboard-key', null)) {
            // Check if secure key is valid
            require_once dirname(__FILE__).'/perfectdashboard-filterinput-class.php';

            $this->filter = PerfectDashboardFilterInput::getInstance();

            // save ping after success connection to child
            $this->savePing();

            // Check task name and run specific action
            switch ($task) {
                case 'getExtensions':
                    $this->getExtensionsTask();
                    break;
                case 'doUpdate':
                    $this->doUpdateTask();
                    break;
                case 'getUpgradeStatus':
                    $this->getUpgradeStatusTask();
                    break;
                case 'getChecksum':
                    $this->getChecksumTask();
                    break;
                case 'getLatestBackup':
                    $this->getLatestBackupTask();
                    break;
                case 'getLatestBackupName':
                    $this->getLatestBackupNameTask();
                    break;
                case 'beforeCmsUpdate':
                    $this->beforeCmsUpdate();
                    break;
                case 'beforeCmsUpgrade':
                    $this->beforeCmsUpgrade();
                    break;
                case 'afterCmsUpdate':
                    $this->afterCmsUpdate();
                    break;
                case 'afterCmsUpgrade':
                    $this->afterCmsUpgrade();
                    break;
                case 'cmsDisable':
                    $this->cmsDisable();
                    break;
                case 'cmsEnable':
                    $this->cmsEnable();
                    break;
                case 'extensionDisable':
                    $this->extensionDisable();
                    break;
                case 'extensionEnable':
                    $this->extensionEnable();
                    break;
                case 'sysInfo':
                    $this->sysInfo();
                    break;
                case 'checkSysEnv':
                    $this->checkSysEnv();
                    break;
                case 'installBackupTool':
                    $this->installBackupTool();
                    break;
                case 'configureBackupTool':
                    $this->configureBackupTool();
                    break;
                case 'removeLastBackup':
                    $this->removeLastBackupTask();
                    break;
                case 'downloadBackup':
                    $this->downloadBackup();
                    break;
                case 'whiteLabelling':
                    $this->whiteLabelling();
                    break;
                default:
                    $this->output = array(
                        'state' => 0,
                        'message' => 'invalid task name',
                    );
                    break;
            }
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'invalid secure_key',
            );
        }

        // Send response output to Dashboard
        $this->sendOutput();
    }

    private function savePing()
    {
        $date = new DateTime('now');
        update_option('perfectdashboard-ping', $date->format('d-m-Y H:i:s'));
    }

    private function getLatestBackupTask()
    {
        if (isset($_POST['filename']) && $_POST['filename']) {
            $filename = $this->filter->clean($_POST['filename'], 'string');
            $filename = basename($filename);
        }

        if (empty($filename)) {
            $latest_backup = $this->getLatestBackupInfo();

            if (empty($latest_backup)) {
                $this->output = array('state' => 0, 'message' => 'missing backup information');

                return false;
            }

            $lastModFile = $latest_backup->path;
        } else {
            $akeeba_path = $this->getBackupToolPath().'backups/';

            $lastModFile = $akeeba_path.$filename;

            if (!is_file($lastModFile)) {
                $this->output = array(
                    'state' => 0,
                    'message' => 'no file',
                );

                return false;
            }
        }

        if (is_null($lastModFile)) {
            $this->output = array(
                'state' => 0,
                'message' => 'no file',
            );

            return false;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($lastModFile).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: '.filesize($lastModFile));
        readfile($lastModFile);
        exit;
    }

    private function getLatestBackupNameTask()
    {
        $latest_backup = $this->getLatestBackupInfo();

        if (!empty($latest_backup)) {
            $this->output = array('state' => 1, 'id' => $latest_backup->id, 'filename' => $latest_backup->archivename, 'multipart' => $latest_backup->multipart);
        }
    }

    /*
     * Getting information about extensions (name, version, type, slug, state, update state and update version)
     */
    private function getExtensionsTask()
    {
        global $wp_version;

        include_once dirname(__FILE__).'/perfectdashboard-info-class.php';

        $info = new PerfectDashboardInfo();

        $output = array(
            array('type' => 'cms'),
        );

        // getting informations about plugins installed on this wordpress
        if (!function_exists('get_plugins')) {
            require_once ABSPATH.'/wp-admin/includes/plugin.php';
        }
        $output = array_merge($output, get_plugins());

        // getting informations about themess installed on this wordpress
        if (!function_exists('wp_get_themes')) {
            require_once ABSPATH.'/wp-admin/includes/theme.php';
        }
        $output = array_merge($output, wp_get_themes());

        $return = array();

        //loop
        foreach ($output as $slug => $value) {
            if ($value instanceof WP_Theme) {
                $return[] = $info->getThemesInfo($slug, $value);
            } elseif (isset($value['type']) && $value['type'] == 'cms') {
                $item = $info->getCmsInfo();
                array_unshift($return, $item);
            } elseif (isset($value['PluginURI'])) {
                $plugin = $info->getPluginsInfo($slug, $value);

                // Perfect Contact Form PRO
                list($plugin_slug) = explode('/', $slug);
                if ($plugin_slug == 'pwebcontact') {

                    // Get download ID
                    $settings = get_option('pwebcontact_settings', array());
                    if (!empty($settings['dlid'])) {
                        $plugin['update_servers'] = array(
                            array(
                                'url' => 'https://www.perfect-web.co/index.php?option=com_ars&view=update&task=stream&format=json&id=8',
                                'dl_query' => 'dlid='.trim($settings['dlid']),
                            ),
                        );
                    }

                    // Fix name
                    if (version_compare($plugin['version'], '2.1.5', '<') &&
                        strripos($plugin['name'], ' PRO') === false &&
                        file_exists(WP_PLUGIN_DIR.'/'.$plugin_slug.'/uploader.php')) {
                        $plugin['name'] = $plugin['name'].' PRO';
                    }
                }
                $return[] = $plugin;
            }
        }

        $return[] = array(
            'name' => 'Translations',
            'slug' => 'core',
            'type' => 'language',
            'cms' => 'wordpress',
            'version' => $wp_version,
            'enabled' => 1,
            'author' => 'WordPress Team',
            'author_url' => 'https://wordpress.org',
        );

        $this->output = array(
            'result' => (empty($return) ? 0 : $return),
        );
    }

    /*
     * Sending json output to Dashboard
     */
    public function sendOutput()
    {
        header('Content-Type: application/json');
        if (is_array($this->output)) {
            $this->output = array_merge($this->output, array(
                'metadata' => array(
                    'version' => PERFECTDASHBOARD_VERSION,
                ),
            ));
        } elseif (is_object($this->output)) {
            $this->output->metadata = array(
                'version' => PERFECTDASHBOARD_VERSION,
            );
        }
        echo '###'.json_encode($this->output).'###';
        die();
    }

    /*
     * Updating Wordpress and extensions
     */
    private function doUpdateTask()
    {

        // get the type of the element that needs to be updated (wordpress, plugin, theme)
        if (isset($_POST['type']) && $_POST['type']) {
            $type = $this->filter->clean($_POST['type'], 'cmd');
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no type',
            );

            return false;
        }

        // get the slug name of plugin or theme
        if ($type != 'cms') {
            if (isset($_POST['slug']) && $_POST['slug']) {
                $slug = $this->filter->clean($_POST['slug'], 'string');
            } else {
                $this->output = array(
                    'state' => 0,
                    'message' => 'no slug',
                );

                return false;
            }
        }

        // get the action to run (download, unpack, update)
        if (isset($_POST['action']) && $_POST['action']) {
            $action = $this->filter->clean($_POST['action'], 'cmd');
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no action',
            );

            return false;
        }

        // get the url of package to download (optional)
        if (isset($_POST['file']) && $_POST['file']) {
            $file = $this->filter->clean($_POST['file'], 'ba'.'se'.'64');
            $file = call_user_func('ba'.'se'.'64'.'_decode', $file);
        }

        // get the encoded serialized respons from previous action (only in unpack and update)
        $return = null;
        if (isset($_POST['return']) && $_POST['return']) {
            $return = $this->filter->clean($_POST['return'], 'ba'.'se'.'64');
            $return = json_decode(call_user_func('ba'.'se'.'64'.'_decode', $return));
        }

        // including the necessary methods
        include_once ABSPATH.'wp-admin/includes/file.php';
        include_once ABSPATH.'wp-admin/includes/plugin.php';
        include_once dirname(__FILE__).'/perfectdashboard-upgrade-class.php';

        $upgrade = new PerfectDashboardUpgrade($type);

        // call specific action and set output message
        switch ($action) {
            case 'download':
                $download_package = $upgrade->downloadPackage($slug, $file);

                if ($download_package['state']) {
                    $this->output = array(
                        'state' => 1,
                        'message' => 'success',
                        'return' => call_user_func('ba'.'se'.'64'.'_encode', json_encode($download_package)),
                    );
                } else {
                    $this->output = $download_package;
                }
                break;
            case 'unpack':
                $unpack_package = $upgrade->unpackPackage($return);

                if ($unpack_package['state']) {
                    $this->output = array(
                        'state' => 1,
                        'message' => 'success',
                        'return' => call_user_func('ba'.'se'.'64'.'_encode', json_encode($unpack_package)),
                    );
                } else {
                    $this->output = $unpack_package;
                }
                break;
            case 'update':
                if ($type == 'cms') {
                    $update = $upgrade->updateWordpress($return);
                } elseif (!empty($return)) {
                    $update = $upgrade->installPackage($slug, $return);
                } elseif (in_array($type, array('language', 'plugin', 'theme'))) {
                    $update = $upgrade->updateExtension($type, $slug);
                } else {
                    $this->output = array(
                        'state' => 0,
                        'message' => 'nothing to update',
                    );

                    return false;
                }

                if ($update['state']) {
                    $this->output = array(
                        'state' => 1,
                        'message' => 'success',
                    );

                    return true;
                } else {
                    $this->output = $update;

                    return false;
                }
                break;
            default:

        }
    }

    /*
     * Getting array of compatibility of all plugins for given WordPress versions
     */
    private function getUpgradeStatusTask()
    {

        // get an array with WordPress versions to check
        if (isset($_POST['versions']) && $_POST['versions']) {
            $versions = $this->filter->clean($_POST['versions'], 'array');
        } else {
            $this->output = array(
                'state' => 0,
                'message' => 'no versions parameter',
            );

            return false;
        }

        if (!is_array($versions)) {
            $this->output = array(
                'state' => 0,
                'message' => 'versions is not an array',
            );

            return false;
        }

        include_once ABSPATH.'wp-admin/includes/plugin.php';
        include_once dirname(__FILE__).'/perfectdashboard-info-class.php';

        $plugins = get_plugins();
        $info = new PerfectDashboardInfo();

        $data = array();

        // check every plugin to compare compatibility with given Wordpress versions
        foreach ($plugins as $slug => $plugin) {
            $item = array(
                'name' => $plugin['Name'],
                'slug' => $slug,
                'cms' => array(),
            );

            // check if plugin is on Wordpress repo
            $plugin_info = $info->checkPluginUpdate(dirname($slug));
            $cms_versions = array();

            // define false
            $installed_compatibility = false;

            // check if
            $same_plugin = ($plugin_info !== false and version_compare($plugin['Version'], $plugin_info->version, '='));

            // if not same grab info about older version installed on wordpress
            if ($same_plugin === false) {

                // if plugin got readme file parse it
                if (file_exists(dirname(WP_PLUGIN_DIR.'/'.$slug).'/readme.txt')) {
                    $installed_plugin_readme = file_get_contents(dirname(WP_PLUGIN_DIR.'/'.$slug).'/readme.txt');
                    preg_match('/(?:Requires\sat\sleast\:\s)(.*)(?:\s+)(?:Tested\sup\sto\:\s)(.*)/i',
                        $installed_plugin_readme, $installed_compatibility);
                } else {
                    $installed_compatibility = false;
                }
            }

            // check all of the given Wordpress versions
            foreach ($versions as $version) {
                $is_available = ($plugin_info !== false and $info->isAvailableForWordpressVersion($plugin_info->requires,
                        $plugin_info->tested,
                        $version));

                //if is available
                if ($is_available === true and $same_plugin === true) {
                    $cms_versions[$version] = 1; // we got available without update
                } elseif ($is_available === true and $same_plugin === false) {
                    $cms_versions[$version] = 3; // we got available with update
                } elseif ($is_available === false and $same_plugin === true) {
                    $cms_versions[$version] = 2; // not available
                } elseif ($is_available === false and $same_plugin === false) {
                    if ($installed_compatibility) {
                        $is_available = $info->isAvailableForWordpressVersion($installed_compatibility[1],
                            $installed_compatibility[2],
                            $version);

                        if ($is_available) {
                            $cms_versions[$version] = 1; // available
                        } else {
                            $cms_versions[$version] = 2; // not available
                        }
                    } else {
                        $cms_versions[$version] = 0; // not available
                    }
                }
            }

            $item['cms'] = $cms_versions;
            $item['old'] = $installed_compatibility;
            $item['new'] = $same_plugin ? $plugin['Version'] : $plugin_info->version;
            $data[] = $item;
        }

        // put array in output to response
        $this->output = $data;
    }

    /*
     * Getting array of files and their md5_file checksum
     */
    private function getChecksumTask()
    {
        include_once dirname(__FILE__).'/perfectdashboard-test-class.php';

        $test = new PerfectDashboardTest();
        $file_list_checksum = $test->getFilesChecksum(ABSPATH);

        if (is_array($file_list_checksum) && count($file_list_checksum) > 0) {
            $this->output = array(
                'state' => 1,
                'file_list' => call_user_func('ba'.'se'.'64'.'_encode', json_encode($file_list_checksum)),
            );
        } else {
            $this->output = array(
                'state' => 0,
            );
        }
    }

    private function beforeCmsUpdate()
    {
        $this->output = array('state' => 1);
    }

    private function beforeCmsUpgrade()
    {
        $this->output = array('state' => 1);
    }

    private function afterCmsUpdate()
    {
        $this->output = array('state' => 1);
    }

    private function afterCmsUpgrade()
    {
        $this->output = array('state' => 1);
    }

    private function cmsDisable()
    {
        update_option('perfectdashboard-site-offline', 1);

        $this->output = array('state' => 1);
    }

    private function cmsEnable()
    {
        update_option('perfectdashboard-site-offline', 0);

        $this->output = array('state' => 1);
    }

    private function extensionDisable()
    {
        if (isset($_POST['extensions'])) {
            $extensions = $this->filter->clean($_POST['extensions'], 'array');
        }

        require_once ABSPATH.'/wp-admin/includes/plugin.php';

        $plugins = array();
        if ($extensions) {
            // If extensions are set, then deactivate only given extensions.
            foreach ($extensions as $ext) {
                $plugins[] = $ext['slug'];
            }
        } else {
            // If extensions aren't set, then deactivate all extensions except perfectdashboard.
            $extensions = get_plugins();

            foreach ($extensions as $ext_slug => $ext_data) {
                if (strpos($ext_slug, '/perfectdashboard.php') === false) {
                    $plugins[] = $ext_slug;
                }
            }
        }

        deactivate_plugins($plugins, true);

        $this->output = array('state' => 1);
    }

    private function extensionEnable()
    {
        if (isset($_POST['extensions'])) {
            $extensions = $this->filter->clean($_POST['extensions'], 'array');
        }

        require_once ABSPATH.'/wp-admin/includes/plugin.php';

        $plugins = array();
        if ($extensions) {
            // If extensions are set, then activate only given extensions.
            foreach ($extensions as $ext) {
                $plugins[] = $ext['slug'];
            }
        } else {
            // If extensions aren't set, then activate all extensions except perfectdashboard.
            $extensions = get_plugins();

            foreach ($extensions as $ext_slug => $ext_data) {
                if (strpos($ext_slug, '/perfectdashboard.php') === false) {
                    $plugins[] = $ext_slug;
                }
            }
        }

        $result = activate_plugins($plugins);

        if ($result === true) {
            $this->output = array('state' => 1);
        } else {
            $errors = array();
            foreach ($result->get_error_data() as $error) {
                $errors[] = $error->get_error_message();
            }
            $this->output = array('state' => 0, 'error_code' => 0, 'debug' => implode(', ', $errors));
        }
    }

    private function sysInfo()
    {
        global $wpdb, $wp_version;
        $security_audit = (isset($_POST['security_audit']) ? $this->filter->clean($_POST['security_audit'], 'int') : null);

        $additional_data = array();
        $database_name = null;
        $database_version = null;
        if ($security_audit) {
            // Check if username admin exists.
            $admin_user = $wpdb->get_var('SELECT COUNT(*) FROM '.$wpdb->users.' WHERE user_login="admin"');

            // Check if somebody use one of popular passwords.
            $popular_passwords = array('123456', 'password', '12345678', 'qwerty', '12345', '123456789', 'football',
                '1234', '1234567', 'baseball', 'welcome', '1234567890', 'abc123', '111111', '1qaz2wsx', 'dragon',
                'master', 'monkey', 'letmein', 'login', 'princess', 'qwertyuiop', 'solo', 'passw0rd', 'starwars', );

            $users_passwords = $wpdb->get_col('SELECT user_pass FROM '.$wpdb->users);

            if (!empty($users_passwords)) {
                foreach ($users_passwords as $user_password) {
                    foreach ($popular_passwords as $popular_password) {
                        if (wp_check_password($popular_password, $user_password) === true) {
                            $popular_password_exist = true;
                            break 2;
                        }
                    }
                }
            }

            // Check if any popular backup tool is installed, if yes then check if it's backup folder is accessible through http.
            $timeout = 3;
            $backups_http_accessible = array();
            $site_url = site_url();

            require_once dirname(__FILE__).'/perfectdashboard-info-class.php';

            // Check AKEEBA
            $backups_http_accessible = PerfectDashboardInfo::checkBackupsHttpAccessAkeeba($site_url, $timeout);

            // Check Duplicator
            $backups_http_accessible = array_merge($backups_http_accessible, PerfectDashboardInfo::checkBackupsHttpAccessDuplicator($site_url, $timeout));

            // Check UpdraftPlus
            $backups_http_accessible = array_merge($backups_http_accessible, PerfectDashboardInfo::checkBackupsHttpAccessUpdraftPlus($site_url, $timeout));

            $backups_http_accessible = array_merge($backups_http_accessible, PerfectDashboardInfo::checkBackupsHttpAccessBackUpWordPress($site_url, $timeout));

            // Check Xcloner
            $backups_http_accessible = array_merge($backups_http_accessible, PerfectDashboardInfo::checkBackupsHttpAccessXCloner($site_url, $timeout));

            // Check WP-DB-Backup
            // Don't check WP-DB-Backup - backups are downloaded to user's computer or send to an email in this plugin.

            // Check VaultPress
            // Don't check VaultPress - they're storing backups on remote servers.

            // At last - check Perfect Dashboard backups folder
            $backups_http_accessible = array_merge($backups_http_accessible, PerfectDashboardInfo::checkBackupsHttpAccessPerfectDashboard($site_url, $this->getBackupToolPath(), $timeout));

            $debug_mode = 1;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $debug_mode = 0;
            }
            if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
                $debug_mode = 0;
            }

            $additional_data = array(
                'error_reporting' => $this->checkErrorReporting(),
                'expose_php' => ini_get('expose_php') ? 0 : 1,
                'allow_url_include' => ini_get('allow_url_include') ? 0 : 1,
                'database_prefix' => (int) ($wpdb->prefix != 'wp_'),
                'database_user' => (int) ($wpdb->dbuser != 'root'),
                'debug_mode' => (int) $debug_mode,
                'readme_file' => (int) (!(file_exists(ABSPATH.'/readme.txt') || file_exists(ABSPATH.'/README.txt')
                    || file_exists(ABSPATH.'/readme.html') || file_exists(ABSPATH.'/README.html')
                    || file_exists(ABSPATH.'/license.txt'))),
                'admin_user' => (int) empty($admin_user),
                'popular_password' => (int) empty($popular_password_exist),
                'backups_http_accessible' => $backups_http_accessible,
            );
        }

        $database_version_info = $wpdb->get_var('SELECT version()');

        if ($database_version_info !== null) {
            $database_name = strpos(strtolower($database_version_info), 'mariadb') !== false ? 'MariaDB' : ($wpdb->is_mysql ? 'MySQL' : '');
        } else {
            $database_name = $wpdb->is_mysql ? 'MySQL' : '';
        }

        $database_version = $wpdb->db_version();
        if ($database_name == 'MariaDB' && $database_version_info) {
            $version = explode('-', $database_version_info);
            $database_version = empty($version) !== true ? $version[0] : $wpdb->db_version();
        }

        $this->output = array(
            'state' => 1,
            'cms_type' => 'wordpress',
            'cms_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'os' => php_uname('s'),
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '',
            'database_name' => $database_name,
            'database_version' => $database_version,
            'additional_data' => $additional_data,
        );
    }

    /**
     * Check error_reporting.
     */
    private function checkErrorReporting()
    {
        $configDebug = defined('WP_DEBUG');
        $configDebugDisplay = defined('WP_DEBUG_DISPLAY');

        // When WP_DEBUG is defined
        if ($configDebug || $configDebugDisplay) {
            if ($configDebug) {
                return WP_DEBUG ? '0' : '1';
            }

            if ($configDebugDisplay) {
                return WP_DEBUG_DISPLAY ? '0' : '1';
            }
        } else {
            $statusInPHP = ini_get('error_reporting');
            $statusInPHPdisplay = ini_get('display_errors');

            if ($statusInPHPdisplay == 0) {
                return '1';
            } elseif ($statusInPHPdisplay > 0 && $statusInPHP == 0) {
                return '1';
            }

            return '0';
        }

        return '1';
    }

    private function checkSysEnv()
    {
        // Get request data.
        $php_ver_min = (isset($_POST['php_ver_min']) ? $this->filter->clean($_POST['php_ver_min'], 'cmd') : '');
        $php_ver_max = (isset($_POST['php_ver_max']) ? $this->filter->clean($_POST['php_ver_max'], 'cmd') : '');

        if (($php_ver_min && version_compare(PHP_VERSION, $php_ver_min) == -1) ||
            ($php_ver_max && version_compare(PHP_VERSION, $php_ver_max) == 1)) {
            $this->output = array(
                'state' => 0,
                'message' => sprintf('Server PHP version %s. Requies PHP version grater than %s and less than %s',
                    PHP_VERSION, $php_ver_min, $php_ver_max), );
        } else {
            $this->output = array('state' => 1);
        }
    }

    private function installBackupTool()
    {
        $download_url = isset($_POST['download_url']) ? $this->filter->clean($_POST['download_url'], 'ba'.'se'.'64') : null;
        $install_dir = isset($_POST['install_dir']) ? $this->filter->clean($_POST['install_dir'], 'cmd') : null;
        $login = isset($_POST['login']) ? $this->filter->clean($_POST['login'], 'alnum') : null;
        $password = isset($_POST['password']) ? $this->filter->clean($_POST['password'], 'alnum') : null;
        $secret = isset($_POST['secret']) ? $this->filter->clean($_POST['secret'], 'alnum') : null;
        $version = isset($_POST['version']) ? $this->filter->clean($_POST['version'], 'cmd') : null;
        $htaccess = isset($_POST['htaccess']) ? $this->filter->clean($_POST['htaccess'], 'cmd') : null;
        $update = false;

        if (empty($download_url) || empty($install_dir) || empty($login) || empty($password) || empty($secret)) {
            $this->output = array('state' => 0, 'message' => 'missing_data');

            return false;
        }

        // Check if backup tool is already installed.
        $backup_dir = get_option('perfectdashboard-backup_dir', false);

        if (!empty($backup_dir) && file_exists(ABSPATH.$backup_dir.'/index.php')) {
            if ($install_dir != $backup_dir) {
                $prefix_db = $this->getAkeebaDbPrefix($backup_dir);
                $this->changeBackupAcces($login, $password, $secret, $prefix_db, $htaccess, $install_dir, $backup_dir);
            }

            if (!empty($version) && file_exists(ABSPATH.$backup_dir.'/version.php')) {
                include_once ABSPATH.$backup_dir.'/version.php';
                if (defined('AKEEBA_VERSION') && version_compare(AKEEBA_VERSION, $version) < 1) {
                    $update = true;
                } elseif (defined('AKEEBABACKUP_VERSION') && version_compare(AKEEBABACKUP_VERSION, $version) < 1) {
                    $update = true;
                }
            }

            if (!$update) {
                $this->output = array('state' => 1, 'message' => 'installed');

                return true;
            }
        }

        // Check if backup tool is already installed - for childs installed before version 1.1
        $params = get_option('perfectdashboard_akeeba_access', false);

        if (!empty($params['ak_prefix_folder']) &&
            file_exists(ABSPATH.$params['ak_prefix_folder'].'perfectdashboard_akeeba'.'/index.php')) {
            $backup_dir = $params['ak_prefix_folder'].'perfectdashboard_akeeba';
            if ($install_dir != $backup_dir) {
                $prefix_db = $params['ak_prefix_db'];
                $this->changeBackupAcces($login, $password, $secret, $prefix_db, $htaccess, $install_dir, $backup_dir);
            }

            if (!empty($version) && file_exists(ABSPATH.$backup_dir.'/version.php')) {
                include_once ABSPATH.$backup_dir.'/version.php';
                if (defined('AKEEBA_VERSION') && version_compare(AKEEBA_VERSION, $version) === -1) {
                    $update = true;
                } elseif (defined('AKEEBABACKUP_VERSION') && version_compare(AKEEBABACKUP_VERSION, $version) === -1) {
                    $update = true;
                }
            }

            if (!$update) {
                $this->output = array('state' => 1, 'message' => 'installed');

                return true;
            }
        }

        $download_url = call_user_func('ba'.'se'.'64'.'_decode', $download_url);

        include_once ABSPATH.'wp-admin/includes/file.php';

        //Download the package
        $download_file = download_url($download_url);

        if (is_wp_error($download_file)) {
            $this->output = array('success' => 0, 'message' => 'download_error', 'debug' => PerfectDashboard::getDebugInfo(__METHOD__, $download_file));

            return false;
        }

        $prefix_db = 'as'.substr(md5(uniqid('akeeba_tables')), 0, 5).'_'.'perfectdashboard_akeeba_';

        $akeeba_path = ABSPATH.$install_dir.'/';
        $akeeba_package_path = $download_file;

        if (!file_exists($akeeba_path)) {
            mkdir($akeeba_path);
        }

        WP_Filesystem();
        // Unzip package to working directory
        $extracted = PerfectDashboard::extract($akeeba_package_path, $akeeba_path);

        if ($extracted === true) {
            if ($update) {
                $this->initAkeeba($install_dir, null, null, $secret, null, $htaccess);
            } else {
                $this->initAkeeba($install_dir, $login, $password, $secret, $prefix_db, $htaccess);
            }
        } else {
            $this->output = array('state' => 0, 'message' => 'unpack_error', 'debug' => $extracted);

            return false;
        }

        if (file_exists($download_file)) {
            unlink($download_file);
        }

        $this->output = array('state' => 1, 'message' => $update ? 'updated' : 'installed');
    }

    public function configureBackupTool()
    {
        $htaccess_param = isset($_POST['htaccess']) ? $this->filter->clean($_POST['htaccess'], 'string') : null;
        $part_size = isset($_POST['part_size']) ? $this->filter->clean($_POST['part_size'], 'int') : null;

        $install_dir = $this->getBackupToolPath(true);

        if (empty($install_dir)) {
            $this->output = array('state' => 0, 'message' => 'missing install_dir');

            return false;
        }

        if ($this->initAkeeba($install_dir, null, null, null, null, $htaccess_param, $part_size)) {
            $this->output = array('state' => 1, 'message' => 'configured');
        }
    }

    private function getDatabaseConfig()
    {
        global $wpdb;

        $config = array();
        $config['db_name'] = DB_NAME;
        $config['db_user'] = DB_USER;
        $config['db_password'] = DB_PASSWORD;
        $config['db_host'] = DB_HOST;
        $config['db_prefix'] = $wpdb->prefix;
        $config['driver'] = class_exists('mysqli') ? 'mysqli' : 'mysql';

        return $config;
    }

    protected function getBackupToolPath($dir_only = false)
    {
        $backup_dir = get_option('perfectdashboard-backup_dir', false);

        if (empty($backup_dir)) {
            $old_params = get_option('perfectdashboard_akeeba_access', false);

            if ($old_params) {
                $old_params = unserialize(call_user_func('ba'.'se'.'64'.'_decode', $old_params));
                $backup_dir = $old_params['ak_prefix_folder'].'perfectdashboard_akeeba';

                update_option('perfectdashboard-backup_dir', $backup_dir);
            }
        }

        if ($backup_dir) {
            return $dir_only ? $backup_dir : ABSPATH.$backup_dir.'/';
        }
    }

    private function removeLastBackupTask()
    {
        $last_backup_info = $this->getLatestBackupInfo();
        $deleted_all = true;

        if (empty($last_backup_info)) {
            return false;
        }

        if (!unlink($last_backup_info->path)) {
            $deleted_all = false;
        }

        if ($last_backup_info->multipart) {
            if (isset($_POST['akeeba_dir'])) {
                $akeeba_dir = $this->filter->clean($_POST['akeeba_dir'], 'string');
            }

            if (empty($akeeba_dir)) {
                $this->output = array('state' => 0, 'message' => 'no akeeba_dir parameter');

                return false;
            }

            $backups_path = ABSPATH.'/'.$akeeba_dir.'/backups/';

            $basename = pathinfo($last_backup_info->archivename, PATHINFO_FILENAME);
            $files = $this->getFilesFromPath($backups_path, '^'.$basename.'.*');

            if (is_array($files)) {
                foreach ($files as $file) {
                    if (!unlink($backups_path.$file)) {
                        $deleted_all = false;
                    }
                }
            } else {
                $this->output = array('state' => 0,  'message' => 'file not exists');

                return false;
            }
        }

        if ($deleted_all) {
            $this->output = array('state' => 1);

            return false;
        } else {
            $this->output = array('state' => 0, 'message' => 'can not delete file');

            return false;
        }
    }

    public function getFilesFromPath($path, $filter = null)
    {
        $files = array();

        if (!($handle = @opendir($path))) {
            return;
        }

        while (($file = readdir($handle)) !== false) {
            $is_dir = is_dir($path.'/'.$file);

            if ($file != '.' && $file != '..' && !$is_dir) {
                if ($filter) {
                    if (preg_match("/$filter/", $file)) {
                        $files[] = $file;
                    }
                } else {
                    $files[] = $file;
                }
            }
        }

        closedir($handle);

        return $files;
    }

    protected function changeBackupAcces($login, $password, $secret, $prefix_db, $htaccess, $dest, $src = null)
    {
        if (empty($src)) {
            $src = get_option('perfectdashboard-backup_dir', false);
        }

        $src_path = ABSPATH.$src.'/';
        $dest_path = ABSPATH.$dest.'/';

        if ($src_path == $dest_path or @rename($src_path, $dest_path) === true) {
            $this->initAkeeba($dest, $login, $password, $secret, $prefix_db, $htaccess);
            $this->output = array('state' => 1, 'message' => 'installed');

            return true;
        } else {
            $this->output = array('state' => 0, 'message' => 'moving folder erro');

            return false;
        }
    }

    protected function initAkeeba($backup_dir, $login = null, $password = null, $secret = null, $prefix_db = null, $htaccess_param = null, $part_size = null)
    {
        $akeeba_path = ABSPATH.$backup_dir.'/';

        if (false === (include $akeeba_path.'Awf/Autoloader/Autoloader.php')) {
            $this->output = array('state' => 0, 'message' => 'include_autoloader_error');

            return false;
        }

        if (!defined('APATH_BASE')) {
            if (false === (include $akeeba_path.'defines.php')) {
                $this->output = array('state' => 0, 'message' => 'include_defines_error');

                return false;
            }
        }

        $prefixes = Awf\Autoloader\Autoloader::getInstance()->getPrefixes();
        if (!array_key_exists('Solo\\', $prefixes)) {
            Awf\Autoloader\Autoloader::getInstance()->addMap('Solo\\', APATH_BASE.'/Solo');
        }

        if (!defined('AKEEBAENGINE')) {
            define('AKEEBAENGINE', 1);

            if (false == include $akeeba_path.'Solo/engine/Factory.php') {
                $this->output = array('state' => 0, 'message' => 'include_engine_factory_error');

                return false;
            }

            if (file_exists($akeeba_path.'Solo/alice/factory.php')) {
                if (false == include $akeeba_path.'Solo/alice/factory.php') {
                    $this->output = array('state' => 0, 'message' => 'include_alice_factory_error');

                    return false;
                }
            }

            Akeeba\Engine\Platform::addPlatform('Solo',  $akeeba_path.'Solo/Platform/Solo');
            Akeeba\Engine\Platform::getInstance()->load_version_defines();
            Akeeba\Engine\Platform::getInstance()->apply_quirk_definitions();
        }

        try {
            // Create the container if it doesn't already exist
            if (!isset($application)) {
                $application = Awf\Application\Application::getInstance('Solo');
            }
            // Initialise the application
            $application->initialise();

            $container = $application->getContainer();
            $model_setup = new Solo\Model\Setup();

            $db_config = $this->getDatabaseConfig();

            $session = $container->segment;

            $session->set('db_driver', $db_config['driver']);
            $session->set('db_host', $db_config['db_host']);
            $session->set('db_user', $db_config['db_user']);
            $session->set('db_pass', $db_config['db_password']);
            $session->set('db_name', $db_config['db_name']);
            $session->set('db_prefix', $prefix_db ? $prefix_db : $this->getAkeebaDbPrefix($backup_dir));

            $model_setup->applyDatabaseParameters();

            if ($prefix_db !== null) {
                $model_setup->installDatabase();
            }

            $live_site = get_site_url().'/'.$backup_dir;

            $session->set('setup_timezone', 'UTC');
            $session->set('setup_live_site',  $live_site);
            $session->set('setup_session_timeout', 1440);

            if ($login && $password) {
                $session->set('setup_user_username', $login);
                $session->set('setup_user_password', $password);
                $session->set('setup_user_password2', $password);
                $session->set('setup_user_email', 'dashboard@perfectdashboard.co');
                $session->set('setup_user_name', 'Perfect Dashboard');
            }

            // Apply configuration settings to app config
            $model_setup->setSetupParameters();

            // Try to create the new admin user and log them in
            if ($login && $password) {
                $model_setup->createAdminUser();
            }

            // Set akeeba system configuration
            if ($secret) {
                $container->appConfig->set('options.frontend_enable', true);
                $container->appConfig->set('options.frontend_secret_word', $secret);
            } else {
                $secret = $container->appConfig->get('options.frontend_secret_word', null);
            }
            $container->appConfig->set('stats_enabled', 0);
            $container->appConfig->set('useencryption', 1);
            $container->appConfig->set('options.frontend_email_on_finish', false);
            $container->appConfig->set('options.displayphpwarning', false);
            $container->appConfig->set('options.siteurl', $live_site.'/');
            $container->appConfig->set('options.confwiz_upgrade', 0);
            $container->appConfig->set('mail.online', false);

            $container->appConfig->saveConfiguration();
            //Generate the secret key if needed
            $main_model = new Solo\Model\Main();
            $main_model->checkEngineSettingsEncryption();

            // Configuration Wizard
            $siteParams = array();
            $siteParams['akeeba.basic.output_directory'] = '[DEFAULT_OUTPUT]';
            $siteParams['akeeba.basic.log_level'] = 1;
            $siteParams['akeeba.platform.site_url'] = get_home_url();
            $siteParams['akeeba.platform.newroot'] = ABSPATH;
            $siteParams['akeeba.platform.dbdriver'] = $db_config['driver'];
            $siteParams['akeeba.platform.dbhost'] = $db_config['db_host'];
            $siteParams['akeeba.platform.dbusername'] = $db_config['db_user'];
            $siteParams['akeeba.platform.dbpassword'] = $db_config['db_password'];
            $siteParams['akeeba.platform.dbname'] = $db_config['db_name'];
            $siteParams['akeeba.platform.dbprefix'] = $db_config['db_prefix'];
            $siteParams['akeeba.platform.override_root'] = 1;
            $siteParams['akeeba.platform.override_db'] = 1;
            $siteParams['akeeba.platform.addsolo'] = 0;
            $siteParams['akeeba.platform.scripttype'] = 'wordpres';
            $siteParams['akeeba.advanced.embedded_installer'] = 'angie-wordpress';
            $siteParams['akeeba.advanced.virtual_folder'] = 'external_files';
            $siteParams['akeeba.advanced.uploadkickstart'] = 0;
            $siteParams['akeeba.quota.enable_count_quota'] = 0;
            if (isset($part_size) && $part_size) {
                $siteParams['engine.archiver.common.part_size'] = $part_size;
            } else {
                $siteParams['engine.archiver.common.part_size'] = '104857600';
            }

            $config = Akeeba\Engine\Factory::getConfiguration();

            $protectedKeys = $config->getProtectedKeys();
            $config->setProtectedKeys(array());

            foreach ($siteParams as $k => $v) {
                $config->set($k, $v);
            }

            Akeeba\Engine\Platform::getInstance()->save_configuration();

            $config->setProtectedKeys($protectedKeys);
            // End Configuration Wizard.

            update_option('perfectdashboard-backup_dir', $backup_dir);
        } catch (Exception $ex) {
            $this->output = array('state' => 0, 'message' => 'install_error',
                'debug' => PerfectDashboard::getDebugInfo(__METHOD__, null, 'backup_install_exception', null, $ex), );

            return false;
        }

        $this->setWAFExceptionsForBackupTool($htaccess_param);

        return true;
    }

    protected function getAkeebaDbPrefix($backup_dir)
    {
        $backup_path = ABSPATH.'/'.basename($backup_dir);

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
                        return $prefix_db;
                    }
                }
            }
        }

        $this->output = array('state' => 0, 'message' => 'backup db prefix error');

        return false;
    }

    /**
     * Get info about latest backup filename, path and multipart count.
     *
     * @return type
     */
    protected function getLatestBackupInfo()
    {
        global $wpdb;

        $backup_path = $this->getBackupToolPath();
        $backup_dir = basename($backup_path);
        $prefix_db = $this->getAkeebaDbPrefix($backup_dir);

        $sql = 'SELECT `id`, `archivename`, `multipart` FROM `'.$prefix_db.'ak_stats` ORDER BY `id` DESC';
        $backups = $wpdb->get_results($sql);

        if (empty($backups)) {
            $this->output = array('state' => 0, 'message' => 'no backups');

            return;
        }

        $result = new stdClass();

        foreach ($backups as $backup) {
            if (!isset($backup->id) || !isset($backup->archivename) || !isset($backup->multipart)) {
                continue;
            }

            $file = basename($backup->archivename);
            $path = $backup_path.'backups/'.$file;

            if (file_exists($path)) {
                $result = $backup;
                $result->archivename = $file;

                if ($result->multipart == 1) {
                    $result->multipart = 0;
                }

                $result->path = $path;

                break;
            }
        }

        if (!isset($result->id)) {
            $this->output = array('state' => 0, 'message' => 'missing backup information');

            return null;
        }

        return $result;
    }

    /**
     * Set exceptions for access backup tool directories.
     */
    private function setWAFExceptionsForBackupTool($htaccess_param = null)
    {
        // Flush htaccess rules to add also rule for backup tool directory.
        // (Rule is set directly in perfectdashboard-class.php - add_filter( 'mod_rewrite_rules', array($this, 'setHtaccessRules') ); ).
    global $wp_rewrite;
        $wp_rewrite->flush_rules();

        $backup_tool_path = $this->getBackupToolPath();

        if ($htaccess_param == 'disable') {
            $backup_tool_htaccess_file = $backup_tool_path.'.htaccess';

            if (is_file($backup_tool_htaccess_file)) {
                @rename($backup_tool_htaccess_file, $backup_tool_path.'.htaccess.disable');
            }
        } else {
            // For apache servers - set htaccess.txt in backup tool dir to .htaccess
            $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : false;
            $backup_tool_htaccess_file = $backup_tool_path.'htaccess.txt';
            $backup_tool_htaccess_disabled_file = $backup_tool_path.'.htaccess.disable';

            if ($server_software && strpos(strtolower($server_software), 'apache') !== false && is_file($backup_tool_htaccess_file) && !is_file($backup_tool_htaccess_disabled_file)) {
                @rename($backup_tool_htaccess_file, $backup_tool_path.'.htaccess');
            }
        }
    }

    public function downloadBackup()
    {
        $backup_url = $this->filter->clean($_POST['backup_url'], 'ba'.'se'.'64');
        $backup_filename = $this->filter->clean($_POST['backup_filename'], 'ba'.'se'.'64');

        if (empty($backup_url)) {
            $this->output = array('state' => 0, 'message' => 'no backup url');

            return false;
        }
        if (empty($backup_filename)) {
            $this->output = array('state' => 0, 'message' => 'no backup file name');

            return false;
        }

        set_time_limit(0);
        ini_set('memory_limit', '2000M');

        $backup_url = call_user_func('ba'.'se'.'64'.'_decode', $backup_url);
        $backup_filename = call_user_func('ba'.'se'.'64'.'_decode', $backup_filename);

        $backup_tool_path = $this->getBackupToolPath();

        //Build the local path
        $path = $backup_tool_path.'backups/'.$backup_filename;
        $data = @file_get_contents($backup_url);

        if ($data === false) {
            $this->output = array('state' => 0, 'message' => 'could not get content for file '.$backup_filename);

            return false;
        }

        $file = fopen($path, 'w+');
        fputs($file, $data);
        fclose($file);

        $this->output = array('state' => 1, 'message' => 'downloaded to '.$path);

        return true;
    }

    public function whiteLabelling()
    {
        $name = $this->filter->clean($_POST['name'], 'ba'.'se'.'64');
        $extensions_view_information = $this->filter->clean($_POST['extensions_view_information'], 'ba'.'se'.'64');
        $login_page_information = $this->filter->clean($_POST['login_page_information'], 'ba'.'se'.'64');

        if (empty($name)) {
            delete_option('perfectdashboard-name');
        } else {
            $name = call_user_func('ba'.'se'.'64'.'_decode', $name);
            update_option('perfectdashboard-name', $name);
        }

        if (empty($extensions_view_information)) {
            delete_option('perfectdashboard-extensions-view-information');
        } else {
            $extensions_view_information = call_user_func('ba'.'se'.'64'.'_decode', $extensions_view_information);
            update_option('perfectdashboard-extensions-view-information', $extensions_view_information);
        }

        if (empty($login_page_information)) {
            delete_option('perfectdashboard-login-page-information');
        } else {
            $login_page_information = call_user_func('ba'.'se'.'64'.'_decode', $login_page_information);
            update_option('perfectdashboard-login-page-information', $login_page_information);
        }

        $this->output = array('state' => 1, 'message' => 'white labelling complete');

        return true;
    }
}
