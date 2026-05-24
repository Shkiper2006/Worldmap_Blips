<?php

namespace WorldMap;

class Plugin
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'world_map_markers';
    }

    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix . 'world_map_markers';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            coord_x DECIMAL(10,6) NOT NULL,
            coord_y DECIMAL(10,6) NOT NULL,
            description LONGTEXT NULL,
            images LONGTEXT NULL,
            icon VARCHAR(120) NULL,
            icon_color VARCHAR(32) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY title (title)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'on_init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'on_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_filter('script_loader_tag', [$this, 'add_defer_to_plugin_scripts'], 10, 3);
    }

    public function on_init(): void
    {
        load_plugin_textdomain('world-map-blips', false, dirname(plugin_basename(__DIR__)) . '/languages');
        add_shortcode('world_map', [$this, 'render_world_map_shortcode']);
        add_shortcode('worldmap_blips', [$this, 'render_world_map_shortcode']);
        register_block_type('world-map/blips', ['render_callback' => [$this, 'render_world_map_block']]);
    }

    public function on_admin_menu(): void
    {
        add_menu_page(__('World Map', 'world-map-blips'), __('World Map', 'world-map-blips'), 'manage_options', 'world-map-blips', [$this, 'render_markers_page'], 'dashicons-location', 58);

        add_submenu_page('world-map-blips', __('Все метки', 'world-map-blips'), __('Все метки', 'world-map-blips'), 'manage_options', 'world-map-blips', [$this, 'render_markers_page']);
        add_submenu_page('world-map-blips', __('Добавить метку', 'world-map-blips'), __('Добавить метку', 'world-map-blips'), 'manage_options', 'world-map-blips-add', [$this, 'render_add_edit_page']);
        add_submenu_page('world-map-blips', __('Настройки', 'world-map-blips'), __('Настройки', 'world-map-blips'), 'manage_options', 'world-map-blips-settings', [$this, 'render_stub_page']);
        add_submenu_page('world-map-blips', __('Иконки', 'world-map-blips'), __('Иконки', 'world-map-blips'), 'manage_options', 'world-map-blips-icons', [$this, 'render_stub_page']);
        add_submenu_page('world-map-blips', __('Импорт/Экспорт', 'world-map-blips'), __('Импорт/Экспорт', 'world-map-blips'), 'manage_options', 'world-map-blips-import-export', [$this, 'render_stub_page']);
    }

    public function handle_admin_actions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['world_map_save_marker'])) {
            check_admin_referer('world_map_save_marker');
            $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
            $images_raw = wp_unslash($_POST['images'] ?? '');
            $images_ids = [];
            if (is_array($images_raw)) {
                $images_ids = array_map('absint', $images_raw);
            } else {
                $images_ids = array_map('absint', array_filter(array_map('trim', explode(',', (string) $images_raw))));
            }
            $images_ids = $this->validate_media_ids($images_ids);
            $data = [
                'title' => sanitize_text_field(wp_unslash($_POST['title'] ?? '')),
                'coord_x' => floatval($_POST['coord_x'] ?? 0),
                'coord_y' => floatval($_POST['coord_y'] ?? 0),
                'description' => wp_kses_post(wp_unslash($_POST['description'] ?? '')),
                'images' => wp_json_encode($images_ids),
                'icon' => sanitize_text_field(wp_unslash($_POST['icon'] ?? '')),
                'icon_color' => sanitize_hex_color(wp_unslash($_POST['icon_color'] ?? '')),
                'status' => in_array($_POST['status'] ?? 'draft', ['draft', 'publish'], true) ? $_POST['status'] : 'draft',
            ];
            global $wpdb;
            if ($id > 0) {
                $wpdb->update($this->table_name, $data, ['id' => $id]);
            } else {
                $wpdb->insert($this->table_name, $data);
            }
            delete_transient('world_map_blips_markers_public');
            wp_safe_redirect(admin_url('admin.php?page=world-map-blips'));
            exit;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete' && ! empty($_POST['marker_ids'])) {
            check_admin_referer('bulk-markers');
            $ids = array_map('absint', (array) $_POST['marker_ids']);
            $ids = array_filter($ids);
            if ($ids) {
                global $wpdb;
                $in = implode(',', $ids);
                $wpdb->query("DELETE FROM {$this->table_name} WHERE id IN ({$in})");
                delete_transient('world_map_blips_markers_public');
            }
            wp_safe_redirect(admin_url('admin.php?page=world-map-blips'));
            exit;
        }
    }

    public function render_markers_page(): void
    {
        global $wpdb;
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $status = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
        $where = '1=1';
        if ($search !== '') {
            $where .= $wpdb->prepare(' AND title LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        if (in_array($status, ['draft', 'publish'], true)) {
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }
        $markers = $wpdb->get_results("SELECT id,title,coord_x,coord_y,status,created_at FROM {$this->table_name} WHERE {$where} ORDER BY id DESC LIMIT 200", ARRAY_A);
        include plugin_dir_path(__DIR__) . 'templates/admin-markers.php';
    }

    public function render_add_edit_page(): void
    {
        global $wpdb;
        $id = absint($_GET['id'] ?? 0);
        $marker = [
            'id' => 0,'title' => '', 'coord_x' => '', 'coord_y' => '', 'description' => '', 'images' => '[]', 'icon' => '', 'icon_color' => '#ff0000', 'status' => 'draft',
        ];
        if ($id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id=%d", $id), ARRAY_A);
            if ($row) { $marker = $row; }
        }
        include plugin_dir_path(__DIR__) . 'templates/admin-marker-form.php';
    }

    public function render_stub_page(): void
    {
        echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1><p>' . esc_html__('Раздел в разработке.', 'world-map-blips') . '</p></div>';
    }

    public function render_world_map_shortcode(array $atts = [], string $content = ''): string { return self::render_world_map($atts, $content); }
    public function render_world_map_block(array $attributes = [], string $content = ''): string { return self::render_world_map($attributes, $content); }

    public static function render_world_map(array $config = [], string $content = ''): string
    {
        $defaults = ['className' => '', 'title' => __('World map', 'world-map-blips'), 'points' => [], 'use_frontend_fetch' => false];
        $data = wp_parse_args($config, $defaults);
        $data = [
            'className' => sanitize_html_class((string) $data['className']),
            'title' => sanitize_text_field((string) $data['title']),
            'points' => is_array($data['points']) ? $data['points'] : [],
            'use_frontend_fetch' => (bool) $data['use_frontend_fetch'],
        ];
        if (empty($data['points'])) {
            $instance = new self();
            $data['points'] = $instance->get_cached_public_markers();
        }
        $data['content'] = $content;
        $data['json'] = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ob_start();
        include plugin_dir_path(__DIR__) . 'templates/map.php';
        return (string) ob_get_clean();
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, 'world-map-blips') === false) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('world-map-blips-admin', plugin_dir_url(__DIR__) . 'assets/js/admin.js', ['jquery'], '0.1.0', true);
        wp_enqueue_style('world-map-blips-admin', plugin_dir_url(__DIR__) . 'assets/css/admin.css', [], '0.1.0');
    }

    public function enqueue_block_editor_assets(): void { wp_enqueue_script('world-map-blips-editor', plugin_dir_url(__DIR__) . 'assets/js/editor.js', ['wp-blocks', 'wp-element', 'wp-i18n'], '0.1.0', true); wp_enqueue_style('world-map-blips-editor', plugin_dir_url(__DIR__) . 'assets/css/editor.css', [], '0.1.0'); }
    public function enqueue_public_assets(): void
    {
        if (is_admin()) {
            return;
        }
        wp_enqueue_script('world-map-blips-public', plugin_dir_url(__DIR__) . 'assets/js/public.js', [], '0.2.0', true);
        wp_enqueue_style('world-map-blips-public', plugin_dir_url(__DIR__) . 'assets/css/public.css', [], '0.2.0');
        wp_localize_script('world-map-blips-public', 'worldMapBlipsApi', [
            'url' => esc_url_raw(rest_url('world-map-blips/v1/markers')),
            'nonce' => wp_create_nonce('wp_rest'),
            'fetchEnabled' => (bool) apply_filters('world_map_blips_enable_frontend_fetch', false),
            'worldMapImage' => esc_url_raw(plugin_dir_url(__DIR__) . 'assets/maps/world.svg'),
            'tilesPattern' => esc_url_raw(plugin_dir_url(__DIR__) . 'assets/maps/tiles/{z}/{x}/{y}.webp'),
        ]);
    }

    public function add_defer_to_plugin_scripts(string $tag, string $handle, string $src): string
    {
        if (in_array($handle, ['world-map-blips-public'], true)) {
            return '<script src="' . esc_url($src) . '" defer></script>';
        }
        return $tag;
    }

    public function register_rest_routes(): void
    {
        register_rest_route('world-map-blips/v1', '/markers', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_public_markers'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('world-map-blips/v1', '/admin/markers', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_get_admin_markers'],
                'permission_callback' => [$this, 'rest_admin_permission_check'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'rest_create_marker'],
                'permission_callback' => [$this, 'rest_admin_permission_check'],
            ],
        ]);

        register_rest_route('world-map-blips/v1', '/admin/markers/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'rest_get_admin_marker'],
                'permission_callback' => [$this, 'rest_admin_permission_check'],
            ],
            [
                'methods' => 'PUT,PATCH',
                'callback' => [$this, 'rest_update_marker'],
                'permission_callback' => [$this, 'rest_admin_permission_check'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'rest_delete_marker'],
                'permission_callback' => [$this, 'rest_admin_permission_check'],
            ],
        ]);
    }

    public function rest_get_public_markers(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response($this->get_cached_public_markers(), 200);
    }

    public function rest_admin_permission_check(): bool
    {
        $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'] ?? ''));
        return current_user_can('manage_options') && wp_verify_nonce($nonce, 'wp_rest');
    }

    public function rest_get_admin_markers(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT 200", ARRAY_A);
        return new \WP_REST_Response(array_map([$this, 'normalize_admin_marker'], is_array($rows) ? $rows : []), 200);
    }

    public function rest_get_admin_marker(\WP_REST_Request $request)
    {
        global $wpdb;
        $id = absint($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id), ARRAY_A);
        if (! $row) {
            return new \WP_Error('world_map_marker_not_found', __('Marker not found.', 'world-map-blips'), ['status' => 404]);
        }
        return new \WP_REST_Response($this->normalize_admin_marker($row), 200);
    }

    public function rest_create_marker(\WP_REST_Request $request)
    {
        return $this->save_marker_from_rest($request);
    }

    public function rest_update_marker(\WP_REST_Request $request)
    {
        return $this->save_marker_from_rest($request, absint($request['id']));
    }

    public function rest_delete_marker(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = absint($request['id']);
        $wpdb->delete($this->table_name, ['id' => $id], ['%d']);
        delete_transient('world_map_blips_markers_public');
        return new \WP_REST_Response(['deleted' => true], 200);
    }

    private function save_marker_from_rest(\WP_REST_Request $request, int $marker_id = 0)
    {
        $is_update = $marker_id > 0;
        $images_ids = $this->validate_media_ids(array_map('absint', (array) $request->get_param('images')));
        $status = in_array($request->get_param('status'), ['draft', 'publish'], true) ? $request->get_param('status') : 'draft';
        $data = [
            'title' => sanitize_text_field((string) $request->get_param('title')),
            'coord_x' => floatval($request->get_param('coord_x')),
            'coord_y' => floatval($request->get_param('coord_y')),
            'description' => wp_kses_post((string) $request->get_param('description')),
            'images' => wp_json_encode($images_ids),
            'icon' => sanitize_text_field((string) $request->get_param('icon')),
            'icon_color' => sanitize_hex_color((string) $request->get_param('icon_color')),
            'status' => $status,
        ];
        global $wpdb;
        if ($marker_id > 0) {
            $wpdb->update($this->table_name, $data, ['id' => $marker_id]);
        } else {
            $wpdb->insert($this->table_name, $data);
            $marker_id = (int) $wpdb->insert_id;
        }
        delete_transient('world_map_blips_markers_public');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $marker_id), ARRAY_A);
        return new \WP_REST_Response($this->normalize_admin_marker(is_array($row) ? $row : []), $is_update ? 200 : 201);
    }

    private function normalize_admin_marker(array $marker): array
    {
        return [
            'id' => absint($marker['id'] ?? 0),
            'title' => sanitize_text_field($marker['title'] ?? ''),
            'coord_x' => floatval($marker['coord_x'] ?? 0),
            'coord_y' => floatval($marker['coord_y'] ?? 0),
            'description' => wp_kses_post($marker['description'] ?? ''),
            'images' => array_map('absint', (array) json_decode((string) ($marker['images'] ?? '[]'), true)),
            'icon' => sanitize_text_field($marker['icon'] ?? ''),
            'icon_color' => sanitize_hex_color($marker['icon_color'] ?? ''),
            'status' => sanitize_text_field($marker['status'] ?? 'draft'),
            'created_at' => sanitize_text_field($marker['created_at'] ?? ''),
            'updated_at' => sanitize_text_field($marker['updated_at'] ?? ''),
        ];
    }

    private function get_cached_public_markers(): array
    {
        $cached = get_transient('world_map_blips_markers_public');
        if (is_array($cached)) {
            return $cached;
        }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,title,coord_x,coord_y,description,images,icon,icon_color FROM {$this->table_name} WHERE status = %s ORDER BY id DESC", 'publish'), ARRAY_A);
        $markers = array_map([$this, 'normalize_marker'], is_array($rows) ? $rows : []);
        set_transient('world_map_blips_markers_public', $markers, HOUR_IN_SECONDS);
        return $markers;
    }

    private function normalize_marker(array $marker): array
    {
        $images = json_decode((string) ($marker['images'] ?? '[]'), true);
        $image_ids = $this->validate_media_ids(is_array($images) ? $images : []);
        $prepared_images = [];
        foreach ($image_ids as $image_id) {
            $prepared_images[] = [
                'id' => $image_id,
                'url' => esc_url_raw(wp_get_attachment_image_url($image_id, 'large') ?: ''),
                'alt' => sanitize_text_field(get_post_meta($image_id, '_wp_attachment_image_alt', true)),
            ];
        }
        return [
            'id' => absint($marker['id'] ?? 0),
            'title' => sanitize_text_field($marker['title'] ?? ''),
            'coord_x' => floatval($marker['coord_x'] ?? 0),
            'coord_y' => floatval($marker['coord_y'] ?? 0),
            'description' => wp_kses_post($marker['description'] ?? ''),
            'images' => $prepared_images,
            'icon' => sanitize_text_field($marker['icon'] ?? ''),
            'icon_color' => sanitize_hex_color($marker['icon_color'] ?? ''),
        ];
    }

    private function validate_media_ids(array $ids): array
    {
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $max_size = 5 * 1024 * 1024;
        $valid = [];
        foreach ($ids as $id) {
            $attachment_id = absint($id);
            if (! $attachment_id || get_post_type($attachment_id) !== 'attachment') {
                continue;
            }
            $file = get_attached_file($attachment_id);
            $mime = get_post_mime_type($attachment_id);
            $extension = strtolower((string) pathinfo((string) $file, PATHINFO_EXTENSION));
            if (! in_array($mime, $allowed_mimes, true) || ! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                continue;
            }
            if (! $file || ! file_exists($file) || filesize($file) > $max_size) {
                continue;
            }
            $valid[] = $attachment_id;
        }
        return $valid;
    }
}
