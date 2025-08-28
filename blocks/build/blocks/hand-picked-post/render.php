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

    // DEBUG: Add this debugging output
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<h4>DEBUG INFO for Related Topics Block:</h4>';

    if (! $post) {
        echo '<p><strong>❌ Problem:</strong> Post object is not set.</p>';
        echo '</div>';
        return;
    } else {
        echo '<p><strong>✅ Current Post ID:</strong> ' . $post->ID . '</p>';
        echo '<p><strong>Current Post Title:</strong> ' . get_the_title($post->ID) . '</p>';
    }

    // Try to get topics taxonomy terms
    $topics = wp_get_post_terms($post->ID, 'topics', array('fields' => 'ids'));

    // DEBUG: Check what WordPress is actually using for tax_query
    $debug_query = new WP_Query(array(
        'posts_per_page' => 1,
        'post_type' => 'post',
        'tax_query' => array(
            array(
                'taxonomy' => 'topics',
                'field' => 'term_id',
                'terms' => $topics,
                'operator' => 'IN',
            ),
        ),
    ));

    echo '<div style="background: #e8f5e8; padding: 10px; margin: 10px 0; border: 1px solid #4caf50;">';
    echo '<p><strong>DIRECT DATABASE CHECK:</strong></p>';
    echo '<p><strong>Input term IDs:</strong> ' . implode(', ', $topics) . '</p>';
    echo '<p><strong>WordPress converted to term_taxonomy_ids:</strong> Check SQL below...</p>';
    echo '<pre style="font-size: 12px;">' . $debug_query->request . '</pre>';
    echo '</div>';

    if (is_wp_error($topics)) {
        echo '<p><strong>❌ Error getting topics:</strong> ' . $topics->get_error_message() . '</p>';
        $topics = array();
    } else {
        echo '<p><strong>Topics Found (IDs):</strong> ' . (empty($topics) ? 'NONE' : implode(', ', $topics)) . '</p>';

        if (!empty($topics)) {
            // Get topic names for display
            $topic_names = wp_get_post_terms($post->ID, 'topics', array('fields' => 'names'));
            echo '<p><strong>Topic Names:</strong> ' . implode(', ', $topic_names) . '</p>';
        }
    }

    echo '<p><strong>Post Types to Search:</strong> ' . implode(', ', $post_type) . '</p>';
    echo '<p><strong>Number of Posts Requested:</strong> ' . $number_of_posts . '</p>';
    echo '</div>';
    // END DEBUG

    $block_query_args = array(
        'posts_per_page'      => $number_of_posts,
        'ignore_sticky_posts' => 1,
        'post_type'           => $post_type,
        'post__not_in'        => array($post->ID),
        'post_status'         => 'publish',
    );

    // Add taxonomy query only if topics exist
    if (! empty($topics)) {
        $block_query_args['tax_query'] = array(
            array(
                'taxonomy' => 'topics',
                'field'    => 'term_id',
                'terms'    => $topics,
                'operator' => 'IN',
                'include_children' => false
            ),
        );

        // DEBUG: Show that tax_query was added
        echo '<div style="background: #e8f5e8; padding: 10px; margin: 10px 0; border: 1px solid #4caf50;">';
        echo '<p><strong>✅ Tax Query Added:</strong> Looking for posts with topic IDs: ' . implode(', ', $topics) . '</p>';
        echo '</div>';
    } else {
        // DEBUG: Show that no tax_query was added
        echo '<div style="background: #ffe8e8; padding: 10px; margin: 10px 0; border: 1px solid #f44336;">';
        echo '<p><strong>⚠️ No Tax Query:</strong> No topics found, so related posts query will show random posts from selected post types</p>';
        echo '</div>';
    }
}

// Log query preparation time
// $prep_time = microtime( true );
// $preparation_duration = ( $prep_time - $start_time ) * 1000; // Convert to milliseconds
// error_log( sprintf( 'Hand Picked Post Block: Query preparation took %.2f ms', $preparation_duration ) );

// Create the query and time its execution
$query_start_time = microtime(true);
$block_query = new WP_Query($block_query_args);

// DEBUG: Show actual SQL query
echo '<div style="background: #ffebee; padding: 10px; margin: 10px 0; border: 1px solid #f44336;">';
echo '<h4>ACTUAL SQL QUERY:</h4>';
echo '<pre style="font-size: 12px; overflow-x: auto;">' . $block_query->request . '</pre>';
echo '</div>';

// DEBUG: Show query results
echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffc107;">';
echo '<p><strong>Query Results:</strong> Found ' . $block_query->found_posts . ' posts</p>';
if ($block_query->found_posts > 0) {
    echo '<p><strong>Post IDs Found:</strong> ';
    $found_ids = array();
    foreach ($block_query->posts as $found_post) {
        $found_ids[] = $found_post->ID . ' (' . get_the_title($found_post->ID) . ')';
    }
    echo implode(', ', $found_ids) . '</p>';
}
echo '</div>';

// DEBUG: Check what topics the found posts actually have
echo '<div style="background: #e1f5fe; padding: 10px; margin: 10px 0; border: 1px solid #0288d1;">';
echo '<h4>TOPIC ANALYSIS for Found Posts:</h4>';
if ($block_query->found_posts > 0) {
    $temp_posts = $block_query->posts;
    foreach (array_slice($temp_posts, 0, 3) as $found_post) { // Just check first 3
        echo '<p><strong>Post: ' . get_the_title($found_post->ID) . ' (ID: ' . $found_post->ID . ')</strong></p>';

        $post_topics = wp_get_post_terms($found_post->ID, 'topics', array('fields' => 'all'));
        if (!empty($post_topics)) {
            echo '<ul>';
            foreach ($post_topics as $topic) {
                $is_match = in_array($topic->term_id, $topics) ? ' ✅ MATCH' : '';
                echo '<li>ID: ' . $topic->term_id . ' - Name: "' . $topic->name . '"' . $is_match . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: red;">No topics found for this post!</p>';
        }
        echo '<hr>';
    }
}
echo '</div>';
// END DEBUG

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