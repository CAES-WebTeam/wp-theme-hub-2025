<?php
/**
 * Breadcrumbs block render template.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

// Don't show breadcrumbs on front page unless it's a static page showing posts
if (is_front_page() && is_home()) {
    return '';
}

// Get block attributes with defaults
$show_home = $attributes['showHome'] ?? true;
$home_text = $attributes['homeText'] ?? __('Home', 'your-textdomain');
$max_depth = $attributes['maxDepth'] ?? 0;

// Get wrapper attributes
$wrapper_attributes = get_block_wrapper_attributes([
    'class' => 'breadcrumb-navigation',
    'aria-label' => esc_attr__('Breadcrumb Navigation', 'your-textdomain')
]);

/**
 * Generate breadcrumb items array
 */
function caes_hub_get_breadcrumb_items($show_home, $home_text) {
    $breadcrumbs = array();
    
    // Add home link if enabled
    if ($show_home) {
        $breadcrumbs[] = array(
            'title' => $home_text,
            'url' => home_url('/'),
            'position' => 1
        );
    }
    
    $position = $show_home ? 2 : 1;
    
    // Handle different page types
    if (is_singular()) {
        $breadcrumbs = array_merge($breadcrumbs, caes_hub_get_singular_breadcrumbs($position));
    } elseif (is_category() || is_tag() || is_tax()) {
        $breadcrumbs = array_merge($breadcrumbs, caes_hub_get_taxonomy_breadcrumbs($position));
    } elseif (is_post_type_archive()) {
        $breadcrumbs = array_merge($breadcrumbs, caes_hub_get_archive_breadcrumbs($position));
    } elseif (is_author()) {
        $breadcrumbs = array_merge($breadcrumbs, caes_hub_get_author_breadcrumbs($position));
    } elseif (is_date()) {
        $breadcrumbs = array_merge($breadcrumbs, caes_hub_get_date_breadcrumbs($position));
    } elseif (is_search()) {
        $breadcrumbs[] = array(
            'title' => sprintf(__('Search Results for "%s"', 'your-textdomain'), get_search_query()),
            'url' => null,
            'position' => $position
        );
    } elseif (is_404()) {
        $breadcrumbs[] = array(
            'title' => __('Page Not Found', 'your-textdomain'),
            'url' => null,
            'position' => $position
        );
    }
    
    return $breadcrumbs;
}

/**
 * Get breadcrumbs for singular posts/pages
 */
function caes_hub_get_singular_breadcrumbs($start_position) {
    $breadcrumbs = array();
    $post = get_queried_object();
    $position = $start_position;
    $post_type = get_post_type();
    
    // Handle posts and publications with primary topic logic
    if ($post_type === 'post') {
        // Dynamically get the title of the 'News' page
        $news_page = get_page_by_path('news');
        if ($news_page) {
            $breadcrumbs[] = array(
                'title' => get_the_title($news_page->ID),
                'url' => get_permalink($news_page->ID),
                'position' => $position++
            );
        } else {
            // Fallback if 'news' page not found
            $breadcrumbs[] = array(
                'title' => 'News',
                'url' => home_url('/news/'),
                'position' => $position++
            );
        }
        
        // Add primary topic if set
        $primary_topics = get_field('primary_topics', get_the_ID());
        if ($primary_topics && !empty($primary_topics)) {
            // Add the /news/topics/ page if a primary topic is set
            $news_topics_page = get_page_by_path('news/topics');
            if ($news_topics_page) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($news_topics_page->ID),
                    'url' => get_permalink($news_topics_page->ID),
                    'position' => $position++
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => 'Topics',
                    'url' => home_url('/news/topics/'),
                    'position' => $position++
                );
            }

            $primary_topic = $primary_topics[0]; // Use first topic for breadcrumb
            $breadcrumbs[] = array(
                'title' => $primary_topic->name,
                'url' => get_term_link($primary_topic),
                'position' => $position++
            );
        }
    } elseif ($post_type === 'publications') {
        // Dynamically get the title of the 'Publications' page
        $publications_page = get_page_by_path('publications');
        if ($publications_page) {
            $breadcrumbs[] = array(
                'title' => get_the_title($publications_page->ID),
                'url' => get_permalink($publications_page->ID),
                'position' => $position++
            );
        } else {
            // Fallback if 'publications' page not found
            $breadcrumbs[] = array(
                'title' => 'Expert Resources',
                'url' => home_url('/publications/'),
                'position' => $position++
            );
        }
        
        // Add primary topic if set
        $primary_topics = get_field('primary_topics', get_the_ID());
        if ($primary_topics && !empty($primary_topics)) {
            // Add the /publications/topics/ page if a primary topic is set
            $publications_topics_page = get_page_by_path('publications/topics');
            if ($publications_topics_page) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($publications_topics_page->ID),
                    'url' => get_permalink($publications_topics_page->ID),
                    'position' => $position++
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => 'Topics',
                    'url' => home_url('/publications/topics/'),
                    'position' => $position++
                );
            }

            $primary_topic = $primary_topics[0]; // Use first topic for breadcrumb
            $breadcrumbs[] = array(
                'title' => $primary_topic->name,
                'url' => get_term_link($primary_topic),
                'position' => $position++
            );
        }
    } else {
        // Handle other post types and pages with existing logic
        $post_type_object = get_post_type_object($post_type);
        
        if ($post_type !== 'page' && $post_type_object && $post_type_object->has_archive) {
            $breadcrumbs[] = array(
                'title' => $post_type_object->labels->name,
                'url' => get_post_type_archive_link($post_type),
                'position' => $position++
            );
        }
        
        // Handle page hierarchy
        if ($post_type === 'page') {
            $ancestors = get_post_ancestors($post);
            $ancestors = array_reverse($ancestors);
            
            foreach ($ancestors as $ancestor_id) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($ancestor_id),
                    'url' => get_permalink($ancestor_id),
                    'position' => $position++
                );
            }
        }
    }
    
    // Current post/page
    $breadcrumbs[] = array(
        'title' => wp_strip_all_tags(get_the_title()),
        'url' => null,
        'position' => $position
    );
    
    return $breadcrumbs;
}

