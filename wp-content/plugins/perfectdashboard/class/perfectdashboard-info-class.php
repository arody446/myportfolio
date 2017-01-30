<?php
/**
 * @version 1.2.0
 *
 * @copyright © 2015 Perfect Web sp. z o.o., All rights reserved. http://www.perfect-web.co
 * @license GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author Perfect Dashboard
 */

// No direct access
function_exists('add_action') or die;

class PerfectDashboardInfo
{
    /*
     * Getting information about current WordPress instance (name, type, version, update state and update version)
     */
    public function getCmsInfo($skip_updates = 0)
    {
        $cms = array(
            'name' => 'WordPress',
            'type' => 'cms',
            'slug' => 'wordpress',
            'version' => get_bloginfo('version'),
            'enabled' => 1,
            'author' => 'WordPress Team',
            'author_url' => 'https://wordpress.org',
        );

        return $cms;
    }

    /*
     * Getting information about plugins in this Wordpress (name, type, slug, version, state, update state and update version)
     */
    public function getPluginsInfo($slug_plugin, $array_plugin)
    {
        $item = array(
            'name' => utf8_encode(trim(html_entity_decode($array_plugin['Name']))),
            'type' => 'plugin',
            'slug' => $slug_plugin,
            'version' => strtolower(trim(html_entity_decode($array_plugin['Version']))),
            'update_servers' => '',
        );

        // Get author name.
        if (isset($array_plugin['Author'])) {
            $item['author'] = utf8_encode(trim(html_entity_decode($array_plugin['Author'])));
        } elseif (isset($array_plugin['AuthorName'])) {
            $item['author'] = utf8_encode(trim(html_entity_decode($array_plugin['AuthorName'])));
        } else {
            $item['author'] = null;
        }

        // get author url
        if (isset($array_plugin['AuthorURI'])) {
            $item['author_url'] = utf8_encode(trim(html_entity_decode($array_plugin['AuthorURI'])));
        } else {
            $item['author_url'] = null;
        }

        // check if plugin is activated
        if (is_plugin_active($slug_plugin)) {
            $item['enabled'] = 1;
        } else {
            $item['enabled'] = 0;
        }

        return $item;
    }

    /*
     * Getting information about themes in this Wordpress (name, type, slug, version, state, update state and update version)
     */
    public function getThemesInfo($slug_theme, $object_theme)
    {

        // build array with themes data to Dashboard
        $item = array(
            'name' => utf8_encode(trim(html_entity_decode($object_theme->get('Name')))),
            'type' => 'theme',
            'slug' => pathinfo($slug_theme, PATHINFO_FILENAME),
            'version' => strtolower(trim(html_entity_decode($object_theme->get('Version')))),
            'update_servers' => '',
        );

        // Get author name.
        $item['author'] = utf8_encode(trim(html_entity_decode($object_theme->get('Author'))));
        $item['author_url'] = utf8_encode(trim(html_entity_decode($object_theme->get('AuthorURI'))));

        // check if theme is activated
        $current_theme = utf8_encode(trim(html_entity_decode(wp_get_theme()->get('Name'))));
        if ($current_theme == $item['name']) {
            $item['enabled'] = 1;
        } else {
            $item['enabled'] = 0;
        }

        return $item;
    }

    /*
     * Getting informations about plugin from Wordpress repository
     */
    public function checkPluginUpdate($slug)
    {
        $url = 'http://api.wordpress.org/plugins/info/1.0/'.$slug.'.json';

        $response = wp_remote_get($url);
        if (is_wp_error($response) || empty($response['body'])) {
            return false;
        }

        $body = json_decode($response['body']);
        if ($body) {
            return $body;
        } else {
            return false;
        }
    }

    /*
     * Getting version of theme from Wordpress repository
     */
    public function checkThemeUpdate($slug)
    {
        $url = 'http://api.wordpress.org/themes/info/1.0/';

        $args = array(
            'slug' => $slug,
            'fields' => array('screenshot_url' => true),
        );

        $response = wp_remote_post($url, array('body' => array('action' => 'theme_information', 'request' => serialize((object) $args))));
        if (is_wp_error($response) || empty($response['body'])) {
            return false;
        }

        $body = unserialize($response['body']);
        if ($body) {
            return $body->version;
        } else {
            return false;
        }
    }

