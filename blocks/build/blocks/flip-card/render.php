<?php
/**
 * Render the flip card block on the front end
 */

// Get the attributes
$show_preview = $attributes['showPreview'] ?? false;
$min_height = $attributes['minHeight'] ?? 300;

// Get the block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'flip-card-container is-preview-mode',
    'style' => 'min-height: ' . $min_height . 'px;'
]);

// Generate unique IDs for accessibility
$card_id = 'flip-card-' . wp_unique_id();
$front_id = $card_id . '-front';
$back_id = $card_id . '-back';
$desc_id = $card_id . '-desc';
?>

<div <?php echo $wrapper_attributes; ?>
     role="button" 
     aria-pressed="false" 
     tabindex="0" 
     aria-describedby="<?php echo esc_attr($desc_id); ?>"
     data-card-id="<?php echo esc_attr($card_id); ?>">
     
    <div class="flip-card-inner">
        <?php
        // Get the InnerBlocks content
        $inner_blocks = $block->inner_blocks;
        
        if (!empty($inner_blocks)) {
            foreach ($inner_blocks as $index => $inner_block) {
                $is_front = $index === 0;
                $side_class = $is_front ? 'flip-card-front' : 'flip-card-back';
                $side_id = $is_front ? $front_id : $back_id;
                $aria_hidden = $is_front ? 'false' : 'true';
                
                echo '<div class="' . esc_attr($side_class) . '" id="' . esc_attr($side_id) . '" aria-hidden="' . esc_attr($aria_hidden) . '">';
                echo $inner_block->render();
                echo '</div>';
            }
        }
        ?>
    </div>
    
    <!-- Screen reader instructions -->
    <span class="sr-only" id="<?php echo esc_attr($desc_id); ?>">
        This is a flip card. Activate by pressing enter or space bar to flip between front and back content.
    </span>
</div>