/**
 * Get breadcrumbs for taxonomy pages
 */
function caes_hub_get_taxonomy_breadcrumbs($start_position) {
    $breadcrumbs = array();
    $term = get_queried_object();
    $position = $start_position;
    
    // Special handling for topics taxonomy - detect context from URL
    if ($term->taxonomy === 'topics') {
        $current_url = $_SERVER['REQUEST_URI'];
        
        if (strpos($current_url, '/publications/') !== false) {
            // Publications context
            $publications_page = get_page_by_path('publications');
            if ($publications_page) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($publications_page->ID),
                    'url' => get_permalink($publications_page->ID),
                    'position' => $position++
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => 'Expert Resources',
                    'url' => home_url('/publications/'),
                    'position' => $position++
                );
            }

            $topics_page = get_page_by_path('publications/topics');
            if ($topics_page) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($topics_page->ID),
                    'url' => get_permalink($topics_page->ID),
                    'position' => $position++
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => 'Topics',
                    'url' => home_url('/publications/topics/'),
                    'position' => $position++
                );
            }

        } elseif (strpos($current_url, '/news/') !== false) {
            // News context
            $news_page = get_page_by_path('news');
            if ($news_page) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($news_page->ID),
                    'url' => get_permalink($news_page->ID),
                    'position' => $position++
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => 'News',
                    'url' => home_url('/news/'),
                    'position' => $position++
                );
            }

            $topics_page = get_page_by_path('news/topics');
            if ($topics_page) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($topics_page->ID),
                    'url' => get_permalink($topics_page->ID),
                    'position' => $position++
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => 'Topics',
                    'url' => home_url('/news/topics/'),
                    'position' => $position++
                );
            }
        }
        
        // Handle term hierarchy for topics
        if ($term->parent) {
            $ancestors = get_ancestors($term->term_id, $term->taxonomy);
            $ancestors = array_reverse($ancestors);
            
            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_term($ancestor_id, $term->taxonomy);
                $breadcrumbs[] = array(
                    'title' => $ancestor->name,
                    'url' => get_term_link($ancestor),
                    'position' => $position++
                );
            }
        }
        
        // Current term
        $breadcrumbs[] = array(
            'title' => $term->name,
            'url' => null,
            'position' => $position
        );
        
        return $breadcrumbs;
    }
    
    // Handle post type archive if taxonomy is tied to custom post type
    $taxonomy = get_taxonomy($term->taxonomy);
    if (!empty($taxonomy->object_type) && !in_array('post', $taxonomy->object_type)) {
        $post_type = $taxonomy->object_type[0];
        $post_type_object = get_post_type_object($post_type);
        
        if ($post_type_object && $post_type_object->has_archive) {
            $breadcrumbs[] = array(
                'title' => $post_type_object->labels->name,
                'url' => get_post_type_archive_link($post_type),
                'position' => $position++
            );
        }
    }
    
    // Handle term hierarchy
    if ($term->parent) {
        $ancestors = get_ancestors($term->term_id, $term->taxonomy);
        $ancestors = array_reverse($ancestors);
        
        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_term($ancestor_id, $term->taxonomy);
            $breadcrumbs[] = array(
                'title' => $ancestor->name,
                'url' => get_term_link($ancestor),
                'position' => $position++
            );
        }
    }
    
    // Current term
    $breadcrumbs[] = array(
        'title' => $term->name,
        'url' => null,
        'position' => $position
    );
    
    return $breadcrumbs;
}

