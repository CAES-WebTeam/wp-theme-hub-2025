<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? '' : get_block_wrapper_attributes();

// Get the block attributes (assuming they're passed in as $attributes)
$authorsAsSnippet = $block['authorsAsSnippet'];

// Get authors from ACF field
$authors = get_field('authors', $post_id);

// Initialize author names array for snippet view
$author_names = [];

if ($authors) {
    if ($authorsAsSnippet) {
        // Snippet format: Single <p> with authors separated by commas
        foreach ($authors as $item) {
            $type = strtolower($item['type']); // Normalize case
            $user = $item['user'] ?? null;
            $custom = $item['custom_user'] ?? [];

            if ($type === 'user' && !empty($user)) {
                $user_id = is_array($user) ? ($user['ID'] ?? null) : $user;
                if ($user_id) {
                    $first_name = get_the_author_meta('first_name', $user_id);
                    $last_name = get_the_author_meta('last_name', $user_id);
                    $author_names[] = trim("$first_name $last_name");
                }
            } elseif ($type === 'custom' && !empty($custom['first_name']) && !empty($custom['last_name'])) {
                $author_names[] = trim($custom['first_name'] . ' ' . $custom['last_name']);
            }
        }

        if (!empty($author_names)) {
            echo '<p class="pub-authors-snippet">' . esc_html(implode(', ', $author_names)) . '</p>';
        }
    } else {
        // Full format: Structured author blocks
        echo '<div ' . $attrs . '><div class="pub-authors-grid">';
        
        foreach ($authors as $item) {
            $type = strtolower($item['type']);
            $user = $item['user'] ?? null;
            $custom = $item['custom_user'] ?? [];

            if ($type === 'user' && !empty($user)) {
                $user_id = is_array($user) ? ($user['ID'] ?? null) : $user;
                if ($user_id) {
                    $first_name = get_the_author_meta('first_name', $user_id);
                    $last_name = get_the_author_meta('last_name', $user_id);
                    $title = get_the_author_meta('title', $user_id);
                    $profile_url = get_author_posts_url($user_id);

                    echo '<div class="pub-author">';
                    echo '<a class="pub-author-name" href="' . esc_url($profile_url) . '">' . esc_html(trim("$first_name $last_name")) . '</a>';
                    if ($title) {
                        echo '<p class="pub-author-title">' . esc_html($title) . '</p>';
                    }
                    echo '</div>';
                }
            } elseif ($type === 'custom' && !empty($custom['first_name']) && !empty($custom['last_name'])) {
                $first_name = $custom['first_name'];
                $last_name = $custom['last_name'];
                $title = $custom['title'] ?? '';

                echo '<div class="pub-author">';
                echo '<a class="pub-author-name" href="#">' . esc_html(trim("$first_name $last_name")) . '</a>';
                if ($title) {
                    echo '<p class="pub-author-title">' . esc_html($title) . '</p>';
                }
                echo '</div>';
            }
        }

        echo '</div></div>';
    }
}
?>
