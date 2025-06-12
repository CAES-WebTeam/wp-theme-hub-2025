<?php
$block_id = $attributes['blockId'] ?? wp_unique_id('field-report-nav-');
$hover_delay = $attributes['hoverDelay'] ?? 300;
$mobile_breakpoint = $attributes['mobileBreakpoint'] ?? '768px';

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'field-report-navigation',
    'data-hover-delay' => $hover_delay,
    'data-mobile-breakpoint' => $mobile_breakpoint,
    'data-block-id' => $block_id
]);

// Create block context for inner blocks
$block_context = array(
    'fieldReport/navigationId' => $block_id,
    'fieldReport/hoverDelay' => $hover_delay
);
?>

<nav <?php echo $wrapper_attributes; ?> aria-label="Main">
    <ul class="nav-menu">
        <?php
        if ( ! empty( $block->inner_blocks ) ) {
            foreach ( $block->inner_blocks as $inner_block ) {
                $inner_block_instance = new WP_Block( $inner_block->parsed_block, $block_context );
                echo $inner_block_instance->render();
            }
        }
        ?>
    </ul>
</nav>