<?php
$post_type = isset( $block->attributes['postType'] ) ? $block->attributes['postType'] : 'post';
$feed_type = isset( $block->attributes['feedType'] ) ? $block->attributes['feedType'] : 'related-keywords';
$number_of_posts = isset( $block->attributes['numberOfItems'] ) ? $block->attributes['numberOfItems'] : 3;

$wrapper_attributes = get_block_wrapper_attributes();

// Determine which query to run
if ( $feed_type === 'hand-picked' ) {
    $post_ids = isset( $block->attributes['postIds'] ) ? $block->attributes['postIds'] : [];

    $block_query_args = array(
        'posts_per_page'      => count( $post_ids ),
        'ignore_sticky_posts' => 1,
        'post_type'           => $post_type,
        'post__in'            => $post_ids,
        'orderby'             => 'post__in',
    );
} else {
    // Related keywords logic
    global $post;

    if ( ! $post ) {
        error_log( 'Post object is not set.' );
    } else {
        error_log( 'Current post ID: ' . $post->ID );
    }

    // Try both versions of the taxonomy slug
    $keywords = wp_get_post_terms( $post->ID, 'keywords', array( 'fields' => 'ids' ) );
    if ( empty( $keywords ) ) {
        error_log( 'No terms found using taxonomy "keyword". Trying "keywords"...' );
        $keywords = wp_get_post_terms( $post->ID, 'keywords', array( 'fields' => 'ids' ) );
    }

    if ( empty( $keywords ) ) {
        error_log( 'No keywords found for post ID ' . $post->ID );
    } else {
        error_log( 'Found keyword term IDs: ' . implode( ', ', $keywords ) );
    }

    $block_query_args = array(
        'posts_per_page'      => $number_of_posts,
        'ignore_sticky_posts' => 1,
        'post_type'           => $post_type,
        'post__not_in'        => array( $post->ID ),
    );

    if ( ! empty( $keywords ) ) {
        $block_query_args['tax_query'] = array(
            array(
                'taxonomy' => 'keywords', // Keep this consistent with the correct taxonomy
                'field'    => 'term_id',
                'terms'    => $keywords,
            ),
        );
    }

    error_log( 'Final query args: ' . print_r( $block_query_args, true ) );
}

$block_query = new WP_Query( $block_query_args );

if ( $block_query->have_posts() ) {
    $classnames = get_post_class();
    ?>
    <div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
        <?php
        while ( $block_query->have_posts() ) {
            $block_query->the_post();

            $block_instance = $block->parsed_block;
            $block_instance['blockName'] = 'core/null';

            echo (
                new WP_Block(
                    $block_instance,
                    array(
                        'postType' => get_post_type(),
                        'postId'   => get_the_ID(),
                    )
                )
            )->render( array( 'dynamic' => false ) );
        }
        ?>
    </div>
    <?php
} else {
    error_log( 'No posts found with query: ' . print_r( $block_query_args, true ) );
}

wp_reset_postdata();
?>