    /**
     * Checking if Wordpress is compatible with given versions.
     *
     * @param string $required   - the minimum required version of Wordpress
     * @param string $tested     - the maximum tested version of Wordpress
     * @param string $wp_version - version of Wordpress
     *
     * @return bool
     */
    public function isAvailableForWordpressVersion($required, $tested, $wp_version = null)
    {
        if ($wp_version === null) {
            $wp_version = get_bloginfo('version');
        }

        // compare given versions to current WordPress version
        $is_not_lower = version_compare($required, $wp_version, '<=');
        $is_not_higher = version_compare($tested, $wp_version, '>=');

        if ($is_not_lower && $is_not_higher) {
            return true;
        } else {
            return false;
        }
    }

    public static function testBackupsFolderHttpRequest($site_url, $backups_directory_path, $timeout = 3)
    {
        $abspath_wthout_slash = substr(ABSPATH, 0, -1);
        $abspath_wthout_slash = str_replace('\\', '/', $abspath_wthout_slash);
        $backups_directory_path = str_replace('\\', '/', $backups_directory_path);
        $backups_directory = str_replace($abspath_wthout_slash, '', $backups_directory_path);

        $response = wp_remote_get($site_url.$backups_directory, $args = array('timeout' => $timeout));
        $response_code = wp_remote_retrieve_response_code($response);

        if (!empty($response_code) && $response_code < 400) {
            return array($backups_directory);
        }

        return array();
    }

    /**
     * Check if backups' folder of Akeeba WP is accessible through http.
     *
     * @param $site_url
     * @param int $timeout
     *
     * @return array
     */
    public static function checkBackupsHttpAccessAkeeba($site_url, $timeout = 3)
    {
        $akeeba_path = WP_PLUGIN_DIR.'/akeebabackupwp';
        $akeeba_app_path = $akeeba_path.'/app';

        // Check if plugin folder exists.
        if (file_exists($akeeba_app_path) && file_exists($akeeba_app_path.'/Awf/Autoloader/Autoloader.php') &&
            file_exists($akeeba_app_path.'/defines.php') && file_exists($akeeba_app_path.'/Solo/engine/Factory.php')) {
            require_once $akeeba_app_path.'/Awf/Autoloader/Autoloader.php';
            // Add our app to the autoloader
            Awf\Autoloader\Autoloader::getInstance()->addMap('Solo\\', array(
                $akeeba_path.'/helpers/Solo',
                $akeeba_app_path.'/Solo',
            ));

            // Load the platform defines
            if (!defined('APATH_BASE')) {
                require_once $akeeba_app_path.'/defines.php';
            }

            if (!defined('AKEEBAENGINE')) {
                define('AKEEBAENGINE', 1);
            }

            try {
                require_once $akeeba_app_path.'/Solo/engine/Factory.php';

                if (file_exists($akeeba_app_path.'/Solo/alice/factory.php')) {
                    require_once $akeeba_app_path.'/Solo/alice/factory.php';
                }

                Akeeba\Engine\Platform::addPlatform('Wordpress', $akeeba_path.'/helpers/Platform/Wordpress');
                $application = Awf\Application\Application::getInstance('Solo');
                $application->initialise();
                Akeeba\Engine\Platform::getInstance()->load_configuration();
                $akeeba_engine_config = Akeeba\Engine\Factory::getConfiguration();
                $backups_directory_path = $akeeba_engine_config->get('akeeba.basic.output_directory');
            } catch (Exception $e) {
            }

            // Check if backup folder exists.
            if (!empty($backups_directory_path) && file_exists($backups_directory_path)) {
                return self::testBackupsFolderHttpRequest($site_url, $backups_directory_path, $timeout);
            }
        }

        return array();
    }

    /**
     * Check if backups' folder of Duplicator is accessible through http.
     *
     * @param $site_url
     * @param int $timeout
     *
     * @return array
     */
    public static function checkBackupsHttpAccessDuplicator($site_url, $timeout = 3)
    {
        $define_file = WP_PLUGIN_DIR.'/duplicator/define.php';

        // Check if plugin folder exists.
        if (file_exists(WP_PLUGIN_DIR.'/duplicator') && file_exists($define_file)) {
            if (!defined('DUPLICATOR_SSDIR_PATH')) {
                require_once $define_file;
            }

            // Check if backup folder exists.
            if (defined('DUPLICATOR_SSDIR_PATH') && file_exists(DUPLICATOR_SSDIR_PATH)) {
                return self::testBackupsFolderHttpRequest($site_url, DUPLICATOR_SSDIR_PATH, $timeout);
            }
        }

        return array();
    }

