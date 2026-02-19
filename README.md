# CAES Field Report WP Theme

## assets

Builds from `/src` folder.

## 📁 block-variations

## 📁 blocks

* Contains build process and files for the custom blocks in this theme. 
* `cd blocks` to enter directory, then run `npm run start` to build and start watching for changes.
* Working files are in `/blocks/src`.

## 📁 inc

* Contains theme's PHP files for functionality, including the ACF plugin files.
* `acf.php`: Initializes and customizes the Advanced Custom Fields (ACF) plugin by setting custom paths, conditionally displaying the ACF admin menu to specific users, and including the local ACF plugin files.
* `theme-support.php`: Contains functions and hooks related to overall theme behavior.
* `events-support.php, publications-support.php`: Provides support for specific custom post types.
* `blocks.php`: Handles registration of custom blocks used throughout the theme.

## 📁 src

Builds to `/assets` folder.

## CAES Hub Theme Changelog

| Version | Date | Changes | Contributor |
|---------|------|---------|-------------|
| 1.2.5 | 2026-02-19 | Reorganized user edit page | Ashley |
| 1.2.4 | 2026-02-18 | Add pub search to menu flyout | Ashley | 
| 1.2.3 | 2026-02-10 | Reveal block release | Ashley |
| 1.2.2 | 2026-02-04 | added smtp function that able to use smtp server | Mikey |
| 1.2.1 | 2026-02-03 | Small style fixes for figcaptions and checklist bullet style | Ashley |
| 1.2.0 | 2026-01-30 | Nav overflow fix on mobile, added change log, starting to list version number | Ashley |
| | 2026-01-28 | Reveal block created | Ashley |
| | 2026-01-27 | Trade Gothic for table captions | Ashley |
| | 2026-01-23 | Advanced search link fixed, dynamic heading over manual, min width for tables, convert covers to images in generated PDF, add medium thumbnail option to RSS | Ashley |
| | 2026-01-22 | Show expert resource breadcrumbs on term pages properly, page title and heading update on topic archives | Ashley |
| | 2026-01-21 | Hide to top button on shorthand stories when caption appears, replace marker pseudo element with ::before for Safari, force HTTPS on images, updated caption font weight | Ashley |
| | 2026-01-20 | Fix for Satsumas publication | Ashley |
| | 2026-01-16 | Update caption on classic tables, fix figcaption to caption | Ashley |
| | 2026-01-15 | Fix figcaption to caption, fix cover image height, table caption to Oswald, image width and title sizing, larger font on caption | Ashley |
| | 2026-01-14 | Fix on print footer rules, caption style added, fix table structure from figcaption to caption | Ashley |
| | 2026-01-13 | Hide sections on print | Ashley |
| | 2026-01-09 | Fix typo, fix image width, fix wrapping | Ashley |
| | 2026-01-08 | Name output corrected on pub print header, extensive pub print CSS edits | Ashley |
| | 2026-01-07 | More edits to pub print CSS | Ashley |
| | 2026-01-06 | Pub CSS adjustments | Ashley |
| | 2026-01-05 | Pub CSS adjustments, upload xlsm enabled, fix Trade Gothic folder name | Ashley |
| | 2026-01-02 | Add publish date to data layer on all post types | Ashley |
| | 2025-12-12 | Fix errors in editor, logging email issues | Ashley |
| | 2025-12-11 | Note support added for pubs | Ashley |
| | 2025-12-09 | Commented URL assembly section | jdk48542 |
| | 2025-12-05 | Update to gallery block to add single gallery option | Ashley |
| | 2025-12-04 | Update API URL, large size for image galleries | Ashley |
| | 2025-12-03 | Expert mark text size adjustment, focus fix for hidden menu, padding-50 helper class, fix padding on single templates, add expert mark for mobile, helper class for flex shrink 0, adjust padding/line height/font size on tables | Ashley |
| | 2025-12-02 | Use Trade Gothic for math elements in generated PDFs, new helper class for hiding on mobile, breadcrumb update to truncate on mobile, generated PDF mods for new fonts on tables | Ashley |
| | 2025-12-01 | Generated PDF mods for new fonts on tables | Ashley |
| | 2025-11-26 | Trade Gothic on imported tables, add Trade Gothic to theme, captions displaying on gallery option | Ashley |
| | 2025-11-25 | Remove default gallery block from inserter, make gallery look more like front end, troubleshoot Safari blinking issue with Parvus styles, gallery (CAES) block style edits and setting adjustments | Ashley |
| | 2025-11-18 | CAES gallery block created, replace custom lightbox gallery JS with Parvus to standardize | Ashley |
| | 2025-11-17 | Replace custom lightbox gallery JS with Parvus, using Parvus for default galleries, fix to hide legacy gallery, Symplectic query tool repairs and error handling | Ashley, jdk48542 |
| | 2025-11-13 | Add pub-print stylesheet | Ashley |
| | 2025-11-12 | Debug script for datalayer pushing, changing datalayer for authors to usernames | Ashley |
| | 2025-11-11 | Extending public post preview to 30 day expiration, data layer code and analytic reorg, Symplectic query tool created | Ashley, jdk48542 |
| | 2025-11-10 | Add page select to pub single template, fix for primary-topic-icon, adding datalayer tracking for different taxonomies, fixing pagination button color | Ashley |
| | 2025-11-07 | Fixing pagination button color, remove debugging, fix PDF titles generation, hide legacy gallery from inserter, homepage template update, fixes for pub title to add subtitle, page number on pubs for pagination | Ashley |
| | 2025-11-06 | Styling for page break nav, new page select block, Yoast title update when using pagination on pubs | Ashley |
| | 2025-11-05 | Adding all Oswald font families | Ashley |
| | 2025-10-31 | Nav updates, more TOC fixes, anchor fix for TOC block | Ashley |
| | 2025-10-30 | Personnel profile related updates | Ashley |
| | 2025-10-23 | Update to TOC block to account for paginated content, adjustments for profile image block and rewrite rule for pagination on pubs | Ashley |
| | 2025-10-22 | More edits to CSS for profile changes, updates for background image on user pages | Ashley |
| | 2025-10-21 | Added class for icon size and color, update to search to allow "4-H" as search term | Ashley |
| | 2025-10-20 | Debugging added for finding out when a post switches to draft | Ashley |
| | 2025-10-16 | Fixing issue in function for favicon | Ashley |
| | 2025-10-14 | Script protection, PDF button disable option | Ashley |
| | 2025-10-13 | Fix to cache author search, series titles for search, author search fixes, add authors to results in Relevanssi search, new full width template, Yoast schema mods | Ashley |
| | 2025-10-12 | Yoast schema mods | Ashley |
| | 2025-10-08 | Adjust featured posts padding on features, updating pub type descriptions, fix to RSS template and exclude additional topic terms on front end | Ashley |
| | 2025-10-03 | Add related content to shorthand template | Ashley |
| | 2025-10-02 | Add related content to shorthand template, add PDF button to summary, update CSS issues for mobile | Ashley |
| | 2025-09-26 | Fixing display issue for emails on event pages, adding events REST API, update to footer links to add AI policy, fix event order on archive pages, updated readme for events, fix event image issue with saving alt text | Ashley |
| | 2025-09-25 | Fix event image issue with saving alt text, updating ACF, add duplicates tab to topic management tool, trying a different PDF library | Ashley |
| | 2025-09-24 | Updating topic list to exclude peanut lab, remove peanut lab from search results, trying a different PDF library, fixing issue where embeds aren't showing | Ashley |
| | 2025-09-22 | Trying a different PDF library (Paged.js exploration) | Ashley |
| | 2025-09-19 | Size of paper issue on generated PDF | Ashley |
| | 2025-09-17 | Adding favicons for theme, fixing bug on author display, remove topic for peanut lab from query loops, removing department keywords from single pub pages, fixes to the print CSS, adding class to pub single template for disclaimer area | Ashley |
| | 2025-09-16 | Fixes to the print CSS, update to single template, make print link use PDF on pubs, fixing pub series template to include details, custom sorting for series pages by pub number | Ashley |
| | 2025-09-15 | Removed one time tool for backfilling pub and revision dates, fixing filter for publication category, updating styles, adding publish date for sorting, sorting by latest publish revision date, tool to update publication latest revision date, adding tool to debug event expiration issue, fixing sunset date function | Ashley |
| | 2025-09-12 | Setting up logging for when pubs unpublish, fixing error with flat author ID save, latest updated pubs, new way to exclude categories on pub feeds, adding new category taxonomy for publications | Ashley |
| | 2025-09-11 | Fix for descriptions on pub number block, fixing error, fix to make topic pages with no items 404, removing extra tools, fixing topic management tool | Ashley |
| | 2025-09-10 | Adding filtered topics block, fixed RSS template, fix for search, fix for series archive, fix for displaying preferred name on pub-details-authors block | Ashley |
| | 2025-09-05 | Fixing search, fixing soft publishing, fixing search redirect issue, fixing search scroll issue, debug soft publish feature | Ashley |
| | 2025-09-04 | Update to add artist credit on stories and pubs, adding artists selection to CAES author block | Ashley |
| | 2025-09-03 | Soft publish work, soft publish finalizing, debug for soft publish status, adding topic management and soft publish status | Ashley |
| | 2025-08-28 | Undoing previous fix, trying to fix queries, fixing timeout issues, duplicate posts finder update, debug related content, debug related post issues | Ashley |
| | 2025-08-27 | Fix tables in PDF generation | Ashley |
| | 2025-08-26 | Fix tables in PDF generation, fix for PDF generation, update to new post default content, fix for header brand, REST API updates for state issue labels | Ashley, jdk48542 |
| | 2025-08-25 | Fix header brand on mobile, new function to set pubs state issues, header brand link to CAES | Ashley, jdk48542 |
| | 2025-08-22 | User import fixes for email matching, news writers import, added screenshot for theme, version number update | Ashley, jdk48542 |
| | 2025-08-21 | User linking script error logging adjustments, fixed events landing template, shorthand stories in search results, fix to redirect on publication-series, update for breadcrumbs to show pub series landing page | Ashley, jdk48542 |
| | 2025-08-20 | Author linking script overhaul, trying to fix pub series | Ashley, jdk48542 |
| | 2025-08-19 | Reference hanging indent style for paragraph blocks, hiding save action for now | Ashley |
| | 2025-08-18 | Fix for red arrows on read more on Safari, removing search error logging, fix for external links on events | Ashley |
| | 2025-08-17 | Fix for mobile tap, fix for date display on events, fix to event featured image block, fix for archive pages on events, debug event feed, event import tool | Ashley |
| | 2025-08-16 | Archive page fix, updating stories-feed to work on archive pages, RSS update for topics, RSS feed fix for including shorthand_story, updates to user profile URLs, redirects for posts, fix for shorthand redirects, fix for missing custom authors, fix to pubs archive template, flyout menu fix, update for science you can trust links, fix display name on front end, fix email display, legacy gallery fix, fixes for sidebar and user phone block | Ashley |
| | 2025-08-15 | Taking out daily cron, removing duplicated expert users, fix order of user import interface, fix the tool for status syncing, fixing script for link-users, fixing script for updating img meta, user email squelching for import processes, tool to fix statuses, incorporate shorthand post types into post feeds | Ashley, jdk48542 |
| | 2025-08-14 | Incorporate shorthand post types into post feeds, fix for author template, user import improvements with email handling, user story feed issue, fix for CSS on lightbox, adding topic RSS feeds, RSS fixes, fix series term links | Ashley, jdk48542 |
| | 2025-08-13 | Fix for external URLs, news story linkage functions updated to pull data from APIs, fixing issue with one pub redirect, news writer and expert imports for duplicated emails, update to date sync tool, fix for latest page issue, redirect rules for pub topics, duplicate email workaround for main CAES personnel user import, redirects for news stories, fixes for publish dates to sync with release | Ashley, jdk48542 |
| | 2025-08-12 | Fixes for publish dates to sync with release, removing failed date sorting solution, date sorting issue | Ashley |
| | 2025-08-11 | Story meta association update, topic term fixer tool, meta tool update, adding needed JSON files, shorthand redirect update, user support script enhanced logging | Ashley, jdk48542 |
| | 2025-08-10 | New lightbox gallery block, moved tax-renamer to retired files | Ashley |
| | 2025-08-09 | Organizing custom tools into their own menu | Ashley |
| | 2025-08-08 | Publication history dates convert to WordPress-friendly format, pub history update script to use API, inactive users sync to pull inactive pubs authors, added redirect for shorthand and updated linking script, user import to set display names and nicknames, adding ASSOCIATION_PUBLICATION_PERSONNEL.json | Ashley, jdk48542 |
| | 2025-08-07 | Adjusted output scrolling, fix for story feeds on user pages, removed unneeded functions and event listeners, field mappings updates, fix to arrow style, fix for category pages, fix to search results display, added public friendly title field, fix to remove external publishers from sidebar, remove extra metabox for primary tax by Yoast, pubs keywords import to use API, fix for previews on news not working, fix to hand picked posts search | Ashley, jdk48542 |
| | 2025-08-06 | Field ID fixes for abstract/summary, update new post default content, bug fix in writing cleaned publication content, renamed helper functions, major update for specific pub import, more event updates, update to event workflow readme, fixing messages, fix for event ICS, fix for event template, small change to event management interface | Ashley, jdk48542 |
| | 2025-08-05 | Deactivation of KSES filters for imported pubs content, fixed bug, API call stored in transient with increased timeout, adjustment to when GTM runs | Ashley, jdk48542 |
| | 2025-08-04 | Adjust GTM to not run when logged in, recording event updates, field comparison check tweaks on ACF updates, reduced batch size for cURL timeout mitigation, event tracking on social share modal, template bg fixes, fix for pub layout | Ashley, jdk48542 |
| | 2025-08-03 | Updates to pub summary block, fixing emails, added event approval workflow, fixed location blocks | Ashley |
| | 2025-08-01 | Added publication import section | jdk48542 |
| | 2025-07-31 | Creating dry run subroutine for migration, updates to turn on revisions and fix tables in PDF output | Ashley, jdk48542 |
| | 2025-07-30 | Using publication IDs instead of numbers, no longer pulling posts with status trash, search tool for individual pubs, comparing datasets, fix for events submission page, making pub PDF generation happen on backend | Ashley, jdk48542 |
| | 2025-07-29 | Comparing datasets, pulling pubs from WordPress, AJAX tweaking, cleaned up PDF output for pubs | Ashley, jdk48542 |
| | 2025-07-28 | Manual revert to stable version, update to single template, theme.json update to add core heading styles, sidebar update, removed top stories header, package updates | Ashley, jdk48542 |
| | 2025-07-25 | Attempting to restore staging site, AJAX and error handling fixes, publications import via API, adding admin tool for main pubs import, fixed block to display authors, expiring events when last day passes, fixing permalink organization | Ashley, jdk48542 |
| | 2025-07-24 | Adding admin tool for main pubs import, fixes to events blocks | Ashley, jdk48542 |
| | 2025-07-23 | Event updates to add department taxonomy | Ashley |
| | 2025-07-22 | Hide legacy event fields and add user role for event submitters, add headings above story and pub feeds, story variation for author pages | Ashley |
| | 2025-07-21 | Ensuring format for phone is consistent, fix to breadcrumbs, new user bio or tagline block, more updates to search block, pagination block styling, search pagination, update to pubs search to allow other landing page, adjustments to search block | Ashley |
| | 2025-07-20 | Added language filter for search, added option for author filter on search block, expert resource label added to primary topic block, fixing bug with hand picked post, add custom Relevanssi search block | Ashley |
| | 2025-07-19 | Add custom Relevanssi search block | Ashley |
| | 2025-07-18 | Developing REST calls for NEWS_IMAGE and to replace JSON files, update for Relevanssi | Ashley, jdk48542 |
| | 2025-07-17 | Update for Relevanssi | Ashley |
| | 2025-07-16 | Developing REST calls to replace JSON files | jdk48542 |
| | 2025-07-14 | Step arrow style for blocks, fix to user feed block, linear gradient update | Ashley |
| | 2025-07-12 | New department block for users, new user feed block | Ashley |
| | 2025-07-10 | Breadcrumbs block added and templates updated | Ashley |
| | 2025-07-09 | Update to events landing template, CSS fix for legacy content, developing REST calls to replace JSON files, new flip card block | Ashley, jdk48542 |
| | 2025-07-07 | More fixes for import process, topic association for pubs | Ashley |
| | 2025-07-03 | Adjustment to fix import issue on pub summaries, pub history update tool added | Ashley |
| | 2025-07-02 | JSON updates for imports, updated pub keywords association, topic mover tool | Ashley |
| | 2025-07-01 | Updates to templates, fix for topic links, fix for pubs to add topics, switching to topics tax from keywords | Ashley |
| | 2025-06-30 | Topic taxonomy updates, fixing the filter for language on pub feeds, layout fixes and pattern updates, legacy gallery fix and style fix, single template update to add legacy gallery | Ashley |
| | 2025-06-27 | Comment out import legacy galleries tool | Ashley |
| | 2025-06-26 | Update to the legacy gallery import tool | Ashley |
| | 2025-06-25 | More template updates, adjustments to primary keyword icon display, debugging, new helper classes, fix for nav style, sidebar update, nav blocks, external publisher block, primary keyword | Ashley |
| | 2025-06-12 | Nav blocks | Ashley |
| | 2025-06-11 | Nav blocks | Ashley |
| | 2025-06-10 | Linking users to their stories | Ashley |
| | 2025-06-09 | Temp remove of related feeds on author pages, debugging user issues, tool to import legacy galleries | Ashley |
| | 2025-06-06 | Saving new templates to theme files, fixes for pubs template | Ashley |
| | 2025-06-05 | Pattern fixes, fixing break points for grid feeds | Ashley |
| | 2025-06-04 | Fix to grammar on EOO statement on events, hide placeholder emails on frontend, fix on authors display, release date fix on news posts, detect duplicates tool, set up for converting plain text release date to date field | Ashley |
| | 2025-06-03 | Fix to event landing page template, accessibility fix for share modals, featured image placeholders | Ashley |
| | 2025-06-02 | Related content pattern, hide legacy fields on event form, adding Ashley to see ACF when signed in, fixing pub landing page template, related and hand picked posts block | Ashley |
| | 2025-05-30 | Update functions.php, carousel fixes, hand picked posts updates, TOC adjustments | Ashley, frankel-chris |
| | 2025-05-29 | Update post status issue, pubs history, history fix, update import process, remove temp img fix | frankel-chris |
| | 2025-05-27 | Update for news/posts | frankel-chris |
| | 2025-05-23 | More updates to pub single | Ashley |
| | 2025-05-22 | Update functions.php, updates for publication template | Ashley, frankel-chris |
| | 2025-05-20 | Author pub feed edits | Ashley |
| | 2025-05-19 | Removing tool that backfills author IDs | Ashley |
| | 2025-05-15 | Temporary tool to backfill publication author lists, update redirect link, multiple pub updates | Ashley, frankel-chris |
| | 2025-05-14 | Update events form, error testing, image fix, updated search form with filters | frankel-chris |
| | 2025-05-13 | Pub summary word limit option, author block update | Ashley |
| | 2025-05-12 | Fix for responsitable, CSS adjustments | Ashley |
| | 2025-05-08 | CSS adjustments, update to single template, enqueue AJAX scripts, saved posts functionality, search shortcodes, more updates to legacy content | Ashley, frankel-chris |
| | 2025-05-07 | More updates to legacy content, update to descriptions for publication info option, fix to pub template, more pub single adjustments | Ashley |
| | 2025-05-06 | Fix for mobile menu style, legacy CSS fix, pub single adjustments, updating with Georgia fonts, updating pub single template and related blocks | Ashley |
| | 2025-05-02 | Expert mark block, misc | Ashley |
| | 2025-05-01 | Pause carousel on mobile when mostly off screen, CSS adjustment for carousel and legacy content, author snippet display update | Ashley |
| | 2025-04-30 | Featured image upload and support, featured image news JSON, remove authors/experts fix, revised news/pubs fix, carousel improvements and adjustments, font updates | Ashley, frankel-chris |
| | 2025-04-29 | Revised save post loop, temp save post fix, publication review | frankel-chris |
| | 2025-04-25 | Removing failed experiment, testing resaving a post's authors programmatically, template updates to remove feed logos and update to author block | Ashley |
| | 2025-04-24 | Publication review, moving external links function to news-support.php file, started readme and added function for external links, sync writers, add news writers, news/keywords JSON, fix duplicate news source issue, news support files | Ashley, frankel-chris |
| | 2025-04-23 | More CSS fixes for legacy content, responsitable fix, wrap classic block content with a wrapper | Ashley |
| | 2025-04-22 | Wrap classic block content with a wrapper | Ashley |
| | 2025-04-21 | CSS/JS approach to addressing legacy images, removing image styles temporarily, adding back image styles, responsitable | Ashley |
| | 2025-04-18 | Import style fixes, pub style fixes, remove empty p from front end on pubs | Ashley |
| | 2025-04-17 | Hide TOC when it hits bottom of article, TOC hide before touching body text | Ashley |
| | 2025-04-11 | Another fix for pub URL permalinks, APA style for date formats, series display on publications | Ashley |
| | 2025-04-10 | Fix for pub permalinks, separated series taxonomies and rewrite rules, pub-keyword script addition, pub-keyword JSON, update user support, archived users script | Ashley, frankel-chris |
| | 2025-04-07 | Event front end fixes, adding Google API key | Ashley |
| | 2025-04-04 | Footer update | Ashley |
| | 2025-04-03 | Fix to display of event submission form in editor | Ashley |
| | 2025-04-02 | Separated series taxonomies for pubs and events, redirects for pubs if visitor goes to pub number in URL | Ashley |
| | 2025-03-31 | Adjust mailchimp form styles | Ashley |
| | 2025-03-27 | Updated pubs support for import, fixed PDF download button to include PDF attachments if used | Ashley, frankel-chris |
| | 2025-03-26 | Shorthand CSS fix, login CSS fixes | Ashley |
| | 2025-03-24 | Author template and blocks added | Ashley |
| | 2025-03-19 | Added mailing/shipping address fields and support, user field groups, updated user support, updated theme support, updating thumbnail quality, add image to RSS feed, added button option to translation link | Ashley, frankel-chris |
| | 2025-03-18 | Pub single adjustment, publication details translation link | Ashley |
| | 2025-03-17 | Fix for single pub template, brand block update for pubs, integrate subtitle field on front end for pubs | Ashley |
| | 2025-03-14 | Misc updates, remove publications featured image block, updates to add translator option, removing brand block from news feeds and news single, font size option for event detail blocks and pattern additions | Ashley |
| | 2025-03-13 | Featured image support for pubs/events, updated support files | frankel-chris |
| | 2025-03-06 | Minor fix to nav, nav style updates, minor updates to event blocks | Ashley |
| | 2025-02-26 | Minor fixes to templates, template for 404 and index template adjustment, switch brand logo if Extension event, styles for nav, fix to events template, small template updates | Ashley |
| | 2025-02-25 | More fixes to backend for brand block, fix to content brand to make faster on edit screen, fixing pub feed pattern, adding pattern categories, template updates, misc updates | Ashley |
| | 2025-02-24 | Table styles | Ashley |
| | 2025-02-23 | CSS cleanup on imported pubs | Ashley |
| | 2025-02-21 | CSS cleanup on imported pubs, adding stories landing page, pub updates | Ashley |
| | 2025-02-19 | Pub updates, remove pub support, updated pubs support and fields | Ashley, frankel-chris |
| | 2025-02-14 | Pubs frontend updates round 4 | Ashley |
| | 2025-02-13 | Pubs related updates round 3 | Ashley |
| | 2025-02-12 | Pubs frontend pt 2 | Ashley |
| | 2025-02-07 | Pub block updates pt 1 | Ashley |
| | 2025-02-04 | Added patterns, new blocks | Ashley |
| | 2025-01-31 | Publications template, updated blocks, pub blocks, TCPDF library added, updated publications support and field groups | frankel-chris |
| | 2025-01-30 | Fix for shorthand CSS glitch, hand picked posts block added | Ashley |
| | 2025-01-29 | Fix to mobile carousel, adding dropshadow to left sidebar, adjusting shorthand template to include sidebar of site | Ashley |
| | 2025-01-16 | Allowing Shorthand CPT to work in REST API | Ashley |
| | 2025-01-15 | Frontend event updates pt 6, plus TOC and shorthand updates | Ashley |
| | 2024-12-18 | Frontend event updates pt 5 | Ashley |
| | 2024-12-17 | Frontend event updates pt 4 | Ashley |
| | 2024-12-12 | Frontend event updates pt 3 | Ashley |
| | 2024-12-09 | Frontend event updates pt 2 | Ashley |
| | 2024-12-05 | First round of updates for events front end | Ashley |
| | 2024-12-04 | Rearranged files, adding olympic color option | Ashley |
| | 2024-12-03 | Re-adding login.scss, removing article-action-icons block (moved to individual blocks) | Ashley |
| | 2024-11-25 | Create login.scss, update query.scss, editor.scss, main.scss, templates, theme.json, navigation.scss, post-feature.php, gulpfile.js, package.json, form style updates and search added | Ashley |
| | 2024-11-22 | Baked in ACF, other theme files, updated blocks | frankel-chris |
| | 2024-11-20 | Yet more TOC fixes, fixes for TOC block, updated login page and font reference | Ashley |
| | 2024-11-19 | New blocks, rearranging files | Ashley |
| | 2024-10-15 | Added templates, blocks, etc | Ashley |
| | 2024-09-03 | Initial dev work | Ashley |
| | 2024-08-28 | WP Pusher tests | Ashley |
| | 2024-08-26 | Initial commit | Ashley |