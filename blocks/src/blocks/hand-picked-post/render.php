<?php
// Get attributes with proper defaults to match frontend
$post_type = isset($block->attributes['postType']) ? $block->attributes['postType'] : ['post'];
$feed_type = isset($block->attributes['feedType']) ? $block->attributes['feedType'] : 'related-topics';
$number_of_posts = isset($block->attributes['numberOfItems']) ? $block->attributes['numberOfItems'] : 3;
$post_ids = isset($block->attributes['postIds']) ? $block->attributes['postIds'] : [];
$query_id = isset($block->attributes['queryId']) ? $block->attributes['queryId'] : 100;
$displayLayout = isset($block->attributes['displayLayout']) ? $block->attributes['displayLayout'] : 'list';
$columns = isset($block->attributes['columns']) ? $block->attributes['columns'] : 3;
$customGapStep = isset($block->attributes['customGapStep']) ? $block->attributes['customGapStep'] : 3;
$gridItemPosition = isset($block->attributes['gridItemPosition']) ? $block->attributes['gridItemPosition'] : 'manual';
$gridAutoColumnWidth = isset($block->attributes['gridAutoColumnWidth']) ? $block->attributes['gridAutoColumnWidth'] : 12;
$gridAutoColumnUnit = isset($block->attributes['gridAutoColumnUnit']) ? $block->attributes['gridAutoColumnUnit'] : 'rem';

// Ensure post_type is an array (matching frontend logic)
if (! is_array($post_type)) {
    $post_type = array($post_type);
}

// Layout settings
$base_class = $displayLayout === 'grid' ? 'hand-picked-post-grid' : 'hand-picked-post-list';
$columns_class = $displayLayout === 'grid' ? 'columns-' . intval($columns) : '';

// Spacing classes
$SPACING_CLASSES = array(
    1 => 'gap-wp-preset-spacing-20',
    2 => 'gap-wp-preset-spacing-30',
    3 => 'gap-wp-preset-spacing-40',
    4 => 'gap-wp-preset-spacing-50',
    5 => 'gap-wp-preset-spacing-60',
    6 => 'gap-wp-preset-spacing-70',
    7 => 'gap-wp-preset-spacing-80',
);

$spacing_class = isset($SPACING_CLASSES[$customGapStep]) ? $SPACING_CLASSES[$customGapStep] : '';
$classes = trim("$base_class $columns_class $spacing_class");

// Generate inline grid styles for auto layout
$inline_style = '';

if ($displayLayout === 'grid' && $gridItemPosition === 'auto') {
    $width = floatval($gridAutoColumnWidth);
    $unit = esc_attr($gridAutoColumnUnit);
    $min_width = "{$width}{$unit}";
    $inline_style = "grid-template-columns: repeat(auto-fill, minmax(min({$min_width}, 100%), 1fr));";
}

$wrapper_attributes = get_block_wrapper_attributes();

// PERFORMANCE FIX: Add caching for related posts
global $post;
if ($feed_type === 'related-topics' && $post) {
    $cache_key = 'related_posts_' . $post->ID . '_' . md5(serialize([
        $feed_type, $number_of_posts, $post_type, $displayLayout, $columns, $customGapStep
    ]));
    $cached_query = wp_cache_get($cache_key, 'caes_related_posts');
    
    if ($cached_query !== false) {
        $block_query = $cached_query;
    }
}

