<?php
$post_id = get_the_ID();
$post_content = get_post_field('post_content', $post_id);
$showSubheadings = $attributes['showSubheadings'];
$title = $attributes['tocHeading'];
$listStyle = $attributes['listStyle'];
$popout = $attributes['popout'];
$topOfContentAnchor = $attributes['topOfContentAnchor'];
$anchorLinkText = $attributes['anchorLinkText'];
$anchorLinkText = $anchorLinkText ? $anchorLinkText : 'Top of Content';
$anchorLinkText = esc_html($anchorLinkText);

// Get current page number (1-indexed)
$current_page = get_query_var('page') ? get_query_var('page') : 1;

// Split content by <!--nextpage--> to get all pages
$pages = preg_split('/<!--nextpage-->/', $post_content);
$total_pages = count($pages);

// Check if there are any headings at all
$pattern = $showSubheadings ? '/<h[2-6][^>]*>.*?<\/h[2-6]>/' : '/<h2[^>]*>.*?<\/h2>/';
if (!preg_match($pattern, $post_content)) {
    return '';
}

// Extract headings from all pages
$headings_data = [];
$used_ids = [];

function generate_unique_id($text, &$used_ids) {
    $base_id = sanitize_title($text);
    $unique_id = $base_id;
    $count = 2;
    
    while (in_array($unique_id, $used_ids)) {
        $unique_id = $base_id . '-' . $count;
        $count++;
    }
    
    $used_ids[] = $unique_id;
    return $unique_id;
}

foreach ($pages as $page_num => $page_content) {
    $page_index = $page_num + 1; // Convert to 1-indexed
    
    // Extract headings from this page
    $heading_pattern = $showSubheadings ? '/<(h[2-6])[^>]*>(.*?)<\/h[2-6]>/i' : '/<(h2)[^>]*>(.*?)<\/h2>/i';
    preg_match_all($heading_pattern, $page_content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $level = (int) substr($match[1], 1); // Extract number from h2, h3, etc.
        $text = strip_tags($match[2]);
        
        // Skip if this is the TOC heading itself
        if ($text === $title) {
            continue;
        }
        
        // Skip template literals and other code-like headings
        if (strpos($text, '${') !== false || strpos($text, 'plant.') !== false) {
            continue;
        }
        
        $id = generate_unique_id($text, $used_ids);
        
        $headings_data[] = [
            'text' => $text,
            'level' => $level,
            'id' => $id,
            'page' => $page_index
        ];
    }
}

// If no headings found, don't render the block
if (empty($headings_data)) {
    return '';
}

// Encode headings data as JSON for JavaScript
$headings_json = wp_json_encode($headings_data);
?>
<div
    <?php echo get_block_wrapper_attributes(); ?>
    data-show-subheadings="<?php echo esc_attr($showSubheadings); ?>"
    data-list-style="<?php echo esc_attr($listStyle); ?>"
    data-title="<?php echo esc_attr($title); ?>"
    data-popout="<?php echo esc_attr($popout); ?>"
    data-top-of-content-anchor="<?php echo esc_attr($topOfContentAnchor); ?>"
    data-current-page="<?php echo esc_attr($current_page); ?>"
    data-total-pages="<?php echo esc_attr($total_pages); ?>"
    data-headings='<?php echo esc_attr($headings_json); ?>'
    <?php if ($topOfContentAnchor) : ?>
    data-anchor-link-text="<?php echo esc_attr($anchorLinkText); ?>"
    <?php endif; ?>>
    <h2><?php echo esc_html($title); ?></h2>
</div>