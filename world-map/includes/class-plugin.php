<?php

namespace WorldMap;

class Plugin
{
    public function register_hooks(): void
    {
        add_action('init', [$this, 'on_init']);
        add_action('admin_menu', [$this, 'on_admin_menu']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
    }

    public function on_init(): void
    {
        load_plugin_textdomain(
            'world-map-blips',
            false,
            dirname(plugin_basename(__DIR__)) . '/languages'
        );

        add_shortcode('world_map', [$this, 'render_world_map_shortcode']);

        register_block_type('world-map/blips', [
            'render_callback' => [$this, 'render_world_map_block'],
        ]);
    }

    public function on_admin_menu(): void
    {
        add_menu_page(
            __('World Map', 'world-map-blips'),
            __('World Map', 'world-map-blips'),
            'manage_options',
            'world-map-blips',
            [$this, 'render_admin_page'],
            'dashicons-location',
            58
        );
    }

    public function render_admin_page(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('World Map Blips', 'world-map-blips') . '</h1></div>';
    }

    public function render_world_map_shortcode(array $atts = [], string $content = ''): string
    {
        return self::render_world_map($atts, $content);
    }

    public function render_world_map_block(array $attributes = [], string $content = ''): string
    {
        return self::render_world_map($attributes, $content);
    }

    public static function render_world_map(array $config = [], string $content = ''): string
    {
        $defaults = [
            'className' => '',
            'title' => __('World map', 'world-map-blips'),
            'points' => [],
        ];

        $data = wp_parse_args($config, $defaults);
        $data = [
            'className' => sanitize_html_class((string) $data['className']),
            'title' => sanitize_text_field((string) $data['title']),
            'points' => is_array($data['points']) ? $data['points'] : [],
        ];

        $data['content'] = $content;
        $data['json'] = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        ob_start();
        include plugin_dir_path(__DIR__) . 'templates/map.php';

        return (string) ob_get_clean();
    }

    public function enqueue_block_editor_assets(): void
    {
        wp_enqueue_script(
            'world-map-blips-editor',
            plugin_dir_url(__DIR__) . 'assets/js/editor.js',
            ['wp-blocks', 'wp-element', 'wp-i18n'],
            '0.1.0',
            true
        );

        wp_enqueue_style(
            'world-map-blips-editor',
            plugin_dir_url(__DIR__) . 'assets/css/editor.css',
            [],
            '0.1.0'
        );
    }

    public function enqueue_public_assets(): void
    {
        wp_enqueue_script(
            'world-map-blips-public',
            plugin_dir_url(__DIR__) . 'assets/js/public.js',
            [],
            '0.1.0',
            true
        );

        wp_enqueue_style(
            'world-map-blips-public',
            plugin_dir_url(__DIR__) . 'assets/css/public.css',
            [],
            '0.1.0'
        );
    }
}
