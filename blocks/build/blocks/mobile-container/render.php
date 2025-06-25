<?php
$block_id = $attributes['blockId'] ?? wp_unique_id('mobile-container-');
$mobile_breakpoint = $attributes['mobileBreakpoint'] ?? '768px';
$overlay_position = $attributes['overlayPosition'] ?? 'right';
$overlay_bg_color = $attributes['overlayBackgroundColor'] ?? '#ffffff';
$show_close_button = $attributes['showCloseButton'] ?? true;
$hamburger_label = $attributes['hamburgerLabel'] ?? __('Menu', 'caes-hub');

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'mobile-container',
    'data-block-id' => $block_id,
    'data-mobile-breakpoint' => $mobile_breakpoint,
    'data-overlay-position' => $overlay_position,
    'data-overlay-bg-color' => $overlay_bg_color
]);

// Create unique IDs for accessibility
$trigger_id = $block_id . '-trigger';
$overlay_id = $block_id . '-overlay';
?>

<div <?php echo $wrapper_attributes; ?>>
    <!-- Mobile hamburger trigger button (only visible on mobile) -->
    <button 
        class="mobile-hamburger-trigger" 
        id="<?php echo esc_attr($trigger_id); ?>"
        aria-expanded="false"
        aria-controls="<?php echo esc_attr($overlay_id); ?>"
        aria-label="<?php echo esc_attr($hamburger_label); ?>"
        data-mobile-trigger
    >
        <span class="hamburger-icon">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </span>
        <?php if (!empty($hamburger_label)): ?>
            <span class="hamburger-text sr-only"><?php echo esc_html($hamburger_label); ?></span>
        <?php endif; ?>
    </button>

    <!-- Desktop content (visible on desktop, hidden on mobile) -->
    <div class="desktop-content">
        <?php
        // Render inner blocks for desktop
        if ( ! empty( $block->inner_blocks ) ) {
            foreach ( $block->inner_blocks as $inner_block ) {
                $inner_block_instance = new WP_Block( $inner_block->parsed_block );
                echo $inner_block_instance->render();
            }
        }
        ?>
    </div>

    <!-- Mobile overlay (only visible on mobile when triggered) -->
    <div 
        class="mobile-overlay mobile-overlay-<?php echo esc_attr($overlay_position); ?>" 
        id="<?php echo esc_attr($overlay_id); ?>"
        aria-labelledby="<?php echo esc_attr($trigger_id); ?>"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        style="--overlay-bg-color: <?php echo esc_attr($overlay_bg_color); ?>"
        data-mobile-overlay
        data-overlay-position="<?php echo esc_attr($overlay_position); ?>"
    >
        <div class="mobile-overlay-backdrop" data-overlay-close></div>
        
        <div class="mobile-overlay-content">
            <?php if ($show_close_button): ?>
                <button 
                    class="mobile-overlay-close" 
                    aria-label="<?php echo esc_attr__('Close menu', 'caes-hub'); ?>"
                    data-overlay-close
                >
                    <span class="close-icon">✕</span>
                </button>
            <?php endif; ?>
            
            <div class="mobile-overlay-inner">
                <?php
                // Render inner blocks again for mobile overlay
                if ( ! empty( $block->inner_blocks ) ) {
                    foreach ( $block->inner_blocks as $inner_block ) {
                        $inner_block_instance = new WP_Block( $inner_block->parsed_block );
                        echo $inner_block_instance->render();
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Show hamburger trigger only on mobile */
@media (min-width: <?php echo esc_attr($mobile_breakpoint); ?>) {
    .mobile-container[data-block-id="<?php echo esc_attr($block_id); ?>"] .mobile-hamburger-trigger {
        display: none;
    }
}

/* Hide desktop content on mobile */
@media (max-width: <?php echo esc_attr($mobile_breakpoint); ?>) {
    .mobile-container[data-block-id="<?php echo esc_attr($block_id); ?>"] .desktop-content {
        display: none;
    }
}
</style>