    /**
     * Check if backups' folder of Updraft Plus is accessible through http.
     *
     * @param $site_url
     * @param int $timeout
     *
     * @return array
     */
    public static function checkBackupsHttpAccessUpdraftPlus($site_url, $timeout = 3)
    {
        // Check if plugin folder exists.
        if (file_exists(WP_PLUGIN_DIR.'/updraftplus') && file_exists(WP_PLUGIN_DIR.'/updraftplus/class-updraftplus.php') &&
            file_exists(WP_PLUGIN_DIR.'/updraftplus/options.php')) {
            if (!defined('UPDRAFTPLUS_DIR')) {
                define('UPDRAFTPLUS_DIR', 1);
            }

            require_once WP_PLUGIN_DIR.'/updraftplus/class-updraftplus.php';
            require_once WP_PLUGIN_DIR.'/updraftplus/options.php';

            $updraft_plus = new UpdraftPlus();
            $backups_directory_path = $updraft_plus->backups_dir_location();

            // Check if backup folder exists.
            if (!empty($backups_directory_path) && file_exists($backups_directory_path)) {
                return self::testBackupsFolderHttpRequest($site_url, $backups_directory_path, $timeout);
            }
        }

        return array();
    }

    /**
     * Check if backups' folder of Back Up WP is accessible through http.
     *
     * @param $site_url
     * @param int $timeout
     *
     * @return array
     */
    public static function checkBackupsHttpAccessBackUpWordPress($site_url, $timeout = 3)
    {
        // Check if plugin folder exists.
        if (file_exists(WP_PLUGIN_DIR.'/updraftplus') && file_exists(WP_PLUGIN_DIR.'/backupwordpress/classes/class-path.php')) {
            require_once WP_PLUGIN_DIR.'/backupwordpress/classes/class-path.php';

            $backups_directory_path = HM\BackUpWordPress\Path::get_path();

            // Check if backup folder exists.
            if (!empty($backups_directory_path) && file_exists($backups_directory_path)) {
                return self::testBackupsFolderHttpRequest($site_url, $backups_directory_path, $timeout);
            }
        }

        return array();
    }

    /**
     * Check if backups' folder of XCloner is accessible through http.
     *
     * @param $site_url
     * @param int $timeout
     *
     * @return array
     */
    public static function checkBackupsHttpAccessXCloner($site_url, $timeout = 3)
    {
        $config_file = WP_PLUGIN_DIR.'/xcloner-backup-and-restore/cloner.config.php';
        // Check if plugin folder exists.
        if (file_exists($config_file)) {
            require_once $config_file;

            if (isset($_CONFIG['backup_path'])) {
                // Taken from wp-content/plugins/xcloner-backup-and-restore/common.php
                $backups_dir = str_replace('//administrator', '/administrator', $_CONFIG['backup_path'].'/administrator/backups');
                $backups_dir = str_replace('\\', '/', $backups_dir);

                $backups_directory_path = $backups_dir;
                if (file_exists($backups_directory_path)) {
                    return self::testBackupsFolderHttpRequest($site_url, $backups_directory_path, $timeout);
                }
            }
        }

        return array();
    }

    /**
     * Check if backups' folder of Perfect Dashboard is accessible through http.
     *
     * @param $site_url
     * @param $backup_tool_path
     * @param int $timeout
     *
     * @return array
     */
    public static function checkBackupsHttpAccessPerfectDashboard($site_url, $backup_tool_path, $timeout = 3)
    {
        // Check if backup folder exists.
        if (!empty($backup_tool_path) && file_exists($backup_tool_path)) {
            $backups_directory_path = $backup_tool_path.'backups/';
            if (file_exists($backups_directory_path)) {
                return self::testBackupsFolderHttpRequest($site_url, $backups_directory_path, $timeout);
            }
        }

        return array();
    }
}
