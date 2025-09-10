<?php
/**
 * Filtered Topics Block Render Template
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
        // Check URL path first (most reliable)
        $request_uri = $_SERVER['REQUEST_URI'];
        
        if (strpos($request_uri, '/publications/') === 0) {
            return 'publications';
        } elseif (strpos($request_uri, '/shorthand-story/') === 0) {
            return 'shorthand_story';
        } elseif (strpos($request_uri, '/events/') === 0) {
            return 'events';
        }
        
        // Fall back to WordPress query checks
        if (is_singular()) {
            return get_post_type();
        } elseif (is_post_type_archive()) {
            return get_query_var('post_type');
        } elseif (is_tax('topics')) {
            // Check if we're on a topic archive
            if (strpos($request_uri, '/publications/topic/') === 0) {
                return 'publications';
            } elseif (strpos($request_uri, '/shorthand-story/topic/') === 0) {
                return 'shorthand_story';
            } elseif (strpos($request_uri, '/events/topic/') === 0) {
                return 'events';
            }
        }
        
        return 'post'; // Default to post (news)
    }
}

/**
 * Get post count for a specific term and post type
 */
if (!function_exists('caes_filtered_topics_get_term_post_count')) {
    function caes_filtered_topics_get_term_post_count($term_id, $post_type) {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => 'topics',
                    'field' => 'term_id',
                    'terms' => $term_id,
                ),
            ),
        ));
        
        return count($posts);
    }
}

/**
 * Build hierarchical terms list
 */
if (!function_exists('caes_filtered_topics_build_hierarchical_list')) {
    function caes_filtered_topics_build_hierarchical_list($terms, $post_type, $show_post_counts, $parent = 0) {
        $html = '';
        
        foreach ($terms as $term) {
            if ($term->parent == $parent) {
                $post_count = caes_filtered_topics_get_term_post_count($term->term_id, $post_type);
                $term_link = get_term_link($term);
                
                $html .= '<li class="topic-item topic-item-' . $term->term_id . '">';
                $html .= '<a href="' . esc_url($term_link) . '">' . esc_html($term->name) . '</a>';
                if ($show_post_counts) {
                    $html .= ' <span class="post-count">(' . $post_count . ')</span>';
                }
                
                // Check for children
                $children = caes_filtered_topics_build_hierarchical_list($terms, $post_type, $show_post_counts, $term->term_id);
                if ($children) {
                    $html .= '<ul class="topic-children">' . $children . '</ul>';
                }
                
                $html .= '</li>';
            }
        }
        
        return $html;
    }
}

// Determine current context
$current_post_type = $filter_by_context ? caes_filtered_topics_get_current_post_type_context() : null;

// Get terms based on context
if ($current_post_type && $current_post_type !== 'post') {
    // Get only terms that have posts in the current post type
    $post_ids = get_posts(array(
        'post_type' => $current_post_type,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids'
    ));
    
    if (empty($post_ids)) {
        $terms = array();
    } else {
        $terms = get_terms(array(
            'taxonomy' => 'topics',
            'hide_empty' => true,
            'object_ids' => $post_ids
        ));
    }
} else {
    // Show all terms (news/general context)
    $terms = get_terms(array(
        'taxonomy' => 'topics',
        'hide_empty' => true
    ));
}

?>

<div <?php echo $wrapper_attributes; ?>>
    <?php if ($show_heading && !empty($custom_heading)): ?>
        <h3 class="topics-heading"><?php echo esc_html($custom_heading); ?></h3>
    <?php endif; ?>
    
    <?php if (empty($terms) || is_wp_error($terms)): ?>
        <p class="topics-empty-message"><?php echo esc_html($empty_message); ?></p>
    <?php else: ?>
        
        <?php if ($display_as_dropdown): ?>
            <!-- Dropdown Display -->
            <div class="topics-dropdown-wrapper">
                <label class="screen-reader-text" for="topics-dropdown-<?php echo uniqid(); ?>">
                    <?php echo esc_html($custom_heading); ?>
                </label>
                <select 
                    name="topics-dropdown" 
                    id="topics-dropdown-<?php echo uniqid(); ?>" 
                    onchange="if(this.value) location.href=this.value;"
                    class="topics-dropdown"
                >
                    <option value="">Select <?php echo esc_html($custom_heading); ?></option>
                    <?php foreach ($terms as $term): ?>
                        <?php 
                        $post_count = $show_post_counts ? caes_filtered_topics_get_term_post_count($term->term_id, $current_post_type ?: 'post') : 0;
                        $term_link = get_term_link($term);
                        ?>
                        <option value="<?php echo esc_url($term_link); ?>">
                            <?php echo esc_html($term->name); ?>
                            <?php if ($show_post_counts): ?>
                                (<?php echo $post_count; ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
        <?php else: ?>
            <!-- List Display -->
            <ul class="topics-list<?php echo $show_hierarchy ? ' topics-hierarchical' : ''; ?>">
                <?php if ($show_hierarchy): ?>
                    <?php echo caes_filtered_topics_build_hierarchical_list($terms, $current_post_type ?: 'post', $show_post_counts); ?>
                <?php else: ?>
                    <?php foreach ($terms as $term): ?>
                        <?php 
                        $post_count = $show_post_counts ? caes_filtered_topics_get_term_post_count($term->term_id, $current_post_type ?: 'post') : 0;
                        $term_link = get_term_link($term);
                        ?>
                        <li class="topic-item topic-item-<?php echo $term->term_id; ?>">
                            <a href="<?php echo esc_url($term_link); ?>">
                                <?php echo esc_html($term->name); ?>
                            </a>
                            <?php if ($show_post_counts): ?>
                                <span class="post-count">(<?php echo $post_count; ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
        
    <?php endif; ?>
</div>