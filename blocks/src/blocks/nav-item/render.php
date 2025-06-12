<?php

$link_text = $attributes['linkText'] ?? 'Navigation Item';
$link_url = $attributes['linkUrl'] ?? '#';
$opens_in_new_tab = $attributes['opensInNewTab'] ?? false;
$has_flyout = $attributes['hasFlyout'] ?? false;
$flyout_id = $attributes['flyoutId'] ?? '';

// Get current page URL for comparison
$current_url = home_url($_SERVER['REQUEST_URI']);
$current_path = parse_url($current_url, PHP_URL_PATH);
$nav_path = parse_url($link_url, PHP_URL_PATH);

// Check if this nav item is current
$is_current = false;
$is_current_parent = false;

if ($nav_path) {
    // Exact match
    if ($current_path === $nav_path) {
        $is_current = true;
    }
    // Parent page match (current page is a subpage of this nav item)
    elseif ($current_path && strpos($current_path, rtrim($nav_path, '/') . '/') === 0) {
        $is_current_parent = true;
    }
}

// Build CSS classes
$nav_classes = ['nav-item'];
if ($has_flyout) {
    $nav_classes[] = 'nav-item-with-submenu';
}
if ($is_current) {
    $nav_classes[] = 'nav-item-current';
}
if ($is_current_parent) {
    $nav_classes[] = 'nav-item-current-parent';
}

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => implode(' ', $nav_classes)
]);

$target = $opens_in_new_tab ? ' target="_blank" rel="noopener"' : '';

// Add aria-current for accessibility
$aria_current = '';
if ($is_current) {
    $aria_current = ' aria-current="page"';
} elseif ($is_current_parent) {
    $aria_current = ' aria-current="true"';
}

// Create block context for inner blocks (flyout)
$block_context = array(
    'fieldReport/flyoutId' => $flyout_id,
    'fieldReport/parentNavItem' => $link_text
);
?>

<li <?php echo $wrapper_attributes; ?>>
    <?php if ($has_flyout): ?>
        <div class="nav-link-wrapper">
            <a href="<?php echo esc_url($link_url); ?>" class="nav-link nav-primary-link"<?php echo $target . $aria_current; ?>>
                <?php echo esc_html($link_text); ?>
            </a>
            <button 
                class="submenu-toggle" 
                aria-expanded="false"
                aria-controls="<?php echo esc_attr($flyout_id); ?>"
                aria-label="<?php echo esc_attr(sprintf(__('Show %s submenu', 'caes-hub'), $link_text)); ?>"
                data-submenu-trigger
            >
                <span class="submenu-arrow">➤</span>
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
        <a href="<?php echo esc_url($link_url); ?>" class="nav-link"<?php echo $target . $aria_current; ?>>
            <?php echo esc_html($link_text); ?>
        </a>
    <?php endif; ?>
</li>