<?php
/**
 * WP-CLI commands for the person CPT migration tool.
 *
 * Reuses the same swap logic the UI batch uses, but runs as a long-lived CLI
 * process instead of WP-Cron/AJAX ticks. Much faster on large datasets — no
 * 2-second gap between batches, no WP bootstrap per chunk.
 *
 * Usage:
 *   wp caes person-migration swap                  Run the repeater ID swap
 *   wp caes person-migration swap --dry-run        Preview without writing
 *   wp caes person-migration swap --chunk=1000     Larger DB chunk (default 500)
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class CAES_Person_Migration_CLI {

    /**
     * Swap repeater wp_user IDs for person CPT IDs across posts, publications,
     * shorthand stories, and their revisions.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Report what would change without writing.
     *
     * [--chunk=<n>]
     * : Number of post IDs to fetch per query. Default 500.
     *
     * @when after_wp_load
     */
    public function swap($args, $assoc_args) {
        $dry_run = !empty($assoc_args['dry-run']);
        $chunk   = isset($assoc_args['chunk']) ? max(50, (int) $assoc_args['chunk']) : 500;

        $map = person_migration_get_map();
        if (empty($map)) {
            WP_CLI::error('Migration map is empty. Build it via the dashboard before running the swap.');
        }

        global $wpdb;
        $post_types_in = "'post','publications','shorthand_story'";

        $total = (int) $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
            WHERE (
                (p.post_type IN ({$post_types_in})
                 AND p.post_status IN ('publish','draft','private','future'))
                OR
                (p.post_type = 'revision' AND p.post_status = 'inherit'
                 AND parent.post_type IN ({$post_types_in}))
            )
        ");

        WP_CLI::log(sprintf(
            'Map size: %d. Scanning %d posts/revisions in chunks of %d.%s',
            count($map), $total, $chunk, $dry_run ? ' [DRY RUN]' : ''
        ));

        $progress = WP_CLI\Utils\make_progress_bar('Swapping', $total);

        $repeater_names = array('authors', 'experts', 'translator', 'artists');
        $sub_candidates = array('user', 'author', 'expert');

        $posts_touched = 0;
        $total_swaps   = 0;
        $offset        = 0;

        while ($offset < $total) {
            $post_ids = $wpdb->get_col($wpdb->prepare("
                SELECT p.ID FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
                WHERE (
                    (p.post_type IN ({$post_types_in})
                     AND p.post_status IN ('publish','draft','private','future'))
                    OR
                    (p.post_type = 'revision' AND p.post_status = 'inherit'
                     AND parent.post_type IN ({$post_types_in}))
                )
                ORDER BY p.ID ASC
                LIMIT %d OFFSET %d
            ", $chunk, $offset));

            if (empty($post_ids)) break;

            foreach ($post_ids as $pid) {
                $pid        = (int) $pid;
                $post_swaps = 0;

                foreach ($repeater_names as $rname) {
                    $count = (int) get_metadata('post', $pid, $rname, true);
                    if ($count <= 0) continue;

                    for ($i = 0; $i < $count; $i++) {
                        foreach ($sub_candidates as $sub) {
                            $meta_key = $rname . '_' . $i . '_' . $sub;
                            $old_val  = get_metadata('post', $pid, $meta_key, true);
                            if ($old_val === '' || $old_val === null) continue;
                            if (!isset($map[$old_val])) continue;

                            if (!$dry_run) {
                                // update_metadata bypasses WP core's redirect of
                                // revision IDs to the parent post, so revision
                                // rows actually get written.
                                update_metadata('post', $pid, $meta_key . '_backup', $old_val);
                                update_metadata('post', $pid, $meta_key, $map[$old_val]);
                            }
                            $post_swaps++;
                            $total_swaps++;
                        }
                    }
                }

                if ($post_swaps > 0) $posts_touched++;
                $progress->tick();
            }

            $offset += count($post_ids);

            // Free per-chunk caches so memory doesn't grow with the scan
            if (function_exists('wp_cache_flush_runtime')) {
                wp_cache_flush_runtime();
            }
        }

        $progress->finish();
        WP_CLI::success(sprintf(
            '%s%d swap(s) across %d post/revision row(s) (of %d scanned).',
            $dry_run ? '[DRY RUN] ' : '',
            $total_swaps,
            $posts_touched,
            $total
        ));
    }
}

WP_CLI::add_command('caes person-migration', 'CAES_Person_Migration_CLI');
