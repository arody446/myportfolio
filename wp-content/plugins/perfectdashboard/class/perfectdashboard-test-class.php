<?php
/**
 * @version   1.4.0
 *
 * @copyright © 2016 Perfect sp. z o.o., All rights reserved. https://www.perfectdashboard.com
 * @license   GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @author    Perfect-Web
 */

// No direct access
function_exists('add_action') or die;

class PerfectDashboardTest
{
    /*
     * Getting list of files and their md5_file checksum
     */
    public function getFilesChecksum($dir, &$results = array())
    {
        $files = scandir($dir);

        foreach ($files as $value) {
            $path = realpath($dir.'/'.$value);
            $rel_path = str_replace(ABSPATH, '', $path);

            if (!is_dir($path)) {
                $results[] = utf8_encode($rel_path).' '.md5_file($path);
            } elseif (is_dir($path) && $value != '.' && $value != '..') {
                $this->getFilesChecksum($path, $results);
            }
        }

        return $results;
    }

    /*
     * Check which part of path isn't writable
     */
    public static function checkPathWriteAbility($path = null, $entry_path = null)
    {
        global $wp_filesystem;

        // Flag to clean cache only once
        static $clean_cache = true;

        if ($clean_cache) {
            $clean_cache = false;
            self::cleanPhpCache();
        }

        static $nested = array();
        static $checked_paths = array(); // To not check same path again
        static $write_errors = array(
            'not_writable' => array(
                'dir' => array(),
                'file' => array(),
            ),
        );

        if (empty($path)) {
            if (empty($write_errors['not_writable']['dir']) && empty($write_errors['not_writable']['file'])
            ) {
                return;
            } else {
                return $write_errors;
            }
        }

        $path = untrailingslashit($path);
        $parent = untrailingslashit(dirname($path));
        $working_dir = untrailingslashit($wp_filesystem->abspath());

        // Prevent infinite loops
        if (empty($nested[$entry_path])) {
            $nested = array(); // Clear array, so it has only current entry_path counter.
            $nested[$entry_path] = 0;
        }

        ++$nested[$entry_path];

        // Prevent infinite loops
        if (($nested[$entry_path] > 20) || ($parent == $path) || $path == $working_dir) {
            return $write_errors;
        }

        if (empty($checked_paths[$path])) {
            $checked_paths[$path] = true;
        } else {
            return $write_errors;
        }

        if ($wp_filesystem->is_file($path) && !is_writable($path)) {
            // Don't display full path
            $relative_path = str_ireplace($working_dir, '', $path);
            $write_errors['not_writable']['file'][$relative_path] = $wp_filesystem->gethchmod($path);
        } elseif ($wp_filesystem->is_dir($path) && !is_writable($path)) {
            // Don't display full path
            $relative_path = str_ireplace($working_dir, '', $path);
            $write_errors['not_writable']['dir'][$relative_path] = $wp_filesystem->gethchmod($path);
        }

        self::checkPathWriteAbility($parent, $entry_path);

        return $write_errors;
    }

    public static function cleanPhpCache()
    {
        // Make sure that PHP has the latest data of the files.
        clearstatcache();

        // Remove all compiled files from opcode cache.
        if (function_exists('opcache_reset')) {
            // Always reset the OPcache if it's enabled. Otherwise there's a good chance the server will not know we are
            // replacing .php scripts. This is a major concern since PHP 5.5 included and enabled OPcache by default.
            @opcache_reset();
        } elseif (function_exists('apc_clear_cache')) {
            @apc_clear_cache();
        }
    }
}
