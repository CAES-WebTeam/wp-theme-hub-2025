<?php
// Get attributes with proper defaults to match frontend
$post_type = isset($block->attributes['postType']) ? $block->attributes['postType'] : ['post'];
$feed_type = isset($block->attributes['feedType']) ? $block->attributes['feedType'] : 'related-topics'; // Changed default to 'related-topics' for clarity
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

// Start timing for query preparation
$start_time = microtime(true);

// Determine which query to run
if ($feed_type === 'hand-picked') {
    // Hand-picked posts logic
    if (empty($post_ids)) {
        // No posts selected, don't display anything (fallback behavior)
        // error_log('Hand Picked Post Block: No post IDs provided for hand-picked feed type.');
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
} else {
    // Related topics logic with primary topic priority
    global $post;

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

    // Strategy 1: Try primary_topics first
    $primary_topic = get_field('primary_topics', $post->ID);
    $primary_topic_id = null;

    // error_log('Hand Picked Post Block: Post ID ' . $post->ID . ' - Raw primary_topics field: ' . print_r($primary_topic, true));

    if ($primary_topic && is_array($primary_topic) && !empty($primary_topic)) {
        $first_topic = $primary_topic[0];
        if (is_object($first_topic)) {
            $primary_topic_id = $first_topic->term_id;
        } elseif (is_array($first_topic)) {
            $primary_topic_id = $first_topic['term_id'];
        } else {
            $primary_topic_id = $first_topic;
        }
        // error_log('Hand Picked Post Block: Using primary_topics strategy with ID: ' . $primary_topic_id);
    }

    if ($primary_topic_id) {
        // Two-stage approach: prioritize primary topic matches, then fill with regular topic matches
        
        // Stage 1: Find posts with matching primary_topics (highest priority)
        $primary_query_args = $block_query_args;
        $primary_query_args['meta_query'] = array(
            array(
                'key'     => 'primary_topics',
                'value'   => 's:' . strlen($primary_topic_id) . ':"' . $primary_topic_id . '";',
                'compare' => 'LIKE'
            )
        );
        
        $primary_query = new WP_Query($primary_query_args);
        $primary_posts = $primary_query->posts;
        $found_post_ids = array();
        
        foreach ($primary_posts as $post_obj) {
            $found_post_ids[] = $post_obj->ID;
        }
        
        // error_log('Hand Picked Post Block: Stage 1 - Found ' . count($primary_posts) . ' posts with matching primary_topics');
        
        // Stage 2: Fill remaining slots with regular topic matches (if needed)
        $remaining_slots = $number_of_posts - count($primary_posts);
        $additional_posts = array();
        
        if ($remaining_slots > 0) {
            // Get all topics for current post, excluding "Departments" (ID 1634)
            $all_topics = wp_get_post_terms($post->ID, 'topics', array('fields' => 'ids'));
            $filtered_topics = array_diff($all_topics, array(1634)); // Remove "Departments"
            
            if (!empty($filtered_topics)) {
                $additional_query_args = $block_query_args;
                $additional_query_args['posts_per_page'] = $remaining_slots;
                $additional_query_args['post__not_in'] = array_merge(array($post->ID), $found_post_ids);
                $additional_query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'topics',
                        'field'    => 'term_id',
                        'terms'    => $filtered_topics,
                        'operator' => 'IN',
                        'include_children' => false
                    ),
                );
                
                $additional_query = new WP_Query($additional_query_args);
                $additional_posts = $additional_query->posts;
                
                // error_log('Hand Picked Post Block: Stage 2 - Found ' . count($additional_posts) . ' additional posts from filtered topics');
                // error_log('Hand Picked Post Block: Excluded topics: 1634 (Departments)');
            }
        }
        
        // Combine results: primary_topic posts first, then additional posts
        $combined_posts = array_merge($primary_posts, $additional_posts);
        
        // Create a mock query object with combined results
        $block_query = new WP_Query(array('post__in' => array(-1))); // Empty query
        $block_query->posts = $combined_posts;
        $block_query->found_posts = count($combined_posts);
        $block_query->post_count = count($combined_posts);
        
        // error_log('Hand Picked Post Block: Combined results - Total: ' . count($combined_posts) . ' posts');
        
    } else {
        // No primary topic set - use regular topics taxonomy (excluding Departments)
        $topics = wp_get_post_terms($post->ID, 'topics', array('fields' => 'ids'));
        $filtered_topics = array_diff($topics, array(1634)); // Remove "Departments"
        
        // error_log('Hand Picked Post Block: No primary topic - using filtered topics: ' . print_r($filtered_topics, true));
        
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

// Create the query and time its execution (only if not already created above)
if (!isset($block_query)) {
    $query_start_time = microtime(true);
    $block_query = new WP_Query($block_query_args);
    $query_end_time = microtime(true);
} else {
    $query_end_time = microtime(true);
}

// Log query results
// error_log('Hand Picked Post Block: Query completed for post ID ' . $post->ID . ' - Found: ' . $block_query->found_posts . ' posts');
// error_log('Hand Picked Post Block: Final query args: ' . print_r($block_query_args, true));

if ($block_query->found_posts === 0) {
    // error_log('Hand Picked Post Block: No posts found. SQL query: ' . $block_query->request);
}

if ($block_query->have_posts()) {
    // Start timing for rendering
    $render_start_time = microtime(true);
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