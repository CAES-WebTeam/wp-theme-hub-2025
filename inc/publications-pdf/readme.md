# Field Report 2025 Theme: Publication PDF Generation System

This system provides a robust solution for dynamically generating PDF versions of the 'publications' custom post type within the Field Report 2025 theme. It leverages WP-Cron for background processing, ensuring that PDF generation doesn't impact user experience during post saves.

## Overview

The system is composed of four main files, each with distinct responsibilities:
1.  `pdf-queue.php`: Manages the custom database table for the PDF generation queue.
2.  `publications-pdf.php`: Contains the core logic for rendering a publication's content into a PDF using TCPDF.
3.  `pdf-admin.php`: Handles the admin-side integration, including queueing PDFs on post save, displaying notices, and adding custom columns to the post list table.
4.  `pdf-cron.php`: Schedules and processes the PDF generation queue via WP-Cron.

## File Breakdown

### 1. `inc/publications-pdf/pdf-queue.php`
This file is responsible for setting up and managing a custom database table named `wp_pdf_generation_queue` (or `yourprefix_pdf_generation_queue`).

**Key Responsibilities:**
* **Table Creation:** `create_pdf_generation_queue_table()`: Creates the `pdf_generation_queue` table on `after_setup_theme` (or `init`).
* **Queue Management:** `insert_or_update_pdf_queue()`: Adds new PDF generation tasks to the queue or updates existing ones to 'pending' status.
* **Item Retrieval:** `get_pending_pdf_queue_items()`: Fetches a batch of pending items for processing by the cron job.
* **Status Updates:** `update_pdf_queue_status()`: Changes the status of a queue item (e.g., 'pending', 'processing', 'completed', 'failed').

### 2. `inc/publications-pdf/publications-pdf.php`
This file contains the PDF rendering logic, primarily using the TCPDF library (which should be located at `inc/tcpdf/tcpdf.php`).

**Key Responsibilities:**
* **PDF Generation:** `generate_publication_pdf_file($post_id)`: The core function that fetches post content and ACF fields, then uses TCPDF to create and save the PDF to the `wp-content/uploads/generated-pub-pdfs/` directory.
* **TCPDF Extension:** `MYPDF` class extends `TCPDF` to provide custom headers, footers (including a special last-page footer), and page break logic.
* **Content Processing:** `process_content_for_pdf()`: Pre-processes post content (e.g., tables, images, captions) to ensure better rendering and page break handling in TCPDF.
* **Styling:** `add_table_styling_for_pdf()`: Injects TCPDF-compatible CSS for tables and images.
* **Helper Functions:** Includes functions like `format_publication_number_for_display()` and `get_latest_published_date()` to format data for the PDF.

### 3. `inc/publications-pdf/pdf-admin.php`
This file manages the user interface aspects within the WordPress admin area.

**Key Responsibilities:**
* **Queue on Save:** `queue_pdf_generation_on_save()`: Hooked to `save_post_publications`, this function:
    * Validates post type, skips autosaves/revisions.
    * **Prevents queuing if `publication_number` ACF field is missing**, displaying an error notice.
    * Adds the post to the PDF generation queue with a 'pending' status.
    * Displays a transient admin notice informing the user that the PDF has been queued.
    * Schedules a single WP-Cron event to trigger the processing soon if one isn't already scheduled.
* **Admin Notices:** `display_pdf_generation_admin_notices()`: Displays transient messages (info, success, error) related to PDF generation status on post edit screens.
* **List Table Columns:**
    * `add_pdf_status_column()`: Adds 'PDF Status' and 'PDF Link' columns to the 'publications' post list table.
    * `display_pdf_status_column_content()`: Populates these custom columns with the current PDF generation status (from the queue table) and a download link if the PDF is generated.
* **Cache Directory:** `create_pdf_cache_directory()`: Ensures the `/wp-content/uploads/generated-pub-pdfs/` directory exists for saving PDFs.

### 4. `inc/publications-pdf/pdf-cron.php`
This file integrates the PDF generation process with WordPress's built-in cron system.

