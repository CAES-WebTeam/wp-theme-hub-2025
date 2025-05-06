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
$snippetPrefix = $block['snippetPrefix'] ?? '';

// Get ACF fields
$authors = get_field('authors', $post_id);
$translators = get_field('translator', $post_id);
$sources = get_field('experts', $post_id);

switch ($type) {
    case 'translators':
        $data = $translators;
        $defaultHeading = "Translators";
        break;
    case 'sources':
        $data = $sources;
        $defaultHeading = "Sources";
        break;
    case 'authors':
    default:
        $data = $authors;
        $defaultHeading = "Authors";
        break;
}

// Ensure function is only defined once
if (!function_exists('process_people')) {
    function process_people($people, $asSnippet = false)
    {
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

        if (!empty($names)) {
            $formatted_names = '';
            $count = count($names);

            if ($count === 1) {
                $formatted_names = $names[0];
            } elseif ($count === 2) {
                $formatted_names = $names[0] . ' and ' . $names[1];
            } else {
                $last = array_pop($names);
                $formatted_names = implode(', ', $names) . ', and ' . $last;
            }

            return $asSnippet ? esc_html($formatted_names) : $output;
        } else {
            return $asSnippet ? '' : $output;
        }
    }
}

// Generate output
if ($data) {
    if ($authorsAsSnippet) {
        echo '<div ' . $attrs . '><p>';
        $snippet_output = process_people($data, true);
        if (!empty($snippet_output)) {
            if (!empty($snippetPrefix)) {
                $snippet_output = '<span class="pub-authors-snippet-prefix">' . esc_html($snippetPrefix) . ' </span><br/>' . $snippet_output;
            }
            echo $snippet_output;
        }
        echo '</p></div>';
    } else {
        echo '<div ' . $attrs . '>';
        // Display heading if enabled
        if ($showHeading) {
            echo '<h2 class="pub-authors-heading is-style-caes-hub-section-heading has-x-large-font-size">' . esc_html($customHeading ?: $defaultHeading) . '</h2>';
        }
        echo '<div class="pub-authors-grid">';
        echo process_people($data, false);
        echo '</div></div>';
    }
}
