<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? '' : get_block_wrapper_attributes();

// Get the block attributes (assuming they're passed in as $block)
$authorsAsSnippet = $block['authorsAsSnippet'] ?? false;
$showHeading = $block['showHeading'] ?? false;
$customHeading = $block['customHeading'] ?? '';
$type = $block['type'] ?? 'authors';

// Get authors and translators from ACF fields
$authors = get_field('authors', $post_id);
$translators = get_field('translators', $post_id);

// Determine which list to use
$data = ($type === 'translators') ? $translators : $authors;
$defaultHeading = ($type === 'translators') ? "Translators" : "Authors";

// Ensure function is only defined once
if (!function_exists('process_people')) {
    function process_people($people, $asSnippet = false) {
        $names = [];
        $output = '';

        if ($people) {
            foreach ($people as $item) {
                $type = strtolower($item['type'] ?? '');
                $user = $item['user'] ?? null;
                $custom = $item['custom_user'] ?? [];

                if ($type === 'user' && !empty($user)) {
                    $user_id = is_array($user) ? ($user['ID'] ?? null) : $user;
                    if ($user_id) {
                        $first_name = get_the_author_meta('first_name', $user_id);
                        $last_name = get_the_author_meta('last_name', $user_id);
                        $profile_url = get_author_posts_url($user_id);
                        $title = get_the_author_meta('title', $user_id);

                        if ($asSnippet) {
                            $names[] = trim("$first_name $last_name");
                        } else {
                            $output .= '<div class="pub-author">';
                            $output .= '<a class="pub-author-name" href="' . esc_url($profile_url) . '">' . esc_html(trim("$first_name $last_name")) . '</a>';
                            if ($title) {
                                $output .= '<p class="pub-author-title">' . esc_html($title) . '</p>';
                            }
                            $output .= '</div>';
                        }
                    }
                } elseif ($type === 'custom' && !empty($custom['first_name']) && !empty($custom['last_name'])) {
                    $first_name = $custom['first_name'];
                    $last_name = $custom['last_name'];
                    $title = $custom['title'] ?? '';

                    if ($asSnippet) {
                        $names[] = trim("$first_name $last_name");
                    } else {
                        $output .= '<div class="pub-author">';
                        $output .= '<a class="pub-author-name" href="#">' . esc_html(trim("$first_name $last_name")) . '</a>';
                        if ($title) {
                            $output .= '<p class="pub-author-title">' . esc_html($title) . '</p>';
                        }
                        $output .= '</div>';
                    }
                }
            }
        }

        return $asSnippet ? (!empty($names) ? '<p class="pub-authors-snippet">' . esc_html(implode(', ', $names)) . '</p>' : '') : $output;
    }
}

// Generate output
if ($data) {
    if ($authorsAsSnippet) {
        echo process_people($data, true);
    } else {
        echo '<div ' . $attrs . '>';
        echo '<div class="pub-authors-grid">';
        
        // Display heading if enabled
        if ($showHeading) {
            echo '<h2 class="pub-authors-heading is-style-caes-hub-full-underline">' . esc_html($customHeading ?: $defaultHeading) . '</h2>';
        }

        echo process_people($data, false);
        
        echo '</div></div>';
    }
}
?>
