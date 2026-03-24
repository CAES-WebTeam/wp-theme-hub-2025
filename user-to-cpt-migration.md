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

All steps across all phases are tracked through a single **migration dashboard** (CAES Tools > Person CPT Migration). The dashboard:

- Shows every phase and step with its current status (not started, in progress, complete)
- Auto-detects status where possible (post counts, lookup map existence, repeater swap state, etc.)
- Only enables Phase 2 actions when their prerequisites are met (greyed out otherwise)
- Phases 3-6 appear as manual checklist items with "mark complete" toggles
- Includes a **Reset** button that clears all migration state (lookup map, duplicate groups, checklist progress, migration job state) in one action -- designed to pair with a fresh prod-to-staging database copy for a clean restart

Phase 2 steps must be executed in the order listed.

5. **Migrate Users to CPT** -- Build an admin-page migration tool under the CAES Tools menu, following the async architecture of `symplectic-scheduled-import.php`:
   - **Async batch processing via WP cron**: state persisted in a WP option (status, progress counters, stats, error log, stop_requested flag). Each batch processes N users then schedules the next batch via `wp_schedule_single_event`. Supports stop/resume so the job can be paused and picked up without losing progress
   - **Single-user mode**: select a user by ID or search, migrate just that one user, and display a detailed log of every field copied, any issues found, and the resulting CPT post ID. Runs synchronously (no cron needed for one user)
   - **Bulk mode**: migrate all `personnel_user` and `expert_user` users. Uses the cron-based batch runner with a configurable batch size (default 50). Admin page shows a live progress display (processed/total, success/fail/skip counts) with auto-refreshing status, similar to the Symplectic import control panel
   - **Dry-run toggle**: when enabled, no data is written -- the tool only reports what it would do, including field counts, missing data, and potential issues. Dry-run results are stored in the state option for review
   - **Error logging**: warnings and errors are appended to the state array (capped at a max count to prevent bloat), viewable from the admin page after the run completes or while in progress
   - **Stop/Resume**: a stop button sets a flag in the state option; the batch runner checks this flag before each user and halts gracefully. Resume picks up from `processed_users` offset
   - The tool reads all data from existing WordPress user meta and ACF user fields -- does NOT pull from any external API (expert/writer data is static and no longer synced from the CAES news database)
   - Creates a `caes_hub_person` CPT post for every `personnel_user` and `expert_user` (content managers are NOT migrated separately since they already exist as personnel users)
   - Copies all user meta and ACF fields (including repeaters) to the new posts
   - Builds and stores a `user_id => post_id` lookup table (WP option) for use in subsequent steps
   - Logs counts and discrepancies for validation
   - Run on staging in dry-run mode first, review the log, then run live. Verify data integrity by comparing user counts to CPT post counts and spot-checking field values
   - 5v. **Verify: Run Person CPT Count Audit** -- confirm the map size matches the number of People posts created (before any merges, difference should be 0)

6. **Link Content Managers** -- Match content manager WP accounts to their existing person posts by personnel_id and set the `linked_wp_user` field. Runs synchronously. Independent of content reference updates but requires step 5 to be complete so the person posts exist.
   > **Open question:** The link is only needed if something in the system needs to map `get_current_user_id()` to a person post (e.g., auto-populating the current user as an author, "My Profile" admin links, or similar). If no such feature exists or is planned, this step can be deferred or skipped. Review before running on production.

