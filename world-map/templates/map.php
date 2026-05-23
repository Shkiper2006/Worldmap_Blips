<?php
/** @var array<string,mixed> $data */
$mapClass = 'world-map-blips__map';
if (! empty($data['className'])) {
    $mapClass .= ' ' . $data['className'];
}
?>
<div class="world-map-blips" data-world-map="<?php echo esc_attr((string) ($data['json'] ?? '{}')); ?>">
    <div class="<?php echo esc_attr($mapClass); ?>" role="region" aria-label="<?php echo esc_attr((string) ($data['title'] ?? '')); ?>"></div>
    <?php if (! empty($data['title'])) : ?>
        <h3 class="world-map-blips__title"><?php echo esc_html((string) $data['title']); ?></h3>
    <?php endif; ?>
    <?php if (! empty($data['content'])) : ?>
        <div class="world-map-blips__content"><?php echo wp_kses_post((string) $data['content']); ?></div>
    <?php endif; ?>
</div>
