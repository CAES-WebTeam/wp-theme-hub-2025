<?php
/**
 * Server-side rendering for the Reveal block
 *
 * @package CAES_Reveal
 */

// --- HELPER FUNCTION (Must be defined before use) ---
if (!function_exists('caes_reveal_hex2rgba')) {
    function caes_reveal_hex2rgba($color, $opacity = false) {
        $default = 'rgb(0,0,0)';
        if (empty($color)) return $default; 
        if ($color[0] == '#' ) $color = substr( $color, 1 );
        if (strlen($color) == 6) $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
        elseif (strlen($color) == 3) $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
        else return $default;
        $rgb =  array_map('hexdec', $hex);
        if($opacity !== false){
            if(abs($opacity) > 1) $opacity = 1.0;
            $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
        } else {
            $output = 'rgb('.implode(",",$rgb).')';
        }
        return $output;
    }
}

// Get block attributes
$frames          = $attributes['frames'] ?? [];
$overlay_color   = $attributes['overlayColor'] ?? '#000000';
$overlay_opacity = $attributes['overlayOpacity'] ?? 30;

// Speed definitions
$speed_multipliers = [
    'slow'   => 1.5,
    'normal' => 1.0,
    'fast'   => 0.75,
];

// Calculate Total Height for the wrapper
$total_vh = 0;
foreach ($frames as $i => $frame) {
    // First frame gets base height, subsequent frames add height based on transition speed
    $speed = $frame['transition']['speed'] ?? 'normal';
    $multiplier = $speed_multipliers[$speed] ?? 1;
    // Ensure minimum scrollable area (100vh minimum per slide)
    $total_vh += 100 * $multiplier;
}

// Wrapper needs the full height of all triggers combined
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'caes-reveal', 
    'style' => "height: {$total_vh}vh;"
]);

if (empty($frames)) {
    return;
}

$block_id = 'reveal-' . wp_unique_id();

// Now we can safely call the function
$overlay_rgba = caes_reveal_hex2rgba($overlay_color, $overlay_opacity / 100);

?>

<div <?php echo $wrapper_attributes; ?>>

    <div class="reveal-stage" style="position: sticky; top: 0; width: 100%; height: 100vh; overflow: hidden;">
        
        <?php foreach ($frames as $index => $frame) : 
            $img = $frame['image'] ?? null;
            $bg_style = 'opacity: 0; z-index: ' . (10 + $index) . ';';
            if ($index === 0) $bg_style = 'opacity: 1; z-index: 10;'; // First frame visible by default
            
            $transition_type = $frame['transition']['type'] ?? 'fade';
        ?>
            <figure class="reveal-frame" 
                    id="<?php echo $block_id . '-frame-' . $index; ?>"
                    data-index="<?php echo $index; ?>"
                    data-transition-type="<?php echo esc_attr($transition_type); ?>"
                    style="<?php echo $bg_style; ?> position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0; transition: none;">
                
                <?php if ($img) : ?>
                    <img class="reveal-img" 
                         src="<?php echo esc_url($img['url']); ?>" 
                         alt="<?php echo esc_attr($img['alt'] ?? ''); ?>"
                         style="width: 100%; height: 100%; object-fit: cover;"
                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                <?php endif; ?>
                
                <div class="reveal-overlay" style="position:absolute; inset:0; background-color: <?php echo esc_attr($overlay_rgba); ?>;"></div>
            </figure>
        <?php endforeach; ?>

        <div class="reveal-content-layer" style="position: absolute; inset: 0; z-index: 100; pointer-events: none;">
            <?php foreach ($frames as $index => $frame) : ?>
                <div class="reveal-frame-content" 
                     data-index="<?php echo $index; ?>"
                     style="opacity: 0; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                    <div class="content-inner" style="pointer-events: auto; max-width: 800px; padding: 2rem;">
                        <?php echo wp_kses_post($frame['content'] ?? ''); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="reveal-triggers" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
        <?php 
        $current_top = 0;
        foreach ($frames as $index => $frame): 
            $speed = $frame['transition']['speed'] ?? 'normal';
            $multiplier = $speed_multipliers[$speed] ?? 1;
            $height = 100 * $multiplier;
        ?>
            <div class="reveal-trigger" 
                 data-index="<?php echo $index; ?>"
                 style="position: absolute; top: <?php echo $current_top; ?>vh; height: <?php echo $height; ?>vh; width: 100%;">
            </div>
        <?php 
            $current_top += $height;
        endforeach; ?>
    </div>

</div>