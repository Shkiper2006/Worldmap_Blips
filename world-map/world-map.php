<?php
/**
 * Plugin Name:       World Map Blips
 * Description:       A starter plugin for rendering world map blips.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            World Map Blips
 * Text Domain:       world-map-blips
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-loader.php';

WorldMap\Loader::register(__DIR__ . '/includes');

$plugin = new WorldMap\Plugin();
$plugin->register_hooks();