7. **Swap Repeater IDs** -- Update every ACF repeater row (`authors`, `experts`, `translator`, `artists`) across all posts, publications, and shorthand stories, swapping user IDs for CPT post IDs using the lookup table. Each original user ID is backed up to a `_backup` meta key before overwriting so the swap can be fully reverted. Uses the same async batch runner with dry-run support and logging. Includes a Verify button (samples posts and reports what IDs resolve to) and a Revert Swap button (restores all original user IDs from backups and reverts ACF field types). Must run before duplicate merging so that all content references are in a consistent format (post IDs).
   - 7a. **Verify swap** -- use the built-in Verify button to confirm all repeater IDs now point to `caes_hub_person` posts. Resolve any flagged users that weren't in the map.
   - 7b. **Update ACF Field Types** -- After the swap, change the `user` sub-field in each repeater (authors, experts, translator, artists) from ACF User type to Post Object type targeting `caes_hub_person`. This makes the admin editor show a person CPT picker instead of a user picker. Original field settings are backed up and can be reverted independently. Must run immediately after the swap; the Revert Swap button automatically reverts field types as well.

8. **Repopulate Flat Meta** -- Rebuild the flat meta fields (`all_author_ids`, `all_expert_ids`) with CPT post IDs across all content. Must run after the swap (step 7) so the indexes use CPT post IDs. Before overwriting, back up the original values into `_all_author_ids_backup` and `_all_expert_ids_backup` meta fields on each post so the old user-ID-based indexes can be restored if needed. The admin tool includes a "Revert flat meta" action that copies the backup fields back to the originals.
   - 8v. **Verify: Run Flat Meta ID Audit** -- confirm all `all_author_ids` and `all_expert_ids` values contain CPT post IDs, not WP user IDs

## Phase 3: Update Sync Infrastructure

10. Rewrite `sync_personnel_users()` and `sync_personnel_users2()` to create/update `caes_hub_person` posts instead of users; rename to `sync_active_personnel()` and `sync_inactive_personnel_authors()`. Must also update `is_active` on the CPT post (the merge tool and front-end rely on this field to determine active/inactive status)
11. Update the CAES Tools admin page to reflect the new sync targets
12. Rewrite both Symplectic import files to query `caes_hub_person` posts by meta and write to CPT post IDs. While updating, ensure the year data is carrying over correctly for scholarly works.
13. Retire `import_news_experts()` and `import_news_writers()`

## Phase 4: Update Front-End Code

**Critical deployment note:** All template/rendering changes in this phase must be deployed to production **before** the repeater swap (Phase 2, step 7) runs on production. Each updated file must handle both user IDs and CPT post IDs gracefully during the transition -- check if the ID is a `caes_hub_person` post first, fall back to user lookup if not. This way the frontend works correctly both before and after the swap.

14. Create a helper function `resolve_person_data($id)` that accepts either a user ID or a CPT post ID and returns a normalized array of person data (name, title, email, image, permalink, etc.). All files below should use this helper so the user-vs-post branching logic lives in one place.

15. Update **pub-details-authors block** -- the primary rendering of authors/experts/translator/artists on publications and stories
    - File: `blocks/src/blocks/pub-details-authors/render.php`
    - Currently calls: `get_the_author_meta()`, `get_author_posts_url()`, `get_field('public_friendly_title', 'user_' . $user_id)`
    - Change to: use `resolve_person_data()` for all person lookups

16. Update **Yoast SEO schema** -- Person structured data for authors
    - File: `inc/plugin-overrides/yoast-schema.php` (lines 67-130)
    - Currently calls: `get_the_author_meta('display_name')`, `get_author_posts_url()`
    - Change to: use `resolve_person_data()`

17. Update **PDF generation** -- author names/titles in publication PDFs
    - Files: `inc/publications-pdf/publications-pdf.php` (lines 839-878), `inc/publications-pdf/publications-pdf-mpdf.php` (lines 553-594)
    - Currently calls: `get_the_author_meta('first_name')`, `get_the_author_meta('last_name')`, `get_the_author_meta('title')`, `get_field('public_friendly_title', 'user_' . $user_id)`
    - Change to: use `resolve_person_data()`

18. Update **RSS feed support** -- author names in feed output
    - File: `inc/rss-support.php` (lines 79-127)
    - Currently calls: `get_the_author_meta('first_name')`, `get_the_author_meta('last_name')`
    - Change to: use `resolve_person_data()`