// Only run query if not cached
if (!isset($block_query)) {
    // Determine which query to run
    if ($feed_type === 'hand-picked') {
        // Hand-picked posts logic
        if (empty($post_ids)) {
            return;
        }

        $block_query_args = array(
            'posts_per_page'      => count($post_ids),
            'ignore_sticky_posts' => 1,
            'post_type'           => $post_type,
            'post__in'            => $post_ids,
            'orderby'             => 'post__in',
            'post_status'         => 'publish',
        );
        
        $block_query = new WP_Query($block_query_args);
    } else {
        // Related topics logic with primary topic priority
        if (! $post) {
            return;
        }

        $block_query_args = array(
            'posts_per_page'      => $number_of_posts,
            'ignore_sticky_posts' => 1,
            'post_type'           => $post_type,
            'post__not_in'        => array($post->ID),
            'post_status'         => 'publish',
            'orderby'             => 'date',
            'order'               => 'DESC'
        );

        // PERFORMANCE FIX: Get primary topic more efficiently
        $primary_topic = get_field('primary_topics', $post->ID);
        $primary_topic_id = null;

        if ($primary_topic && is_array($primary_topic) && !empty($primary_topic)) {
            $first_topic = $primary_topic[0];
            if (is_object($first_topic)) {
                $primary_topic_id = $first_topic->term_id;
            } elseif (is_array($first_topic)) {
                $primary_topic_id = $first_topic['term_id'];
            } else {
                $primary_topic_id = $first_topic;
            }
        }

        if ($primary_topic_id) {
            // PERFORMANCE FIX: Use a single optimized query instead of multiple queries
            // Get all topics for current post, excluding "Departments" (ID 1634)
            $all_topics = wp_get_post_terms($post->ID, 'topics', array('fields' => 'ids'));
            $filtered_topics = array_diff($all_topics, array(1634)); // Remove "Departments"
            
            if (!empty($filtered_topics)) {
                // Single tax_query that prioritizes primary topic but includes all topics
                $block_query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'topics',
                        'field'    => 'term_id',
                        'terms'    => $filtered_topics,
                        'operator' => 'IN',
                        'include_children' => false
                    ),
                );
                
                // PERFORMANCE FIX: Use orderby to prioritize primary topic matches
                // Custom ordering to put primary topic matches first
                add_filter('posts_orderby', function($orderby, $query) use ($primary_topic_id) {
                    global $wpdb;
                    if (!$query->is_main_query() && $query->get('post_type')) {
                        // Create custom ordering that prioritizes posts with the primary topic
                        $custom_order = "CASE WHEN EXISTS (
                            SELECT 1 FROM {$wpdb->term_relationships} tr 
                            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                            WHERE tr.object_id = {$wpdb->posts}.ID 
                            AND tt.term_id = {$primary_topic_id}
                            AND tt.taxonomy = 'topics'
                        ) THEN 0 ELSE 1 END, {$wpdb->posts}.post_date DESC";
                        return $custom_order;
                    }
                    return $orderby;
                }, 10, 2);
                
                $block_query = new WP_Query($block_query_args);
                
                // Remove the filter after use
                remove_all_filters('posts_orderby');
            } else {
                $block_query = new WP_Query($block_query_args);
            }
        } else {
            // No primary topic set - use regular topics taxonomy (excluding Departments)
            $topics = wp_get_post_terms($post->ID, 'topics', array('fields' => 'ids'));
            $filtered_topics = array_diff($topics, array(1634)); // Remove "Departments"
            
            if (!is_wp_error($filtered_topics) && !empty($filtered_topics)) {
                $block_query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'topics',
                        'field'    => 'term_id',
                        'terms'    => $filtered_topics,
                        'operator' => 'IN',
                        'include_children' => false
                    ),
                );
                $block_query = new WP_Query($block_query_args);
            } else {
                $block_query = new WP_Query($block_query_args);
            }
        }
    }

    // PERFORMANCE FIX: Cache the query results
    if ($feed_type === 'related-topics' && $post && isset($cache_key)) {
        wp_cache_set($cache_key, $block_query, 'caes_related_posts', 300); // Cache for 5 minutes
    }
}

if ($block_query->have_posts()) {
?>
    <div <?php echo wp_kses_post($wrapper_attributes); ?>>
        <div class="<?php echo esc_attr($classes); ?>" style="<?php echo esc_attr($inline_style); ?>">

            <?php
            while ($block_query->have_posts()) {
                $block_query->the_post();

                $block_context = array(
                    'caes-hub/hand-picked-post/postIds' => $post_ids,
                    'caes-hub/hand-picked-post/postType' => $post_type,
                    'caes-hub/hand-picked-post/queryId' => $query_id,
                    'postId' => get_the_ID(),
                    'postType' => get_post_type(),
                );

                if (! empty($block->inner_blocks)) {
                    foreach ($block->inner_blocks as $inner_block) {
                        $inner_block_instance = new WP_Block($inner_block->parsed_block, $block_context);
                        echo $inner_block_instance->render();
                    }
                }
            }
            ?>
        </div>
    </div>
<?php
}

wp_reset_postdata();
?>