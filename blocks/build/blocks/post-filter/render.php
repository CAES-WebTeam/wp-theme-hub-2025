<?php
// Function to render the category filter block
function myplugin_render_category_filter_block() {
    $categories = get_categories();
    ob_start();
    ?>
    <div class="category-filter">
        <label for="category-select"><?php esc_html_e('Filter by Category:', 'post-filter'); ?></label>
        <select id="category-select">
            <option value=""><?php esc_html_e('All Categories', 'post-filter'); ?></option>
            <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category->term_id); ?>">
                    <?php echo esc_html($category->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
    return ob_get_clean();
}
?>

<div <?php echo get_block_wrapper_attributes(); ?> >
    <?php echo myplugin_render_category_filter_block(); ?>
</div>

<?php
add_action('wp_ajax_filter_posts', 'myplugin_filter_posts');
add_action('wp_ajax_nopriv_filter_posts', 'myplugin_filter_posts');

function myplugin_filter_posts() {
    // Check if the category is set and sanitize it
    if (isset($_GET['category'])) {
        $category_slug = sanitize_text_field($_GET['category']);

        // Set up the query arguments for the Query Loop block
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => 10,
            'category_name' => $category_slug, // Add the category to the query
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                // Output the post template
                get_template_part('template-parts/content', 'excerpt'); // Adjust as needed
            }
        } else {
            echo '<p>' . esc_html__('No posts found.', 'post-filter') . '</p>';
        }

        wp_reset_postdata();
    } else {
        echo '<p>' . esc_html__('No category specified.', 'post-filter') . '</p>';
    }

    wp_die(); // Required for AJAX handlers
}

error_log(print_r($_GET, true));

?>