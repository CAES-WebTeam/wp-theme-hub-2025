<?php
/**
 * External Publisher Block Render Template
 */

// Get the current post ID
$post_id = get_the_ID();

// Get the External Publishers from ACF for this specific post
$external_publisher = get_field('external_publisher', $post_id);

// Debug the term lookup
echo '<pre style="background: #f0f0f0; padding: 10px; margin: 10px 0;">';
echo 'Post ID: ' . $post_id . "\n";
echo 'Publisher value: '; var_dump($external_publisher);

if ($external_publisher) {
    echo "\nTrying to get term for ID: " . $external_publisher . "\n";
    
    $term = get_term($external_publisher);
    echo 'get_term result: '; var_dump($term);
    
    if (is_wp_error($term)) {
        echo 'Term error: ' . $term->get_error_message() . "\n";
    }
    
    // Try getting term with taxonomy specified
    echo "\nTrying with taxonomy specified:\n";
    $term_with_tax = get_term($external_publisher, 'external_publisher'); // Replace with your actual taxonomy name
    echo 'get_term with taxonomy: '; var_dump($term_with_tax);
}
echo '</pre>';

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