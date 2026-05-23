<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$optionKeys = [
    'world_map_blips_settings',
    'world_map_blips_version',
];

foreach ($optionKeys as $optionKey) {
    delete_option($optionKey);
    delete_site_option($optionKey);
}
