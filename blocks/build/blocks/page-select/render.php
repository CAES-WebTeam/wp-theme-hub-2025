<?php
/**
 * Page Select Block Template
 * 
 * Displays a dropdown navigation for paginated content.
 * Only renders if the post has <!--nextpage--> breaks.
 */

// Get the current post
$post_id = get_the_ID();
$post_content = get_post_field('post_content', $post_id);

// Check if there are page breaks
if (!preg_match('/<!--nextpage-->/', $post_content)) {
    // No page breaks found, don't render anything
    return;
}

// Split content by page breaks to count pages
$pages = preg_split('/<!--nextpage-->/', $post_content);
$total_pages = count($pages);

// If only one page, don't render
if ($total_pages < 2) {
    return;
}

// Get current page number (1-indexed)
$current_page = absint(get_query_var('page'));
if ($current_page < 1) {
    $current_page = 1;
}

// Get base URL for the publication
$base_url = get_permalink($post_id);
// Remove any existing page number from the URL
$base_url = preg_replace('/\/\d+\/?$/', '', $base_url);

// Build the select options
$options = '';
for ($i = 1; $i <= $total_pages; $i++) {
    // Build URL for each page
    $page_url = ($i === 1) ? $base_url : $base_url . '/' . $i;
    
    // Check if this is the current page
    $selected = ($i === $current_page) ? ' selected' : '';
    
    // Build option
    $options .= sprintf(
        '<option value="%s"%s>Page %d</option>',
        esc_url($page_url),
        $selected,
        $i
    );
}

// Get block wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes();
?>

<div <?php echo $wrapper_attributes; ?>>
    <label for="publication-page-select-<?php echo esc_attr($post_id); ?>">
        Jump to page:
    </label>
    <div class="select-wrapper">
        <select 
            id="publication-page-select-<?php echo esc_attr($post_id); ?>"
            onchange="document.location.href=this.options[this.selectedIndex].value;"
            aria-label="Navigate to a different page">
            <?php echo $options; ?>
        </select>
    </div>
</div>