<?php
/**
 * Code used when the plugin is removed (not just deactivated but actively deleted through the WordPress Admin).
 */
if (!defined('WP_UNINSTALL_PLUGIN'))
    exit ();

//Settings
delete_option('blogsoft_settings');

