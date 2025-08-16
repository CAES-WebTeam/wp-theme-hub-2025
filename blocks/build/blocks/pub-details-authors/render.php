<?php

// Get the current post ID
$post_id = get_the_ID();

// Attributes for wrapper
$attrs = $is_preview ? '' : get_block_wrapper_attributes();

// Look up classes from attributes
$classnames = $block['className'] ?? '';
// Check if 'is-style-caes-hub-compact' is applied
$is_compact = strpos($classnames, 'is-style-caes-hub-compact') !== false;
// If we do use compact style, adjust heading 2 styles
if (! $is_compact) {
    $headingStyles = 'pub-authors-heading is-style-caes-hub-section-heading has-x-large-font-size';
} else {
    $headingStyles = 'pub-authors-heading';
}

// Get the block attributes
$displayVersion = $block['displayVersion'] ?? 'names-only';
$showHeading = $block['showHeading'] ?? false;
$customHeading = $block['customHeading'] ?? '';
$type = $block['type'] ?? 'authors';
$snippetPrefix = $block['snippetPrefix'] ?? '';
$snippetPrefixPosition = $block['snippetPrefixPosition'] ?? 'above';
$grid = $block['grid'] ?? false;

// Adjust logic flags
$authorNamesOnly = $displayVersion === 'names-only';
$oneLine = $displayVersion === 'names-and-titles';
$useGrid = $displayVersion === 'name-and-title-below' && $grid;

// Get ACF fields
$authors = get_field('authors', $post_id, false);
$translators = get_field('translator', $post_id, false);
$sources = get_field('experts', $post_id, false);

switch ($type) {
    case 'translators':
        $data = $translators;
        $translator_count = is_array($translators) ? count($translators) : 0;
        if ($is_compact) {
            $defaultHeading = $translator_count === 1 ? "Meet the Translator" : "Meet the Translators";
        } else {
            $defaultHeading = "Translators";
        }
        break;
    case 'sources':
        $data = $sources;
        $expert_count = is_array($sources) ? count($sources) : 0;
        if ($is_compact) {
            $defaultHeading = $expert_count === 1 ? "Meet the Expert" : "Meet the Experts";
        } else {
            $defaultHeading = "Expert Sources";
        }
        break;
    case 'authors':
    default:
        $data = $authors;
        $author_count = is_array($authors) ? count($authors) : 0;
        if ($is_compact) {
            $defaultHeading = $author_count === 1 ? "Meet the Author" : "Meet the Authors";
        } else {
            $defaultHeading = "Authors";
        }
        break;
}

// Ensure function is only defined once
if (!function_exists('process_people')) {
    function process_people($people, $asSnippet = false, $oneLine = false)
    {
        $names = [];
        $output = '';

        // SIMPLE DEBUGGING - Remove this after testing
        echo '<pre style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 12px;">';
        echo 'DEBUG: People data structure:';
        print_r($people);
        echo '</pre>';

        if ($people) {
            foreach ($people as $index => $item) {
                // SIMPLE DEBUGGING - Remove this after testing
                echo '<pre style="background: #ffffcc; padding: 5px; margin: 5px 0; font-size: 11px;">';
                echo "DEBUG: Item #$index:";
                print_r($item);
                echo '</pre>';
                // Check the type field to determine if this is a user or custom entry
                $entry_type = $item['type'] ?? '';
                
                $first_name = '';
                $last_name = '';
                $title = '';
                $profile_url = '';
                
                if ($entry_type === 'custom') {
                    // Handle custom user entry
                    $custom_user = $item['custom_user'] ?? [];
                    $first_name = $custom_user['first_name'] ?? '';
                    $last_name = $custom_user['last_name'] ?? '';
                    $title = $custom_user['title'] ?? '';
                    // No profile URL for custom users
                    $profile_url = '';
                } else {
                    // Handle WordPress user selection (existing logic)
                    $user_id = null;

                    // First check for 'user' key (standard ACF format)
                    if (isset($item['user']) && !empty($item['user'])) {
                        $user_id = is_array($item['user']) ? ($item['user']['ID'] ?? null) : $item['user'];
                    }

                    // Fallback: check for numeric values in any field (ACF internal field keys)
                    if (empty($user_id) && is_array($item)) {
                        foreach ($item as $key => $value) {
                            if (is_numeric($value) && $value > 0) {
                                $user_id = $value;
                                break;
                            }
                        }
                    }

                    if ($user_id && is_numeric($user_id)) {
                        $first_name = get_the_author_meta('first_name', $user_id);
                        $last_name = get_the_author_meta('last_name', $user_id);
                        $profile_url = get_author_posts_url($user_id);
                        $public_title = get_field('public_friendly_title', 'user_' . $user_id);
                        $regular_title = get_the_author_meta('title', $user_id);
                        $title = !empty($public_title) ? $public_title : $regular_title;
                    }
                }

                // Only proceed if we have at least a name
                if (!empty($first_name) || !empty($last_name)) {
                    $full_name = trim("$first_name $last_name");
                    
                    if ($asSnippet) {
                        $names[] = $full_name;
                    } else {
                        if ($oneLine) {
                            $output .= '<p class="pub-author-oneline">';
                            if (!empty($profile_url)) {
                                $output .= '<a class="pub-author-name" href="' . esc_url($profile_url) . '">' . esc_html($full_name) . '</a>';
                            } else {
                                $output .= '<span class="pub-author-name">' . esc_html($full_name) . '</span>';
                            }
                            if ($title) {
                                $output .= ', ' . esc_html($title);
                            }
                            $output .= '</p>';
                        } else {
                            $output .= '<div class="pub-author">';
                            if (!empty($profile_url)) {
                                $output .= '<a class="pub-author-name" href="' . esc_url($profile_url) . '">' . esc_html($full_name) . '</a>';
                            } else {
                                $output .= '<span class="pub-author-name">' . esc_html($full_name) . '</span>';
                            }
                            if ($title) {
                                $output .= '<p class="pub-author-title">' . esc_html($title) . '</p>';
                            }
                            $output .= '</div>';
                        }
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
    echo '<div ' . $attrs . '>';

    if ($showHeading) {
        echo '<h2 class="' . $headingStyles . '">' . esc_html($customHeading ?: $defaultHeading) . '</h2>';
    }

    if ($authorNamesOnly) {
        $snippet_output = process_people($data, true);
        if (!empty($snippet_output)) {
            if (!empty($snippetPrefix) && $snippetPrefixPosition === 'above') {
                $snippet_output = '<p><span class="pub-authors-snippet-prefix">' . esc_html($snippetPrefix) . ' </span><br/>' . $snippet_output . '</p>';
            } elseif (!empty($snippetPrefix) && $snippetPrefixPosition === 'same-line') {
                $snippet_output = '<p><span class="pub-authors-snippet-prefix">' . esc_html($snippetPrefix) . ' </span>' . $snippet_output . '</p>';
            } else {
                $snippet_output = '<p>' . $snippet_output . '</p>';
            }
            echo $snippet_output;
        }
    } else {
        echo '<div class="pub-authors-wrap' . ($useGrid ? ' pub-authors-grid' : '') . '">';
        echo process_people($data, false, $oneLine);
        echo '</div>';
    }

    echo '</div>';
}