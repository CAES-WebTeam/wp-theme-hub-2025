# Personnel/Expert CPT Migration Overview

## Background

Currently, ~5000+ personnel and expert/writer records are stored as WordPress users with custom roles (`personnel_user`, `expert_user`). These users serve as front-end directory records and are credited on posts, publications, and shorthand stories. Personnel data is actively synced from the CAES personnel API. Expert/writer data was previously imported from a separate CAES database but is now static.

Content managers are real WordPress users who log in and author content. They also have front-end profiles and are credited on publications/stories alongside personnel and experts. Only users with the `content_manager` role get linked CPT posts -- administrators and other roles do not need person profiles.

## Goal

Migrate all personnel and expert/writer records from WordPress users to a `caes_hub_person` Custom Post Type (public URL slug: `/person/`). Content managers retain their WP user accounts for authentication but get linked `caes_hub_person` posts for their public profiles. Administrators and other roles do not get person posts.

---

## Phase 1: Set Up the CPT and Fields

1. Register the `caes_hub_person` CPT with `'rewrite' => ['slug' => 'person']`
2. Duplicate all five ACF field groups targeting `post_type == caes_hub_person` -- keep the originals on users untouched. The five groups are:
   - **"Users"** (in theme code) -- first_name, last_name, display_name, personnel_id, college_id, uga_email, title, department, phone, addresses, image_name, etc. The name fields (first_name, last_name, display_name) are new ACF fields that replace the WP core user fields -- the personnel API provides FNAME, LNAME, and NAME which were previously stored as WP user meta but now need explicit ACF fields on the CPT
   - **"Symplectic Elements"** (in theme code) -- elements_user_id, elements_overview, areas_of_expertise taxonomy, scholarly_works/distinctions/courses repeaters
   - **"Editorial"** (in theme code) -- public_friendly_title
   - **"Expert/Source"** (registered in WP admin, not in theme code) -- source_expert_id, description, area_of_expertise, is_source, is_expert, is_active. Only visible to admin users on the CPT edit screen
   - **"Writer"** (registered in WP admin, not in theme code) -- writer_id, tagline, coverage_area, is_proofer, is_media_contact. Only visible to admin users on the CPT edit screen
   - Reference `acf-export-2026-03-10.json` for the full field definitions of the Expert/Source and Writer groups
3. Add a `linked_wp_user` ACF field (user type) to the `caes_hub_person` CPT for linking content manager accounts to their person posts
4. Attach the `areas_of_expertise` taxonomy to `caes_hub_person` and set `show_in_menu` to `true` so it appears under the People admin menu

## Phase 2: Data Migration

5. Build an admin-page migration tool under the CAES Tools menu, following the async architecture of `symplectic-scheduled-import.php`:
   - **Async batch processing via WP cron**: state persisted in a WP option (status, progress counters, stats, error log, stop_requested flag). Each batch processes N users then schedules the next batch via `wp_schedule_single_event`. Supports stop/resume so the job can be paused and picked up without losing progress
   - **Single-user mode**: select a user by ID or search, migrate just that one user, and display a detailed log of every field copied, any issues found, and the resulting CPT post ID. Runs synchronously (no cron needed for one user)
   - **Bulk mode**: migrate all `personnel_user` and `expert_user` users. Uses the cron-based batch runner with a configurable batch size (default 50). Admin page shows a live progress display (processed/total, success/fail/skip counts) with auto-refreshing status, similar to the Symplectic import control panel
   - **Dry-run toggle**: when enabled, no data is written -- the tool only reports what it would do, including field counts, missing data, and potential issues. Dry-run results are stored in the state option for review
   - **Error logging**: warnings and errors are appended to the state array (capped at a max count to prevent bloat), viewable from the admin page after the run completes or while in progress
   - **Stop/Resume**: a stop button sets a flag in the state option; the batch runner checks this flag before each user and halts gracefully. Resume picks up from `processed_users` offset
   - The tool reads all data from existing WordPress user meta and ACF user fields -- does NOT pull from any external API (expert/writer data is static and no longer synced from the CAES news database)
   - Creates a `caes_hub_person` CPT post for every `personnel_user` and `expert_user` (content managers are NOT migrated separately since they already exist as personnel users)
   - After migration, links content manager WP accounts to their existing person posts by matching on personnel_id and setting the `linked_wp_user` field
   - Copies all user meta and ACF fields (including repeaters) to the new posts
   - Builds and stores a `user_id => post_id` lookup table (WP option) for use in subsequent phases
   - Logs counts and discrepancies for validation
