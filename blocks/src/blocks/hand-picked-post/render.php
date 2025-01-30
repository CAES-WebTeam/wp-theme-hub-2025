<?php
/**
 * The template for the server-side rendering of single post block.
 *
 * @since 1.0
 *
 * @version 1.0
 *
 * @package Featured Content Block
 */

// Get the selected post type (falling back to 'post' if not set)
$post_type = isset( $block->attributes['postType'] ) ? $block->attributes['postType'] : 'post';

// Set up the query args to fetch the post by the selected post type and post ID
$block_query_args = array(
    'posts_per_page'      => 1,
    'ignore_sticky_posts' => 1,
    'post_type'           => $post_type, // Use the dynamic post type
    'post__in'            => array( $block->attributes['postId'] ),
);

$block_query = new WP_Query( $block_query_args );

if ( $block_query->have_posts() ) {
    $classnames         = get_post_class();
    $wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $classnames ) ) );
    ?>
    <div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
        <?php
        while ( $block_query->have_posts() ) {
            $block_query->the_post();

            $block_instance = $block->parsed_block;
            $block_instance['blockName'] = 'core/null';

            // Render the block instance with the current post ID and post type
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
}

wp_reset_postdata();
?>
