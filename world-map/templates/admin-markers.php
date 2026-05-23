<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Все метки', 'world-map-blips'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=world-map-blips-add')); ?>" class="page-title-action"><?php esc_html_e('Добавить метку', 'world-map-blips'); ?></a>
    <hr class="wp-header-end"/>

    <form method="get" style="margin:10px 0;">
        <input type="hidden" name="page" value="world-map-blips"/>
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Поиск по названию', 'world-map-blips'); ?>"/>
        <select name="status">
            <option value=""><?php esc_html_e('Все статусы', 'world-map-blips'); ?></option>
            <option value="publish" <?php selected($status, 'publish'); ?>>publish</option>
            <option value="draft" <?php selected($status, 'draft'); ?>>draft</option>
        </select>
        <button class="button"><?php esc_html_e('Фильтр', 'world-map-blips'); ?></button>
    </form>

    <form method="post">
        <?php wp_nonce_field('bulk-markers'); ?>
        <input type="hidden" name="action" value="bulk_delete"/>
        <button class="button button-secondary"><?php esc_html_e('Удалить выбранные', 'world-map-blips'); ?></button>
        <table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
            <thead><tr><td><input type="checkbox" id="wm-select-all"></td><th>ID</th><th><?php esc_html_e('Название', 'world-map-blips'); ?></th><th><?php esc_html_e('Координаты', 'world-map-blips'); ?></th><th><?php esc_html_e('Статус', 'world-map-blips'); ?></th><th><?php esc_html_e('Дата', 'world-map-blips'); ?></th></tr></thead>
            <tbody>
            <?php if ($markers) : foreach ($markers as $m) : ?>
                <tr>
                    <td><input type="checkbox" name="marker_ids[]" value="<?php echo (int) $m['id']; ?>"></td>
                    <td><?php echo (int) $m['id']; ?></td>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=world-map-blips-add&id=' . (int) $m['id'])); ?>"><?php echo esc_html($m['title']); ?></a></td>
                    <td><?php echo esc_html($m['coord_x'] . ', ' . $m['coord_y']); ?></td>
                    <td><?php echo esc_html($m['status']); ?></td>
                    <td><?php echo esc_html($m['created_at']); ?></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="6"><?php esc_html_e('Нет меток', 'world-map-blips'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>
