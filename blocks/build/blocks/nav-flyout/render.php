<?php
// Get flyoutId from context instead of attributes
$flyout_id = $block->context['fieldReport/flyoutId'] ?? '';
$parent_nav_item = $block->context['fieldReport/parentNavItem'] ?? '';

$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'nav-flyout',
    'id' => $flyout_id, // This should now have the value
    'data-submenu' => '',
    'aria-labelledby' => $flyout_id . '-trigger'
]);

// Create context for inner blocks
$block_context = array(
    'fieldReport/flyoutId' => $flyout_id,
    'fieldReport/parentNavItem' => $parent_nav_item
);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="flyout-content">
        <?php
        if ( ! empty( $block->inner_blocks ) ) {
            foreach ( $block->inner_blocks as $inner_block ) {
                $inner_block_instance = new WP_Block( $inner_block->parsed_block, $block_context );
                echo $inner_block_instance->render();
            }
        }
        ?>
    </div>
</div>