6. Run the migration tool on staging in dry-run mode first, review the log, then run live. Verify data integrity by comparing user counts to CPT post counts and spot-checking field values
7. Update every ACF repeater row (`authors`, `experts`, `translator`, `artists`) across all posts, publications, and shorthand stories -- swapping user IDs for CPT post IDs using the lookup table. This step also runs through the same admin tool with its own batch runner, dry-run support, and logging
8. Repopulate the flat meta fields (`all_author_ids`, `all_expert_ids`) with CPT post IDs across all content. Also runs through the admin tool with the same controls. Before overwriting, back up the original values into `_all_author_ids_backup` and `_all_expert_ids_backup` meta fields on each post so the old user-ID-based indexes can be restored if needed. The admin tool should include a "Revert flat meta" action that copies the backup fields back to the originals.

## Phase 3: Update Sync Infrastructure

9. Rewrite `sync_personnel_users()` and `sync_personnel_users2()` to create/update `caes_hub_person` posts instead of users; rename to `sync_active_personnel()` and `sync_inactive_personnel_authors()`
10. Update the CAES Tools admin page to reflect the new sync targets
11. Rewrite both Symplectic import files to query `caes_hub_person` posts by meta and write to CPT post IDs. While updating, ensure the `journal` field is being extracted and stored for scholarly works -- it is currently missing from the Symplectic import and not carrying over to the CPT
12. Retire `import_news_experts()` and `import_news_writers()`

## Phase 4: Update Front-End Code

13. Create a helper function `get_person_post_for_user($user_id)` that maps a content manager's WP user ID to their linked `caes_hub_person` post ID -- use this anywhere the theme needs to resolve `post_author` to a person profile
14. Update the 8 user blocks (`user-image`, `user-bio`, `user-name`, `user-email`, `user-phone`, `user-position`, `user-department`, `user-feed`) to read from `caes_hub_person` post meta instead of user meta -- keep block names unchanged so saved block markup in the database doesn't break
15. Update `pub-details-authors` block to read from `caes_hub_person` posts instead of user data
16. Update `update_flat_author_ids_meta()` in publications-support.php and `update_flat_expert_ids_meta()` in news-support.php to work with CPT post IDs
17. Update block-variations/index.php -- replace `is_author()` checks with `is_singular('caes_hub_person')` and adjust meta query logic

## Phase 5: URL Structure and Templates

18. Keep the `/person/{id}/{slug}/` URL structure using custom rewrite rules that resolve to `caes_hub_person` posts by post ID (replaces the current rules that resolve to `?author=`). Update the permalink filter to generate `/person/{post_id}/{slug}/` links. The slug is derived from the person's display name (their preferred name) and is purely cosmetic -- the post ID is the stable identifier used for resolution. If WordPress appends `-2` etc. for duplicate names, it does not matter since the slug is never used for lookup.
19. Use `author-2.html` as the basis for the new `single-caes_hub_person.html` template; remove both `author.html` and `author-2.html`
20. Add a redirect rule for old `/person/{user_id}/{slug}/` URLs -- generate a static `user_id => post_id` redirect map (stored as a WP option) during migration; only old-format URLs hit this lookup, and traffic to them fades over time
21. Add 301 redirect from old `/author/username/` URLs to new CPT URLs

## Phase 6: Cleanup

22. Delete the old user-targeted ACF field groups (only after full verification)
23. Remove `personnel_user` and `expert_user` role definitions
24. Update `content_manager_map_meta_cap` filter -- remove the `edit_user`/`edit_users` case (no longer needed since personnel/expert data lives in the CPT) but keep the `unfiltered_html` case (still required for multisite)
25. Remove user profile accordion JS
26. Optionally bulk-delete the old personnel/expert user accounts

---

## Key Risks

- **Repeater field migration** is the highest-risk step -- thousands of posts with serialized ACF data need user IDs swapped to post IDs
- **URL continuity** -- old links in emails, search engines, and external sites need working redirects
- **Block markup** -- renaming blocks would break saved content, so block names must stay as-is
- **Static expert/writer data** -- no re-import available, so the migration script is the only chance to get this data right
- **Content manager dual records** -- must ensure the `linked_wp_user` mapping stays in sync; if a new content manager is onboarded, a `caes_hub_person` post needs to be created and linked

## Recommendations

- Do all phases on staging first
- Build the migration script with dry-run mode and detailed logging
- Validate record counts at every step (users vs posts created, repeater rows updated, flat meta populated)
- Keep the old user-targeted field groups and user accounts intact until the entire migration is verified in production
- Consider adding a hook on user registration/role change that auto-creates a linked `caes_hub_person` post when a user is assigned the `content_manager` role
