<?php

error_log('Nav item render.php is being called');
error_log('Attributes: ' . print_r($attributes, true));

$link_text = $attributes['linkText'] ?? 'Navigation Item';
$link_url = $attributes['linkUrl'] ?? '#';
$opens_in_new_tab = $attributes['opensInNewTab'] ?? false;
$has_flyout = $attributes['hasFlyout'] ?? false;
$flyout_id = $attributes['flyoutId'] ?? '';

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'nav-item' . ($has_flyout ? ' nav-item-with-submenu' : '')
]);

$target = $opens_in_new_tab ? ' target="_blank" rel="noopener"' : '';

// Create block context for inner blocks (flyout)
$block_context = array(
    'fieldReport/flyoutId' => $flyout_id,
    'fieldReport/parentNavItem' => $link_text
);
?>

<li <?php echo $wrapper_attributes; ?>>
    <?php if ($has_flyout): ?>
        <div class="nav-link-wrapper">
            <a href="<?php echo esc_url($link_url); ?>" class="nav-link nav-primary-link"<?php echo $target; ?>>
                <?php echo esc_html($link_text); ?>
            </a>
            <button 
                class="submenu-toggle" 
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($flyout_id); ?>"
                aria-label="<?php echo esc_attr(sprintf(__('Show %s submenu', 'caes-hub'), $link_text)); ?>"
                data-submenu-trigger
            >
                <span class="submenu-arrow">▶</span>
            </button>
        </div>
        <?php
        // Render inner blocks (flyout content)
        if ( ! empty( $block->inner_blocks ) ) {
            foreach ( $block->inner_blocks as $inner_block ) {
                $inner_block_instance = new WP_Block( $inner_block->parsed_block, $block_context );
                echo $inner_block_instance->render();
            }
        }
        ?>
    <?php else: ?>
        <a href="<?php echo esc_url($link_url); ?>" class="nav-link"<?php echo $target; ?>>
            <?php echo esc_html($link_text); ?>
        </a>
    <?php endif; ?>
</li>