/**
 * Get breadcrumbs for post type archives
 */
function caes_hub_get_archive_breadcrumbs($start_position) {
    $post_type_object = get_queried_object();
    
    return array(
        array(
            'title' => $post_type_object->labels->name,
            'url' => null,
            'position' => $start_position
        )
    );
}

/**
 * Get breadcrumbs for author pages
 */
function caes_hub_get_author_breadcrumbs($start_position) {
    $author = get_queried_object();
    
    return array(
        array(
            'title' => sprintf(__('Author: %s', 'your-textdomain'), $author->display_name),
            'url' => null,
            'position' => $start_position
        )
    );
}

/**
 * Get breadcrumbs for date archives
 */
function caes_hub_get_date_breadcrumbs($start_position) {
    $breadcrumbs = array();
    $position = $start_position;
    
    if (is_year()) {
        $breadcrumbs[] = array(
            'title' => get_the_date('Y'),
            'url' => null,
            'position' => $position
        );
    } elseif (is_month()) {
        $breadcrumbs[] = array(
            'title' => get_the_date('Y'),
            'url' => get_year_link(get_the_date('Y')),
            'position' => $position++
        );
        $breadcrumbs[] = array(
            'title' => get_the_date('F'),
            'url' => null,
            'position' => $position
        );
    } elseif (is_day()) {
        $breadcrumbs[] = array(
            'title' => get_the_date('Y'),
            'url' => get_year_link(get_the_date('Y')),
            'position' => $position++
        );
        $breadcrumbs[] = array(
            'title' => get_the_date('F'),
            'url' => get_month_link(get_the_date('Y'), get_the_date('m')),
            'position' => $position++
        );
        $breadcrumbs[] = array(
            'title' => get_the_date('j'),
            'url' => null,
            'position' => $position
        );
    }
    
    return $breadcrumbs;
}

// Generate breadcrumb items
$cache_key = 'caes_hub_breadcrumbs_' . get_queried_object_id() . '_' . serialize($attributes);
$breadcrumb_items = wp_cache_get($cache_key);

if (false === $breadcrumb_items) {
    $breadcrumb_items = caes_hub_get_breadcrumb_items($show_home, $home_text);
    wp_cache_set($cache_key, $breadcrumb_items, '', 300); // Cache for 5 minutes
}

// Apply max depth if set
if ($max_depth > 0 && count($breadcrumb_items) > $max_depth) {
    $breadcrumb_items = array_slice($breadcrumb_items, -$max_depth);
    // Re-index positions
    foreach ($breadcrumb_items as $index => &$item) {
        $item['position'] = $index + 1;
    }
}

// Don't render if no breadcrumbs
if (empty($breadcrumb_items)) {
    return '';
}

// Generate schema markup
$schema_items = array();
foreach ($breadcrumb_items as $item) {
    $schema_item = array(
        '@type' => 'ListItem',
        'position' => $item['position'],
        'name' => $item['title']
    );
    
    if ($item['url']) {
        $schema_item['item'] = $item['url'];
    }
    
    $schema_items[] = $schema_item;
}

$schema = array(
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => $schema_items
);

// Add schema to head
add_action('wp_head', function() use ($schema) {
    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 10);

?>
<nav <?php echo $wrapper_attributes; ?>>
    <ol class="breadcrumb-list">
        <?php foreach ($breadcrumb_items as $index => $item): ?>
            <li class="breadcrumb-list-item">
                <span class="breadcrumb-item">
                    <?php if ($item['url']): ?>
                        <a href="<?php echo esc_url($item['url']); ?>" 
                           class="<?php echo ($index === 0 && $show_home) ? 'breadcrumb-home' : 'breadcrumb-link'; ?>">
                            <?php echo esc_html($item['title']); ?>
                        </a>
                    <?php else: ?>
                        <span class="breadcrumb-current" aria-current="page">
                            <?php echo esc_html($item['title']); ?>
                        </span>
                    <?php endif; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>