19. Update **analytics data layer** -- author tracking in GA
    - File: `inc/analytics.php` (lines 173-278)
    - Currently calls: `get_field('field_uga_email_custom', 'user_' . $user_id)`, `get_the_author_meta('user_email')`, `get_the_author_meta('display_name')`
    - Change to: use `resolve_person_data()`

20. **Flat meta save hooks** -- no code changes needed
    - Files: `inc/publications-support.php` (`update_flat_author_ids_meta()`), `inc/news-support.php` (`update_flat_expert_ids_meta()`)
    - These hooks read raw `$_POST` data and pass through whatever ID is in the repeater's `user` subfield -- they naturally write user IDs before the swap and post IDs after. No dual-ID logic required

21. Update **block variations / archive queries** -- content feeds on person profile pages
    - File: `block-variations/index.php` (lines 209-212, 277-285)
    - Currently uses `is_author()` and LIKE queries against serialized user IDs in `all_author_ids` / `all_expert_ids`
    - Change to: support `is_singular('caes_hub_person')` and query by post IDs

22. Update the **8 user blocks** (`user-image`, `user-bio`, `user-name`, `user-email`, `user-phone`, `user-position`, `user-department`, `user-feed`) to read from `caes_hub_person` post meta instead of user meta -- keep block names unchanged so saved block markup in the database doesn't break

23. Update **publications content import** -- author mapping during pub imports
    - File: `inc/publications-support.php` (lines 229-241, 152-154)
    - Currently looks up users by `college_id` and stores `user_id` in repeater rows
    - Change to: look up `caes_hub_person` posts by `college_id` meta and store post IDs

## Phase 5: URL Structure and Templates

**Critical deployment note:** Steps 24-27 must be deployed together as one unit. The redirect rules (steps 26-27) depend on the lookup map from Phase 2 step 5, which already exists by this point. Do NOT deploy the new rewrite rules (step 24) without the redirect code (steps 26-27) or old URLs will 404.

24. Keep the `/person/{id}/{slug}/` URL structure using custom rewrite rules that resolve to `caes_hub_person` posts by post ID (replaces the current rules that resolve to `?author=`). Update the permalink filter to generate `/person/{post_id}/{slug}/` links. The slug is derived from the person's display name (their preferred name) and is purely cosmetic -- the post ID is the stable identifier used for resolution. If WordPress appends `-2` etc. for duplicate names, it does not matter since the slug is never used for lookup.
25. Use `author-2.html` as the basis for the new `single-caes_hub_person.html` template; remove both `author.html` and `author-2.html`
26. Add a redirect rule for old `/person/{user_id}/{slug}/` URLs -- generate a static `user_id => post_id` redirect map (stored as a WP option) during migration; only old-format URLs hit this lookup, and traffic to them fades over time
27. Add 301 redirect from old `/author/username/` URLs to new CPT URLs

## Phase 6: Cleanup

28. Delete the old user-targeted ACF field groups (only after full verification)
29. Remove `personnel_user` and `expert_user` role definitions
30. Update `content_manager_map_meta_cap` filter -- remove the `edit_user`/`edit_users` case (no longer needed since personnel/expert data lives in the CPT) but keep the `unfiltered_html` case (still required for multisite)
31. Remove user profile accordion JS
32. Optionally bulk-delete the old personnel/expert user accounts

## Future: Review & Merge Duplicates

Personnel and expert records for the same real person will exist as separate `caes_hub_person` posts after migration. Both are valid and functional. Each post retains its source fields (`personnel_id`, `source_expert_id`, `writer_id`, `uga_email`, etc.) so duplicates can be identified and merged at any time using the existing scan/merge tools in the migration dashboard.

---

## Key Risks

- **Repeater field migration** is the highest-risk step -- thousands of posts with serialized ACF data need user IDs swapped to post IDs. Phase 4 code (dual user/post ID support) must be deployed before running the swap on production
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
