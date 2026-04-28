<?php
/**
 * WP-CLI commands for person CPT data syncs.
 *
 * Reuses the existing personnel and Symplectic sync logic but runs as a
 * long-lived CLI process instead of WP-Cron batches. Same state option,
 * same per-post functions, same dashboard visibility.
 *
 * Usage:
 *   wp caes person-sync personnel       Run the personnel API sync
 *   wp caes person-sync symplectic      Run the Symplectic Elements sync
 *   wp caes person-sync all             Run personnel, then Symplectic
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class CAES_Person_Sync_CLI {

    /**
     * Run the personnel API sync.
     *
     * ## OPTIONS
     *
     * [--quiet]
     * : Suppress per-post progress output.
     *
     * @when after_wp_load
     */
    public function personnel($args, $assoc_args) {
        $quiet = !empty($assoc_args['quiet']);
        $force = !empty($assoc_args['force']);

        WP_CLI::log('Starting personnel sync...');

        $this->maybe_force_reset(PERSONNEL_CPT_STATE_KEY, PERSONNEL_CPT_BATCH_HOOK, $force);

        // Reuse existing setup: fetch API, build dept URL map, set state to running.
        $started = personnel_cpt_start_job('cli');
        if (!$started) {
            $state = personnel_cpt_get_state();
            if ($state['status'] === 'running') {
                WP_CLI::error('A sync is already running. Use --force to override, or stop it from the dashboard first.');
            }
            WP_CLI::error('Could not start sync. Check API credentials and connectivity.');
        }

        // Cancel the cron event start_job scheduled -- we run synchronously instead.
        wp_clear_scheduled_hook(PERSONNEL_CPT_BATCH_HOOK);

        $this->loop_until_complete('personnel', $quiet);
    }

    /**
     * Run the Symplectic Elements sync.
     *
     * ## OPTIONS
     *
     * [--quiet]
     * : Suppress per-post progress output.
     *
     * @when after_wp_load
     */
    public function symplectic($args, $assoc_args) {
        $quiet = !empty($assoc_args['quiet']);
        $force = !empty($assoc_args['force']);

        WP_CLI::log('Starting Symplectic sync...');

        $this->maybe_force_reset(SYMPLECTIC_CPT_STATE_KEY, SYMPLECTIC_CPT_BATCH_HOOK, $force);

        $started = symplectic_cpt_start_job('cli');
        if (!$started) {
            $state = symplectic_cpt_get_state();
            if ($state['status'] === 'running') {
                WP_CLI::error('A sync is already running. Use --force to override, or stop it from the dashboard first.');
            }
            WP_CLI::error('Could not start sync. Check API credentials and that person posts exist.');
        }

        wp_clear_scheduled_hook(SYMPLECTIC_CPT_BATCH_HOOK);

        $this->loop_until_complete('symplectic', $quiet);
    }

    /**
     * Reset stuck sync state and clear scheduled cron events. Use after a
     * crashed CLI run or when the dashboard "Stop" button isn't enough.
     *
     * @when after_wp_load
     */
    public function reset($args, $assoc_args) {
        delete_option(PERSONNEL_CPT_STATE_KEY);
        delete_option(SYMPLECTIC_CPT_STATE_KEY);
        wp_clear_scheduled_hook(PERSONNEL_CPT_BATCH_HOOK);
        wp_clear_scheduled_hook(SYMPLECTIC_CPT_BATCH_HOOK);
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        WP_CLI::success('Sync state cleared and pending cron events removed.');
    }

    /**
     * If --force is used, clear state and any orphaned cron events so a
     * fresh start_job can succeed even if previous run left status='running'.
     */
    private function maybe_force_reset($state_key, $batch_hook, $force) {
        if (!$force) {
            return;
        }
        WP_CLI::warning("--force: clearing existing state and pending cron events.");
        delete_option($state_key);
        wp_clear_scheduled_hook($batch_hook);
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Run personnel, then Symplectic, in sequence.
     *
     * ## OPTIONS
     *
     * [--quiet]
     * : Suppress per-post progress output.
     *
     * @when after_wp_load
     */
    public function all($args, $assoc_args) {
        $this->personnel($args, $assoc_args);
        $this->symplectic($args, $assoc_args);
    }

    /**
     * Loop calling the appropriate run_batch function until the sync completes
     * or stops. Streams progress to stdout.
     */
    private function loop_until_complete($which, $quiet) {
        $state_fn   = $which === 'personnel' ? 'personnel_cpt_get_state' : 'symplectic_cpt_get_state';
        $batch_fn   = $which === 'personnel' ? 'personnel_cpt_run_batch' : 'symplectic_cpt_run_batch';
        $total_key  = $which === 'personnel' ? 'total_records' : 'total_posts';
        $done_key   = $which === 'personnel' ? 'processed' : 'processed_posts';
        $state_key  = $which === 'personnel' ? PERSONNEL_CPT_STATE_KEY : SYMPLECTIC_CPT_STATE_KEY;

        // Catch ctrl-C / SIGTERM and mark the sync as stopped so the next run can start cleanly.
        $interrupted = false;
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $handler = function ($signo) use (&$interrupted, $state_key) {
                $interrupted = true;
                $state = get_option($state_key);
                if (is_array($state) && ($state['status'] ?? '') === 'running') {
                    $state['status'] = 'stopped';
                    $state['completed_at'] = time();
                    update_option($state_key, $state, false);
                }
                WP_CLI::warning("Interrupted by signal {$signo}. State marked as stopped.");
                exit(130);
            };
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGTERM, $handler);
            if (defined('SIGHUP')) {
                pcntl_signal(SIGHUP, $handler);
            }
        }

        $start = microtime(true);
        $last_progress = -1;

        while (true) {
            $state = call_user_func($state_fn);

            if ($state['status'] !== 'running') {
                // Stopped, completed, or errored
                break;
            }

            $processed = (int) ($state[$done_key] ?? 0);
            $total     = (int) ($state[$total_key] ?? 0);

            if (!$quiet && $processed !== $last_progress) {
                $pct = $total > 0 ? round($processed / $total * 100, 1) : 0;
                WP_CLI::log("[{$which}] {$processed}/{$total} ({$pct}%)");
                $last_progress = $processed;
            }

            // Run one batch synchronously. The existing batch code schedules a
            // cron event for the next batch; we cancel that and loop here instead.
            call_user_func($batch_fn);
            wp_clear_scheduled_hook($which === 'personnel' ? PERSONNEL_CPT_BATCH_HOOK : SYMPLECTIC_CPT_BATCH_HOOK);
        }

        $state    = call_user_func($state_fn);
        $elapsed  = round(microtime(true) - $start, 1);
        $stats    = $state['stats'] ?? array();

        WP_CLI::log('--- Done ---');
        WP_CLI::log("Status:   {$state['status']}");
        WP_CLI::log("Elapsed:  {$elapsed}s");
        if ($which === 'personnel') {
            WP_CLI::log("Created:        " . ($stats['created'] ?? 0));
            WP_CLI::log("Updated:        " . ($stats['updated'] ?? 0));
            WP_CLI::log("Marked Inactive:" . ($stats['marked_inactive'] ?? 0));
            WP_CLI::log("Unpublished:    " . ($stats['unpublished'] ?? 0));
            WP_CLI::log("Reactivated:    " . ($stats['reactivated'] ?? 0));
        } else {
            WP_CLI::log("Posts OK:       " . ($stats['posts_ok'] ?? 0));
            WP_CLI::log("Failed:         " . ($stats['posts_failed'] ?? 0));
            WP_CLI::log("Skipped:        " . ($stats['posts_skipped'] ?? 0));
            WP_CLI::log("Fields Written: " . ($stats['fields_written'] ?? 0));
        }
        WP_CLI::log('Errors: ' . ($stats['errors'] ?? count($state['errors'] ?? array())));

        if ($state['status'] === 'error') {
            WP_CLI::error('Sync ended with errors. Check the dashboard for details.');
        }
        if ($state['status'] === 'stopped') {
            WP_CLI::warning('Sync was stopped before completion.');
        }
    }
}

WP_CLI::add_command('caes person-sync', 'CAES_Person_Sync_CLI');
