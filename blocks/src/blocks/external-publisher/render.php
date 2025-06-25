<?php
/**
 * External Publisher Block Render Template
 */

// Get the current post ID
$post_id = get_the_ID();

// Debug: Check what's actually stored
echo '<pre style="background: #f0f0f0; padding: 10px; margin: 10px 0;">';
echo 'Post ID: ' . $post_id . "\n";
echo 'Raw meta: ';
var_dump(get_post_meta($post_id, 'external_publisher', true));
echo "\nACF get_field: ";
var_dump(get_field('external_publisher', $post_id));
echo "\nACF get_field (bypass cache): ";
var_dump(get_field('external_publisher', $post_id, false));
echo '</pre>';

// Get the External Publishers from ACF for this specific post
$external_publisher = get_field('external_publisher', $post_id);

// If no External Publishers, don't render anything
if (!$external_publisher || empty($external_publisher)) {
    return;
}

// Convert single item to array for consistent processing
if (!is_array($external_publisher)) {
    $external_publisher = [$external_publisher];
}

// Get block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'wp-block-caes-hub-external-publisher'
]);
?>

<div <?php echo $wrapper_attributes; ?>>
    <div class="external-publisher-wrapper">
        <?php foreach ($external_publisher as $index => $publisher): ?>
            <?php 
            $term_name = '';
            
            // Handle different return formats from ACF
            if (is_numeric($publisher)) {
                // If it's a term ID, get the term object
                $term = get_term($publisher);
                if ($term && !is_wp_error($term) && isset($term->name)) {
                    $term_name = $term->name;
                }
            } elseif (is_object($publisher)) {
                // If it's already a term object
                if (isset($publisher->name)) {
                    $term_name = $publisher->name;
                }
            } elseif (is_array($publisher)) {
                // If it's an array, get the name
                if (isset($publisher['name'])) {
                    $term_name = $publisher['name'];
                }
            }
            
            if (!empty($term_name)): ?>
                <span class="external-publisher-item">
                    <span class="external-publisher-text">
                        <?php echo esc_html($term_name); ?>
                    </span>

                    <?php if ($index < count($external_publisher) - 1): ?>
                        <span class="external-publisher-separator">, </span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>