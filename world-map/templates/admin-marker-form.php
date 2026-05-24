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
            <tr><th><?php esc_html_e('Выбрать на карте', 'world-map-blips'); ?></th><td><div id="wm-map-picker"><img src="<?php echo esc_url(plugin_dir_url(__DIR__) . 'assets/maps/world.svg'); ?>" alt="map"/></div><p class="description"><?php esc_html_e('Кликните по мини-карте, чтобы заполнить X/Y.', 'world-map-blips'); ?></p></td></tr>
            <tr><th>description</th><td><?php wp_editor((string) $marker['description'], 'description', ['textarea_name' => 'description']); ?></td></tr>
            <tr><th>images</th><td><input type="hidden" name="images[]" id="wm-images" value="<?php echo esc_attr((string) $marker['images']); ?>"><button type="button" class="button" id="wm-upload"><?php esc_html_e('Выбрать изображения', 'world-map-blips'); ?></button><span id="wm-images-preview"></span></td></tr>
            <tr><th>icon</th><td><input class="regular-text" name="icon" value="<?php echo esc_attr((string) $marker['icon']); ?>"></td></tr>
            <tr><th>icon_color</th><td><input type="color" name="icon_color" value="<?php echo esc_attr((string) $marker['icon_color']); ?>"></td></tr>
            <tr><th>status</th><td><select name="status"><option value="draft" <?php selected($marker['status'], 'draft'); ?>>draft</option><option value="publish" <?php selected($marker['status'], 'publish'); ?>>publish</option></select></td></tr>
        </table>
        <?php submit_button(__('Сохранить', 'world-map-blips')); ?>
    </form>
</div>
