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
global $user_email;
wp_get_current_user();
// For white labelling.
$name = get_option('perfectdashboard-name', null);
$extensions_view_information = get_option('perfectdashboard-extensions-view-information', null);
?>

<div class="perfectdashboard-header">
    <h1 class="perfectdashboard-heading">
        <?php if (empty($name)) : ?>
            <?php _e('Perfect Dashboard extension', 'perfectdashboard'); ?>
        <?php else : ?>
            <?php echo $name; ?>
        <?php endif; ?>
    </h1>

    <button class="button button-primary perfectdashboard-settings-button"
            data-close="<?php _e('Close Settings', 'perfectdashboard'); ?>"
            data-open="<?php _e('Settings', 'perfectdashboard'); ?>">
        <?php _e('Settings', 'perfectdashboard'); ?>
    </button>
</div>

<div class="perfectdashboard">

    <?php if (strlen(get_option('perfectdashboard-ping')) === 19) : ?>
        <?php if (empty($extensions_view_information)) : ?>
            <div class="perfectdashboard-start perfectdashboard-view perfectdashboard-view-active">
                <div class="perfectdashboard-success-view">
                    <h2 class="perfectdashboard-title">
                        <?php _e('This website has been successfully added to Perfect Dashboard.', 'perfectdashboard'); ?>
                    </h2>

                    <h3 class="perfectdashboard-subtitle">
                        <?php printf(__('Go to %s to:', 'perfectdashboard'), '<a href="https://app.perfectdashboard.co/?utm_source=backend&utm_medium=installer&utm_campaign=WP" target="_blank">app.perfectdashboard.co</a>'); ?>
                    </h3>

                    <ul class="perfectdashboard-list-features">
                        <li><span
                                class="dashicons dashicons-yes"></span> <?php _e('Manage WordPress, Themes and Plugins updates', 'perfectdashboard'); ?>
                        </li>
                        <li><span
                                class="dashicons dashicons-yes"></span> <?php _e('Verify Backups Automatically&nbsp;',
                                'perfectdashboard'); ?></li>
                        <li><span
                                class="dashicons dashicons-yes"></span> <?php _e('Test and Validate Websites After Every Update Automatically&nbsp;',
                                'perfectdashboard'); ?></li>
                    </ul>

                    <button type="button" onclick="document.getElementById('perfect-dashboard-install').submit()" class="button">
                        <?php _e('Click here to add your website again to Perfect&nbsp;Dashboard', 'perfectdashboard') ?>
                    </button>
                </div>
            </div>
        <?php else : ?>
            <?php echo $extensions_view_information; ?>
        <?php endif; ?>
    <?php else : ?>
        <div class="perfectdashboard-start perfectdashboard-view perfectdashboard-view-active">

            <div class="perfectdashboard-col2">
                <h2>
                    <?php _e('Let Perfect Dashboard do all the backups & updates for you ', 'perfectdashboard'); ?>
                    <span><?php _e('[for&nbsp;FREE]', 'perfectdashboard'); ?></span>
                </h2>

                <ul class="perfectdashboard-list-features">
                    <li><span class="dashicons dashicons-yes"></span> <?php _e('The One Place You Will Ever Need to Manage All Websites Efficiently',
                            'perfectdashboard'); ?></li>
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Test and Validate Websites After Every Update Automatically',
                            'perfectdashboard'); ?></li>
                    <li><span
                            class="dashicons dashicons-yes"></span> <?php _e('Verify Backups Automatically',
                            'perfectdashboard'); ?></li>
                </ul>

                <button type="button" onclick="document.getElementById('perfect-dashboard-install').submit()"
                        class="button button-primary button-hero perfectdashboard-big-btn">
                    <?php _e('Click here to add your website to Perfect&nbsp;Dashboard', 'perfectdashboard') ?>
                </button>

                <ul class="perfectdashboard-list-presale">
                    <li><?php _e('Functional Basic version. Free forever.', 'perfectdashboard'); ?></li>
                    <li><?php _e('PRO features: 3 weeks for free.', 'perfectdashboard'); ?></li>
                    <li><?php _e('No credit card required. Cancel anytime.', 'perfectdashboard'); ?></li>
                </ul>
            </div>

            <div class="perfectdashboard-col2">
                <div class="perfectdashboard-computer">
                    <img src="<?php echo plugins_url('media/images/laptop.svg', dirname(__FILE__)); ?>" class="perfectdashboard-computer-img" alt="">
                    <video src="<?php echo plugins_url('media/images/laptop.mp4', dirname(__FILE__)); ?>" class="perfectdashboard-computer-video" autoplay loop poster="<?php echo plugins_url('media/images/laptop_poster.png', dirname(__FILE__)); ?>"></video>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <form action="<?php echo PERFECTDASHBOARD_ADDWEBSITE_URL; ?>?utm_source=backend&amp;utm_medium=installer&amp;utm_campaign=WP" method="post" enctype="multipart/form-data" id="perfect-dashboard-install" target="_blank">
        <input type="hidden" name="secure_key" value="<?php echo $key; ?>">
        <input type="hidden" name="user_email" value="<?php echo $user_email; ?>">
        <input type="hidden" name="site_frontend_url" value="<?php echo get_site_url(); ?>">
        <input type="hidden" name="site_backend_url" value="<?php echo get_admin_url(); ?>">
        <input type="hidden" name="cms_type" value="wordpress">
        <input type="hidden" name="version" value="<?php echo PERFECTDASHBOARD_VERSION; ?>">
    </form>

    <div class="perfectdashboard-settings perfectdashboard-view perfectdashboard-view-inactive">
        <form>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label for="perfectdashboard_key"><?php _e('Secure key',
                                'perfectdashboard'); ?></label></th>
                    <td><input id="perfectdashboard_key" placeholder="Key" type="text" class="regular-text"
                               value="<?php echo is_null($key) ? '' : $key; ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label><?php _e('Site offline',
                                'perfectdashboard'); ?></label></th>
                    <td>
                        <label>
                            <input type="radio" name="site_offline" value="0" <?php if (empty($site_offline)) {
                                    echo 'checked="checked"';
                                } ?>/><?php _e('No', 'perfectdashboard'); ?>
                        </label>
                        <label>
                            <input type="radio" name="site_offline" value="1" <?php if (!empty($site_offline)) {
                                    echo 'checked="checked"';
                                } ?>/><?php _e('Yes', 'perfectdashboard'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label><?php _e('Enable SSL verify for download requests',
                                'perfectdashboard'); ?></label></th>
                    <td>
                        <label>
                            <input type="radio" name="ssl_verify" value="0" <?php if (empty($ssl_verify)) {
                                    echo 'checked="checked"';
                                } ?>/><?php _e('No', 'perfectdashboard'); ?>
                        </label>
                        <label>
                            <input type="radio" name="ssl_verify" value="1" <?php if (!empty($ssl_verify)) {
                                    echo 'checked="checked"';
                                } ?>/><?php _e('Yes', 'perfectdashboard'); ?>
                        </label>
                    </td>
                </tr>
                </tbody>
            </table>


            <p class="submit">
                <input type="submit" name="submit" id="perfectdashboard_save_config" class="button button-primary"
                       value="<?php _e('Save changes', 'perfectdashboard') ?>">
            </p>
        </form>
    </div>
