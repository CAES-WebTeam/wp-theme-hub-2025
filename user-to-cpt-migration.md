# Personnel/Expert CPT Migration Overview

## Background

Currently, ~5000+ personnel and expert/writer records are stored as WordPress users with custom roles (`personnel_user`, `expert_user`). These users serve as front-end directory records and are credited on posts, publications, and shorthand stories. Personnel data is actively synced from the CAES personnel API. Expert/writer data was previously imported from a separate CAES database but is now static.

Editorial staff are real WordPress users who log in and author content. They also have front-end profiles and are credited on publications/stories alongside personnel and experts.

## Goal

Migrate all personnel and expert/writer records from WordPress users to a `caes_hub_person` Custom Post Type (public URL slug: `/person/`). Editorial staff retain their WP user accounts for authentication but get linked `caes_hub_person` posts for their public profiles.

---

## Phase 1: Set Up the CPT and Fields

1. Register the `caes_hub_person` CPT with `'rewrite' => ['slug' => 'person']`
2. Duplicate all three ACF field groups ("Users", "Symplectic Elements", "Editorial") targeting `post_type == caes_hub_person` -- keep the originals on users untouched
3. Add a `linked_wp_user` ACF field (user type) to the `caes_hub_person` CPT for linking editorial staff accounts to their person posts

## Phase 2: Data Migration

4. Write a migration script with a dry-run mode that:
   - Reads all data from existing WordPress user meta and ACF user fields -- does NOT pull from any external API (expert/writer data is static and no longer synced from the CAES news database)
   - Creates a `caes_hub_person` CPT post for every `personnel_user` and `expert_user`
   - Creates a `caes_hub_person` CPT post for every editorial staff user (editors, content managers, administrators who have public profiles)
   - For editorial staff posts, populates the `linked_wp_user` field with their WP user ID
   - Copies all user meta and ACF fields (including repeaters) to the new posts
   - Builds and stores a `user_id => post_id` lookup table
   - Logs counts and discrepancies for validation
5. Run the migration script on staging, verify data integrity
6. Update every ACF repeater row (`authors`, `experts`, `translator`, `artists`) across all posts, publications, and shorthand stories -- swapping user IDs for CPT post IDs using the lookup table
7. Repopulate the flat meta fields (`all_author_ids`, `all_expert_ids`) with CPT post IDs across all content

## Phase 3: Update Sync Infrastructure

8. Rewrite `sync_personnel_users()` and `sync_personnel_users2()` to create/update `caes_hub_person` posts instead of users; rename to `sync_active_personnel()` and `sync_inactive_personnel_authors()`
9. Update the CAES Tools admin page to reflect the new sync targets
10. Rewrite both Symplectic import files to query `caes_hub_person` posts by meta and write to CPT post IDs
11. Retire `import_news_experts()` and `import_news_writers()`

## Phase 4: Update Front-End Code

12. Create a helper function `get_person_post_for_user($user_id)` that maps a WP user ID to the linked `caes_hub_person` post ID -- use this anywhere the theme needs to resolve `post_author` to a person profile
13. Update the 8 user blocks (`user-image`, `user-bio`, `user-name`, `user-email`, `user-phone`, `user-position`, `user-department`, `user-feed`) to read from `caes_hub_person` post meta instead of user meta -- keep block names unchanged so saved block markup in the database doesn't break
14. Update `pub-details-authors` block to read from `caes_hub_person` posts instead of user data
15. Update `update_flat_author_ids_meta()` in publications-support.php and `update_flat_expert_ids_meta()` in news-support.php to work with CPT post IDs
16. Update block-variations/index.php -- replace `is_author()` checks with `is_singular('caes_hub_person')` and adjust meta query logic

## Phase 5: URL Structure and Templates

17. Keep the `/person/{id}/{slug}/` URL structure using custom rewrite rules that resolve to `caes_hub_person` posts by post ID (replaces the current rules that resolve to `?author=`). Update the permalink filter to generate `/person/{post_id}/{slug}/` links. The slug portion is cosmetic and name-change-safe since the post ID is the stable identifier.
18. Use `author-2.html` as the basis for the new `single-caes_hub_person.html` template; remove both `author.html` and `author-2.html`
19. Add a redirect rule for old `/person/{user_id}/{slug}/` URLs -- generate a static `user_id => post_id` redirect map (stored as a WP option) during migration; only old-format URLs hit this lookup, and traffic to them fades over time
20. Add 301 redirect from old `/author/username/` URLs to new CPT URLs

## Phase 6: Cleanup

21. Delete the old user-targeted ACF field groups (only after full verification)
22. Remove `personnel_user` and `expert_user` role definitions
23. Update `content_manager_map_meta_cap` filter -- remove the `edit_user`/`edit_users` case (no longer needed since personnel/expert data lives in the CPT) but keep the `unfiltered_html` case (still required for multisite)
24. Remove user profile accordion JS
25. Optionally bulk-delete the old personnel/expert user accounts

---

## Key Risks

- **Repeater field migration** is the highest-risk step -- thousands of posts with serialized ACF data need user IDs swapped to post IDs
- **URL continuity** -- old links in emails, search engines, and external sites need working redirects
- **Block markup** -- renaming blocks would break saved content, so block names must stay as-is
- **Static expert/writer data** -- no re-import available, so the migration script is the only chance to get this data right
- **Editorial staff dual records** -- must ensure the `linked_wp_user` mapping stays in sync; if a new editorial user is onboarded, a `caes_hub_person` post needs to be created and linked

## Recommendations

- Do all phases on staging first
- Build the migration script with dry-run mode and detailed logging
- Validate record counts at every step (users vs posts created, repeater rows updated, flat meta populated)
- Keep the old user-targeted field groups and user accounts intact until the entire migration is verified in production
- Consider adding a hook on user registration/profile update that auto-creates or syncs a linked `caes_hub_person` post for editorial staff
