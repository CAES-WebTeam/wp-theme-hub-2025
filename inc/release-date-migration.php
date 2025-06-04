<?php
/**
 * Release Date Migration Tool
 * Safe admin UI to convert text-based datetime fields (ISO) 
 * into an ACF DateTime Picker field.
 *
 * To include from your theme's functions.php:
 *     require get_template_directory() . '/release-date-migration.php';
 */

add_action('admin_menu', function () {
    add_management_page(
        'Release Date Migration',    // Page title
        'Release Date Migration',    // Menu title under Tools
        'manage_options',            // Capability
        'release-date-migration',    // Menu slug
        'render_release_date_migration_page'
    );
});

function render_release_date_migration_page() {
    if (! current_user_can('manage_options')) {
        return;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ★ YOUR SETTINGS –––––––––––––––––––––––––––––––––––––––––––––––––––––
    $post_type = 'post';            // ← Change to your CPT slug if needed
    $old_field = 'release_date';    // Original ACF text field
    $new_field = 'release_date_new';// New ACF DateTime Picker field
    $per_page  = 500;               // How many posts per “page” of preview
    // ─────────────────────────────────────────────────────────────────────────

    // “Show only items needing migration?” comes from GET parameter
    $show_only = isset($_GET['show_only']) && $_GET['show_only'] === '1';

    // Current page for pagination (via GET)
    $paged  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($paged - 1) * $per_page;

    echo '<div class="wrap"><h1>Release Date Migration</h1>';

    // ─────────────────────────────────────────────────────────────────────────
    // 1) HANDLE MIGRATION (POST FORM)
    // ─────────────────────────────────────────────────────────────────────────
    if (isset($_POST['run_migration']) && check_admin_referer('release_date_migration')) {
        $migrated_count = 0;

        // Build meta_query exactly as in preview below:
        $meta_query = [
            'relation' => 'AND',
            [
                'key'     => $old_field,
                'compare' => 'EXISTS',
            ],
        ];

        if ($show_only) {
            // If “Show only…” is checked, also require that new_field is empty or doesn't exist
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key'     => $new_field,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => $new_field,
                    'value'   => '',
                    'compare' => '=',
                ],
            ];
        }

        $posts_to_migrate = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'post_status'    => 'any',
            'meta_query'     => $meta_query,
        ]);

        foreach ($posts_to_migrate as $post) {
            $old_iso = trim(get_post_meta($post->ID, $old_field, true));
            if (empty($old_iso)) {
                // Nothing to convert
                continue;
            }

            $timestamp = strtotime($old_iso);
            if ($timestamp === false) {
                // Invalid date string
                continue;
            }

            // **Use “Y-m-d H:i:s” for storage** (no “T”)
            $converted = date('Y-m-d H:i:s', $timestamp);

            // Retrieve existing new-field value
            $existing_new = get_field($new_field, $post->ID);

            // If it already matches exactly, skip
            if ($existing_new === $converted) {
                continue;
            }

            // Otherwise write the new value
            update_field($new_field, $converted, $post->ID);
            $migrated_count++;
        }

        echo "<div class='notice notice-success'><p><strong>Migrated {$migrated_count} posts on this page.</strong></p></div>";
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2) RENDER FILTER FORM (“Show only items needing migration?”)
    // ─────────────────────────────────────────────────────────────────────────
    // Use GET so pagination links keep this parameter
    $checked_attr = $show_only ? 'checked' : '';
    echo '<form method="GET" style="margin-bottom:1em;">';
    // Preserve paged if present (so checkbox toggle doesn’t lose your page)
    if ($paged > 1) {
        echo '<input type="hidden" name="paged" value="' . esc_attr($paged) . '">';
    }
    // Preserve the menu slug
    echo '<input type="hidden" name="page" value="release-date-migration">';
    echo '<label style="font-weight:normal;">';
    echo '<input type="checkbox" name="show_only" value="1" ' . $checked_attr . '> ';
    echo 'Show only items needing migration';
    echo '</label> ';
    echo '<input type="submit" class="button" value="Apply Filter">';
    echo '</form>';

    // ─────────────────────────────────────────────────────────────────────────
    // 3) BUILD THE “TOTAL” QUERY FOR PAGINATION
    // ─────────────────────────────────────────────────────────────────────────
    $total_wp_query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'no_found_rows'  => false,    // we need max_num_pages
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => $old_field,
                'compare' => 'EXISTS',
            ],
            // If show_only, restrict to posts where new_field is empty/not set
            ( $show_only
                ? [
                    'relation' => 'OR',
                    [
                        'key'     => $new_field,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => $new_field,
                        'value'   => '',
                        'compare' => '=',
                    ],
                ]
                : [ ] // no extra clause if not filtering
            ),
        ],
    ]);

    $total_pages = $total_wp_query->max_num_pages;

    // ─────────────────────────────────────────────────────────────────────────
    // 4) FETCH POSTS FOR THIS PAGE (RESPECTING “SHOW ONLY…”)
    // ─────────────────────────────────────────────────────────────────────────
    $preview_wp_query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'no_found_rows'  => true, // no need for pagination data here
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => $old_field,
                'compare' => 'EXISTS',
            ],
            // If show_only, restrict to new_field empty/not set
            ( $show_only
                ? [
                    'relation' => 'OR',
                    [
                        'key'     => $new_field,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => $new_field,
                        'value'   => '',
                        'compare' => '=',
                    ],
                ]
                : [ ] // no extra clause if not filtering
            ),
        ],
    ]);

    // ─────────────────────────────────────────────────────────────────────────
    // 5) RENDER THE PREVIEW TABLE + MIGRATE BUTTON
    // ─────────────────────────────────────────────────────────────────────────
    echo '<form method="POST">';
    wp_nonce_field('release_date_migration');

    echo '<p>This table shows posts with an existing “Release Date” text. ';
    if ($show_only) {
        echo 'You are viewing <strong>only</strong> those needing migration (i.e. no new DateTime value yet).';
    } else {
        echo 'Uncheck “Show only…” to see everything.';
    }
    echo '</p>';

    echo '<table class="widefat fixed">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Post</th>';
    echo '<th>Old Value (text)</th>';
    echo '<th>New Value (DateTime)</th>';
    echo '<th>Status</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if ($preview_wp_query->have_posts()) :
        while ($preview_wp_query->have_posts()) : $preview_wp_query->the_post();
            $post_id = get_the_ID();
            $old_iso = trim(get_post_meta($post_id, $old_field, true));
            $new_datetime = get_field($new_field, $post_id);

            // Determine status 
            if (empty($old_iso)) {
                $status = '<span style="color:#888;">(empty, skipped)</span>';
                $converted = '';
            } elseif (($ts = strtotime($old_iso)) === false) {
                $status = '<span style="color:#d00;">(invalid date)</span>';
                $converted = '';
            } else {
                // **Convert using “Y-m-d H:i:s” (no T)**
                $converted = date('Y-m-d H:i:s', $ts);

                if ($new_datetime === $converted) {
                    $status = '<span style="color:green;">✔ Already migrated</span>';
                } elseif (! empty($new_datetime)) {
                    $status = '<span style="color:orange;">⚠ Mismatch</span>';
                } else {
                    $status = '<span style="color:blue;">→ Ready to migrate</span>';
                }
            }

            // If user did NOT request “Show only…”, render every row.
            // If they DID request it, render ONLY when new_datetime != converted
            if (
                ! $show_only 
                || (
                    ! empty($converted) 
                    && $new_datetime !== $converted
                )
            ) {
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_edit_post_link($post_id)) . '">' 
                     . get_the_title($post_id) . '</a></td>';
                echo '<td><code>' . esc_html($old_iso) . '</code></td>';
                echo '<td><code>' . esc_html($new_datetime) . '</code></td>';
                echo '<td>' . $status . '</td>';
                echo '</tr>';
            }
        endwhile;
        wp_reset_postdata();
    else :
        echo '<tr><td colspan="4">No posts found.</td></tr>';
    endif;

    echo '</tbody>';
    echo '</table>';

    // **Show the migrate button if any rows were printed**:
    if ($preview_wp_query->have_posts()) {
        echo '<p><input type="submit" name="run_migration" '
            . 'class="button button-primary" '
            . 'value="Migrate These Posts on Page"></p>';
    }

    echo '</form>';

    // ─────────────────────────────────────────────────────────────────────────
    // 6) RENDER PAGINATION LINKS (KEEPING show_only & page)
    // ─────────────────────────────────────────────────────────────────────────
    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        $base_args = [
            'base'      => add_query_arg(
                array(
                    'paged'     => '%#%',
                    'page'      => 'release-date-migration',
                    'show_only' => $show_only ? '1' : '0',
                ), 
                admin_url('tools.php')
            ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $total_pages,
            'prev_text' => __('‹ Previous'),
            'next_text' => __('Next ›'),
        ];
        echo paginate_links($base_args);
        echo '</div></div>';
    }

    echo '</div>'; // .wrap
}