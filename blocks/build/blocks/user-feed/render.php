<?php
// Custom function to set current user (like the_post() does for posts)
if (!function_exists('caes_set_current_user')) {
    function caes_set_current_user($user) {
        $GLOBALS['caes_current_user_id'] = $user->ID;
    }
}

if (!function_exists('caes_get_current_user_id')) {
    function caes_get_current_user_id() {
        return isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();
    }
}

// Get attributes with proper defaults to match frontend
$user_ids = isset($block->attributes['userIds']) ? $block->attributes['userIds'] : [];
$feed_type = isset($block->attributes['feedType']) ? $block->attributes['feedType'] : 'hand-picked';
$number_of_users = isset($block->attributes['numberOfUsers']) ? $block->attributes['numberOfUsers'] : 5;
$query_id = isset($block->attributes['queryId']) ? $block->attributes['queryId'] : 100;
$displayLayout = isset($block->attributes['displayLayout']) ? $block->attributes['displayLayout'] : 'list';
$columns = isset($block->attributes['columns']) ? $block->attributes['columns'] : 3;
$customGapStep = isset($block->attributes['customGapStep']) ? $block->attributes['customGapStep'] : 0;
$gridItemPosition = isset($block->attributes['gridItemPosition']) ? $block->attributes['gridItemPosition'] : 'manual';
$gridAutoColumnWidth = isset($block->attributes['gridAutoColumnWidth']) ? $block->attributes['gridAutoColumnWidth'] : 12;
$gridAutoColumnUnit = isset($block->attributes['gridAutoColumnUnit']) ? $block->attributes['gridAutoColumnUnit'] : 'rem';

// Layout settings
$base_class = $displayLayout === 'grid' ? 'user-feed-grid' : 'user-feed-list';
$columns_class = $displayLayout === 'grid' ? 'columns-' . intval($columns) : '';

// Spacing classes
$SPACING_CLASSES = array(
    0 => '',
    1 => 'gap-wp-preset-spacing-20',
    2 => 'gap-wp-preset-spacing-30',
    3 => 'gap-wp-preset-spacing-40',
    4 => 'gap-wp-preset-spacing-50',
    5 => 'gap-wp-preset-spacing-60',
    6 => 'gap-wp-preset-spacing-70',
    7 => 'gap-wp-preset-spacing-80',
);

$spacing_class = isset($SPACING_CLASSES[$customGapStep]) ? $SPACING_CLASSES[$customGapStep] : '';
$classes = trim("$base_class $columns_class $spacing_class");

// Generate inline grid styles for auto layout
$inline_style = '';

if ($displayLayout === 'grid' && $gridItemPosition === 'auto') {
    $width = floatval($gridAutoColumnWidth);
    $unit = esc_attr($gridAutoColumnUnit);
    $min_width = "{$width}{$unit}";
    $inline_style = "grid-template-columns: repeat(auto-fill, minmax(min({$min_width}, 100%), 1fr));";
}

$wrapper_attributes = get_block_wrapper_attributes();

// We only support hand-picked users/persons
if ($feed_type !== 'hand-picked' || empty($user_ids)) {
    return;
}

// Separate IDs into CPT post IDs and WP user IDs
$person_post_ids = array();
$wp_user_ids     = array();
foreach ($user_ids as $id) {
    $id = (int) $id;
    if (get_post_type($id) === 'caes_hub_person') {
        $person_post_ids[] = $id;
    } else {
        $wp_user_ids[] = $id;
    }
}

// Build an ordered list of render items: each has an 'id' and 'type' (post|user)
$render_items = array();
foreach ($user_ids as $id) {
    $id = (int) $id;
    if (in_array($id, $person_post_ids, true)) {
        $render_items[] = array('id' => $id, 'type' => 'post');
    } else {
        $render_items[] = array('id' => $id, 'type' => 'user');
    }
}

// Pre-fetch WP users in one query if needed
$wp_users_by_id = array();
if (!empty($wp_user_ids)) {
    $cache_key = 'user_feed_users_' . md5(implode('_', $wp_user_ids));
    $fetched   = wp_cache_get($cache_key, 'caes_user_feed');
    if (false === $fetched) {
        $uq      = new WP_User_Query(array('include' => $wp_user_ids, 'orderby' => 'include', 'number' => count($wp_user_ids)));
        $fetched = $uq->get_results();
        wp_cache_set($cache_key, $fetched, 'caes_user_feed', HOUR_IN_SECONDS);
    }
    foreach ($fetched as $u) {
        $wp_users_by_id[$u->ID] = $u;
    }
}

if (!empty($render_items)) {
?>
    <div <?php echo wp_kses_post($wrapper_attributes); ?>>
        <div class="<?php echo esc_attr($classes); ?>" style="<?php echo esc_attr($inline_style); ?>">

            <?php
            foreach ($render_items as $item) {
                if ($item['type'] === 'post') {
                    // CPT person post -- set global to post ID so inner blocks use resolve_person_post_id()
                    $GLOBALS['caes_current_user_id'] = $item['id'];
                    $context_user_id = $item['id'];
                    echo '<!-- person: caes_hub_person #' . $item['id'] . ' -->';
                } else {
                    // WP user -- set global to user ID (pre-migration path)
                    $user = $wp_users_by_id[$item['id']] ?? null;
                    if (!$user) continue;
                    caes_set_current_user($user);
                    $context_user_id = $user->ID;
                    echo '<!-- person: wp_user #' . $user->ID . ' -->';
                }

                $block_context = array(
                    'caes-hub/user-feed/userIds' => $user_ids,
                    'caes-hub/user-feed/queryId' => $query_id,
                    'userId'                     => $context_user_id,
                );

                if (!empty($block->inner_blocks)) {
                    foreach ($block->inner_blocks as $inner_block) {
                        $inner_block_instance = new WP_Block($inner_block->parsed_block, $block_context);
                        echo $inner_block_instance->render();
                    }
                }
            }
            ?>
        </div>
    </div>
<?php
}
?>