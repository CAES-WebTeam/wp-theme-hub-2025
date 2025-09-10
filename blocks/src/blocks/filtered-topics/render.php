<?php
/**
 * Filtered Topics Block Render Template - DEBUG VERSION
 */

// Get block attributes with defaults
$show_hierarchy = isset($attributes['showHierarchy']) ? $attributes['showHierarchy'] : false;
$show_post_counts = isset($attributes['showPostCounts']) ? $attributes['showPostCounts'] : false;
$display_as_dropdown = isset($attributes['displayAsDropdown']) ? $attributes['displayAsDropdown'] : false;
$show_heading = isset($attributes['showHeading']) ? $attributes['showHeading'] : true;
$custom_heading = isset($attributes['customHeading']) ? $attributes['customHeading'] : 'Topics';
$filter_by_context = isset($attributes['filterByContext']) ? $attributes['filterByContext'] : true;
$empty_message = isset($attributes['emptyMessage']) ? $attributes['emptyMessage'] : 'No topics found for this section.';

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'caes-hub-filtered-topics'
]);

/**
 * Determine the current post type context
 */
if (!function_exists('caes_filtered_topics_get_current_post_type_context')) {
    function caes_filtered_topics_get_current_post_type_context() {
        $request_uri = $_SERVER['REQUEST_URI'];
        
        if (strpos($request_uri, '/publications/') === 0) {
            return 'publications';
        } elseif (strpos($request_uri, '/shorthand-story/') === 0) {
            return 'shorthand_story';
        } elseif (strpos($request_uri, '/events/') === 0) {
            return 'events';
        }
        
        if (is_singular()) {
            return get_post_type();
        } elseif (is_post_type_archive()) {
            return get_query_var('post_type');
        } elseif (is_tax('topics')) {
            if (strpos($request_uri, '/publications/topic/') === 0) {
                return 'publications';
            } elseif (strpos($request_uri, '/shorthand-story/topic/') === 0) {
                return 'shorthand_story';
            } elseif (strpos($request_uri, '/events/topic/') === 0) {
                return 'events';
            }
        }
        
        return 'post';
    }
}

// Determine current context
$current_post_type = $filter_by_context ? caes_filtered_topics_get_current_post_type_context() : null;

// DEBUG: Show what's happening
$debug_info = [];
$debug_info['request_uri'] = $_SERVER['REQUEST_URI'];
$debug_info['filter_by_context'] = $filter_by_context;
$debug_info['detected_post_type'] = $current_post_type;

// Get terms based on context
if ($current_post_type && $current_post_type !== 'post') {
    // Get only terms that have posts in the current post type
    $post_ids = get_posts(array(
        'post_type' => $current_post_type,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    $debug_info['post_ids_found'] = count($post_ids);
    $debug_info['first_few_post_ids'] = array_slice($post_ids, 0, 5);
    
    if (empty($post_ids)) {
        $terms = array();
        $debug_info['terms_result'] = 'No posts found, terms set to empty';
    } else {
        $terms = get_terms(array(
            'taxonomy' => 'topics',
            'hide_empty' => true,
            'object_ids' => $post_ids
        ));
        $debug_info['terms_found'] = is_array($terms) ? count($terms) : 'Error: ' . $terms->get_error_message();
    }
} else {
    // Show all terms (news/general context)
    $terms = get_terms(array(
        'taxonomy' => 'topics',
        'hide_empty' => true
    ));
    $debug_info['terms_found'] = is_array($terms) ? count($terms) : 'Error: ' . $terms->get_error_message();
    $debug_info['context'] = 'Showing all terms (news context)';
}

?>

<div <?php echo $wrapper_attributes; ?>>
    <!-- DEBUG INFO - REMOVE AFTER TESTING -->
    <div style="background: #f0f0f0; padding: 15px; margin-bottom: 20px; border: 1px solid #ccc;">
        <h4>DEBUG INFO:</h4>
        <pre><?php print_r($debug_info); ?></pre>
        <?php if (!empty($terms) && is_array($terms)): ?>
            <h5>First few terms found:</h5>
            <ul>
                <?php foreach (array_slice($terms, 0, 3) as $term): ?>
                    <li><?php echo $term->name; ?> (ID: <?php echo $term->term_id; ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <!-- END DEBUG INFO -->

    <?php if ($show_heading && !empty($custom_heading)): ?>
        <h3 class="topics-heading"><?php echo esc_html($custom_heading); ?></h3>
    <?php endif; ?>
    
    <?php if (empty($terms) || is_wp_error($terms)): ?>
        <p class="topics-empty-message"><?php echo esc_html($empty_message); ?></p>
    <?php else: ?>
        <p>Found <?php echo count($terms); ?> topics to display.</p>
        
        <ul class="topics-list">
            <?php foreach ($terms as $term): ?>
                <li class="topic-item topic-item-<?php echo $term->term_id; ?>">
                    <a href="<?php echo esc_url(get_term_link($term)); ?>">
                        <?php echo esc_html($term->name); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>