**Key Responsibilities:**
* **Cron Scheduling:** `schedule_pdf_generation_cron()`: Schedules the `my_pdf_generation_cron_hook` to run `every_five_minutes` using `wp_schedule_event()`.
* **Custom Interval:** `add_five_minute_cron_interval()`: Defines the 'every_five_minutes' interval for WP-Cron.
* **Queue Processor:** `process_pdf_generation_queue()`: This is the function executed by the cron hook:
    * Implements a transient-based lock (`_transient_pdf_generation_lock`) to prevent simultaneous runs.
    * Retrieves a batch of 'pending' PDF generation tasks from the queue.
    * Iterates through tasks, updates their status to 'processing', calls `generate_publication_pdf_file()`, and updates the queue status to 'completed' or 'failed'.
    * **Crucially, it temporarily disables and re-enables the `save_post_publications` hook (`remove_action`/`add_action`) around the `update_field('pdf_download_url', ...)` call.** This prevents an infinite loop where the cron's programmatic update triggers the `save_post` hook, which would otherwise re-queue the PDF unnecessarily.
    * Sets success or failure transient notices for the admin.
* **Theme Deactivation Cleanup:** `deactivate_pdf_generation_cron()`: Hooked to `switch_theme`, this function unschedules the custom cron event when the theme is deactivated, ensuring a clean slate. It also clears the `pdf_generation_lock` transient.

## Setup and Usage

1.  **Place Files:** Ensure the `inc/publications-pdf/` directory structure is maintained within the `wp-content/themes/wp-theme-hub-2025/` theme directory. The `tcpdf` library should be in `wp-content/themes/wp-theme-hub-2025/inc/tcpdf/`.
2.  **Include in `functions.php`:** Add the following lines to the `Field Report 2025` theme's `functions.php` file to include these components:
    ```php
    require_once get_template_directory() . '/inc/publications-pdf/pdf-queue.php';
    require_once get_template_directory() . '/inc/publications-pdf/publications-pdf.php';
    require_once get_template_directory() . '/inc/publications-pdf/pdf-admin.php';
    require_once get_template_directory() . '/inc/publications-pdf/pdf-cron.php';
    ```
3.  **Custom Post Type:** Ensure a 'publications' custom post type is registered, and ACF fields named `publication_number` (text field) and `pdf_download_url` (URL field) are associated with it. Also, an ACF Repeater field named `history` with sub-fields `status` (number) and `date` (date picker) for the publication history.
4.  **Theme Activation/Switch:** The `create_pdf_generation_queue_table()` will run on theme activation (or `init`) to create the necessary database table.
5.  **Usage:**
    * When a 'publications' post is edited or created and saved:
        * If the `publication_number` is missing, an error notice will appear, and the PDF will not be queued.
        * If the `publication_number` is present, an info notice "PDF generation for 'X' has been queued." will appear on the post edit screen.
    * Within 5 minutes (or sooner, depending on actual cron trigger), the WP-Cron job will run in the background.
    * On the Publications list table (`wp-admin/edit.php?post_type=publications`), the "PDF Status" column will update from "Not Queued" to "Pending", then "Processing", and finally "Completed" (or "Failed").
    * Once "Completed", a "Download PDF" button will appear in the "PDF Link" column.

## Important Notes

* **WP-Cron Reliability:** WP-Cron relies on visits to the website to trigger. For high-traffic sites, this is usually fine. For low-traffic development sites, manual cron triggering (e.g., visiting `wp-cron.php` directly, using a plugin like WP Crontrol, or setting up a real server cron job) might be necessary.
* **File Permissions:** Ensure the `wp-content/uploads/` directory and the new `wp-content/uploads/generated-pub-pdfs/` subdirectory have appropriate write permissions (e.g., 755 or 775) for the web server.
* **Error Logging:** Check the WordPress `debug.log` file for any errors related to PDF generation or the queue if issues arise.
* **TCPDF Fonts:** Custom fonts (Georgia TTF files) are loaded from `assets/fonts/`. Ensure these files are present in the `Field Report 2025` theme's `assets/fonts/` directory.
* **Logo:** Also ensure that the Extension logo file is available the `assets/images/` folder.
* **Pathing:** The `require_once get_template_directory() . '/inc/tcpdf/tcpdf.php';` and other `require_once` statements assume the files are located directly within the theme's `inc/publications-pdf/` directory.