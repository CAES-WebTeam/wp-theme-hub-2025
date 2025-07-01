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

$classes = trim("$base_class $columns_class $spacing_class");

// Generate inline grid styles for auto layout
$inline_style = '';

if ($displayLayout === 'grid' && $gridItemPosition === 'auto') {
    $width = floatval($gridAutoColumnWidth);
    $unit = esc_attr($gridAutoColumnUnit);
    $min_width = "{$width}{$unit}";
    $inline_style = "grid-template-columns: repeat(auto-fill, minmax(min({$min_width}, 100%), 1fr));";
}


$wrapper_attributes = get_block_wrapper_attributes(); // No layout classes here

// Start timing for query preparation
$start_time = microtime(true);

// Determine which query to run
if ($feed_type === 'hand-picked') {
    // Hand-picked posts logic
    if (empty($post_ids)) {
        // No posts selected, don't display anything (fallback behavior)
        error_log('Hand Picked Post Block: No post IDs provided for hand-picked feed type.');
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
    // Related topics logic
    global $post;

    if (! $post) {
        // error_log( 'Hand Picked Post Block: Post object is not set.' );
        return;
    }

    // Try to get topics taxonomy terms (changed from keywords to topics)
    $topics = wp_get_post_terms($post->ID, 'topics', array('fields' => 'ids'));

    if (is_wp_error($topics)) {
        // error_log( 'Hand Picked Post Block: Error getting topics: ' . $topics->get_error_message() );
        $topics = array();
    }

    // if (empty($topics)) {
    //     // error_log( 'Hand Picked Post Block: No topics found for post ID ' . $post->ID );
    // }

    $block_query_args = array(
        'posts_per_page'      => $number_of_posts,
        'ignore_sticky_posts' => 1,
        'post_type'           => $post_type,
        'post__not_in'        => array($post->ID),
        'post_status'         => 'publish',
        // 'meta_query'          => array(
        //     array(
        //         'key'     => '_thumbnail_id',
        //         'compare' => 'EXISTS'
        //     )
        // )
    );

    // Add taxonomy query only if topics exist
    if (! empty($topics)) { // Changed variable name from $keywords to $topics
        $block_query_args['tax_query'] = array(
            array(
                'taxonomy' => 'topics', // Changed from 'keywords' to 'topics'
                'field'    => 'term_id',
                'terms'    => $topics, // Changed variable name from $keywords to $topics
                'operator' => 'IN',
            ),
        );
    }
}

// Log query preparation time
// $prep_time = microtime( true );
// $preparation_duration = ( $prep_time - $start_time ) * 1000; // Convert to milliseconds
// error_log( sprintf( 'Hand Picked Post Block: Query preparation took %.2f ms', $preparation_duration ) );

// Create the query and time its execution
$query_start_time = microtime(true);
$block_query = new WP_Query($block_query_args);
$query_end_time = microtime(true);

// Calculate and log query execution time
// $query_duration = ( $query_end_time - $query_start_time ) * 1000; // Convert to milliseconds
// $total_duration = ( $query_end_time - $start_time ) * 1000; // Total time from start

// error_log( sprintf( 
//     'Hand Picked Post Block: Query executed in %.2f ms (total: %.2f ms) - Feed type: %s, Post types: %s, Found: %d posts', 
//     $query_duration,
//     $total_duration,
//     $feed_type,
//     implode( ', ', $post_type ),
//     $block_query->found_posts
// ) );

// Log detailed query info for debugging if needed
// if ( $query_duration > 1000 ) { // Log slow queries (over 1 second)
//     error_log( 'Hand Picked Post Block: SLOW QUERY detected - Args: ' . print_r( $block_query_args, true ) );
//     error_log( 'Hand Picked Post Block: SLOW QUERY SQL: ' . $block_query->request );
// }

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

    // Log rendering time
    // $render_end_time = microtime( true );
    // $render_duration = ( $render_end_time - $render_start_time ) * 1000;
    // $complete_duration = ( $render_end_time - $start_time ) * 1000;

    // error_log( sprintf( 
    //     'Hand Picked Post Block: Rendering took %.2f ms (complete block: %.2f ms)', 
    //     $render_duration,
    //     $complete_duration
    // ) );
} else {
    // No posts found - don't display anything (fallback behavior)
    // error_log( sprintf( 
    //     'Hand Picked Post Block: No posts found with query (%.2f ms) - Args: %s', 
    //     $query_duration,
    //     print_r( $block_query_args, true )
    // ) );
}

wp_reset_postdata();
?>