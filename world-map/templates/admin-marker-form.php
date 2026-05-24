<div class="wrap">
    <h1><?php echo esc_html($marker['id'] ? __('Редактировать метку', 'world-map-blips') : __('Добавить метку', 'world-map-blips')); ?></h1>
    <form method="post">
        <?php wp_nonce_field('world_map_save_marker'); ?>
        <input type="hidden" name="world_map_save_marker" value="1"/>
        <input type="hidden" name="id" value="<?php echo (int) $marker['id']; ?>"/>
        <table class="form-table">
            <tr><th>title</th><td><input class="regular-text" name="title" value="<?php echo esc_attr($marker['title']); ?>" required></td></tr>
            <tr><th>coord_x</th><td><input class="regular-text" id="coord_x" name="coord_x" value="<?php echo esc_attr((string) $marker['coord_x']); ?>" required></td></tr>
            <tr><th>coord_y</th><td><input class="regular-text" id="coord_y" name="coord_y" value="<?php echo esc_attr((string) $marker['coord_y']); ?>" required></td></tr>
            <tr>
                <th><?php esc_html_e('Выбрать на карте', 'world-map-blips'); ?></th>
                <td>
                    <div id="wm-map-picker" data-map-src="<?php echo esc_url(plugin_dir_url(__DIR__) . 'assets/maps/world.svg'); ?>">
                        <div class="wm-map-toolbar">
                            <button type="button" class="button" id="wm-zoom-in">+</button>
                            <button type="button" class="button" id="wm-zoom-out">−</button>
                            <button type="button" class="button" id="wm-zoom-reset"><?php esc_html_e('Сброс', 'world-map-blips'); ?></button>
                            <button type="button" class="button" id="wm-marker-remove"><?php esc_html_e('Удалить метку', 'world-map-blips'); ?></button>
                            <span id="wm-zoom-value">100%</span>
                        </div>
                        <div id="wm-map-viewport">
                            <div id="wm-map-canvas">
                                <img id="wm-map-image" src="<?php echo esc_url(plugin_dir_url(__DIR__) . 'assets/maps/world.svg'); ?>" alt="map" draggable="false"/>
                                <button type="button" id="wm-map-marker" aria-label="marker"></button>
                            </div>
                        </div>
                    </div>
                    <p class="description"><?php esc_html_e('Клик: поставить метку. Перетаскивание: карта/метка. Колёсико или +/-: zoom.', 'world-map-blips'); ?></p>
                </td>
            </tr>
            <tr><th>description</th><td><?php wp_editor((string) $marker['description'], 'description', ['textarea_name' => 'description']); ?></td></tr>
            <tr><th>images</th><td><input type="hidden" name="images[]" id="wm-images" value="<?php echo esc_attr((string) $marker['images']); ?>"><button type="button" class="button" id="wm-upload"><?php esc_html_e('Выбрать изображения', 'world-map-blips'); ?></button><span id="wm-images-preview"></span></td></tr>
            <tr><th>icon</th><td><input class="regular-text" name="icon" value="<?php echo esc_attr((string) $marker['icon']); ?>"></td></tr>
            <tr><th>icon_color</th><td><input type="color" name="icon_color" value="<?php echo esc_attr((string) $marker['icon_color']); ?>"></td></tr>
            <tr><th>status</th><td><select name="status"><option value="draft" <?php selected($marker['status'], 'draft'); ?>>draft</option><option value="publish" <?php selected($marker['status'], 'publish'); ?>>publish</option></select></td></tr>
        </table>
        <?php submit_button(__('Сохранить', 'world-map-blips')); ?>
    </form>
</div>
