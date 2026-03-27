<?php
/**
 * Bulk Publication Assets Import Tool
 *
 * Admin utility to bulk-import HTML content and images into existing 'publications' CPT posts.
 * Upload a ZIP whose folders are named by publication number; each folder contains one HTML
 * file (same name as the folder) and any associated image files.
 *
 * For each publication in the payload:
 *   - If no matching post exists (publication_number ACF field): log a warning and skip.
 *   - If the post exists: upload images to the media library, patch src URLs in the HTML,
 *     and update post_content with the HTML wrapped in a wp:html Gutenberg block.
 *
 * Stop/resume is supported via a state machine stored in wp_options.
 */

define( 'PUB_ASSETS_STATE_KEY',   'pub_assets_import_state' );
define( 'PUB_ASSETS_UPLOAD_DIR',  'pub-assets-import' );
define( 'PUB_ASSETS_MAX_LOG',     500 );

// ── Admin menu ─────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'pub_assets_import_menu' );

function pub_assets_import_menu() {
	add_submenu_page(
		'caes-tools',
		'Bulk Publication Assets Import',
		'Pub Assets Import',
		'manage_options',
		'pub-assets-import',
		'pub_assets_import_page'
	);
}

// ── Enqueue ────────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'pub_assets_import_enqueue' );

function pub_assets_import_enqueue( $hook ) {
	if ( $hook !== 'caes-tools_page_pub-assets-import' ) {
		return;
	}
	wp_enqueue_style( 'wp-admin' );
	wp_add_inline_style( 'wp-admin', pub_assets_import_css() );
}

// ── AJAX handlers ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_pub_assets_upload', 'pub_assets_ajax_upload' );
add_action( 'wp_ajax_pub_assets_start',  'pub_assets_ajax_start' );
add_action( 'wp_ajax_pub_assets_tick',   'pub_assets_ajax_tick' );
add_action( 'wp_ajax_pub_assets_stop',   'pub_assets_ajax_stop' );
add_action( 'wp_ajax_pub_assets_reset',  'pub_assets_ajax_reset' );

// ── State helpers ──────────────────────────────────────────────────────────────

function pub_assets_default_state() {
	return [
		'status'         => 'idle',   // idle | ready | running | stopped | complete
		'upload_dir'     => '',        // absolute path to extracted payload directory
		'publications'   => [],        // [ ['number' => 'B-1461', 'dir' => '/abs/path'], … ]
		'total'          => 0,
		'current_index'  => 0,         // next publication to process
		'updated'        => 0,
		'skipped'        => 0,         // post not found in WP
		'error_count'    => 0,
		'errors'         => [],
		'log'            => [],
		'stop_requested' => false,
		'started_at'     => 0,
		'completed_at'   => 0,
	];
}

function pub_assets_get_state() {
	return get_option( PUB_ASSETS_STATE_KEY, pub_assets_default_state() );
}

function pub_assets_save_state( $state ) {
	update_option( PUB_ASSETS_STATE_KEY, $state, false );
}

function pub_assets_log( &$state, $level, $message ) {
	$state['log'][] = [
		'time'    => current_time( 'H:i:s' ),
		'level'   => $level,   // info | success | warning | error
		'message' => $message,
	];
	if ( count( $state['log'] ) > PUB_ASSETS_MAX_LOG ) {
		$state['log'] = array_slice( $state['log'], -PUB_ASSETS_MAX_LOG );
	}
}

// ── Upload & scan ──────────────────────────────────────────────────────────────

function pub_assets_ajax_upload() {
	check_ajax_referer( 'pub_assets_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	if ( empty( $_FILES['payload_zip'] ) || $_FILES['payload_zip']['error'] !== UPLOAD_ERR_OK ) {
		$code = isset( $_FILES['payload_zip'] ) ? $_FILES['payload_zip']['error'] : 'missing';
		wp_send_json_error( 'Upload error (code: ' . $code . '). Please try again.' );
	}

	$file = $_FILES['payload_zip'];

	// Basic MIME check — accept common ZIP variants
	$allowed_mimes = [
		'application/zip',
		'application/x-zip',
		'application/x-zip-compressed',
		'application/octet-stream',
	];
	$mime = mime_content_type( $file['tmp_name'] );
	if ( ! in_array( $mime, $allowed_mimes, true ) ) {
		@unlink( $file['tmp_name'] );
		wp_send_json_error( 'Uploaded file does not appear to be a ZIP (detected: ' . esc_html( $mime ) . ').' );
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_send_json_error( 'The ZipArchive PHP extension is not available on this server.' );
	}

	// Prepare extraction directory
	$uploads     = wp_upload_dir();
	$extract_dir = trailingslashit( $uploads['basedir'] ) . PUB_ASSETS_UPLOAD_DIR . '/' . time();
	wp_mkdir_p( $extract_dir );

	// Validate ZIP entries for path traversal before extracting
	$zip    = new ZipArchive();
	$opened = $zip->open( $file['tmp_name'] );
	if ( $opened !== true ) {
		@unlink( $file['tmp_name'] );
		wp_send_json_error( 'Could not open ZIP archive (ZipArchive error code: ' . $opened . ').' );
	}

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( strpos( $name, '..' ) !== false ) {
			$zip->close();
			@unlink( $file['tmp_name'] );
			pub_assets_rmdir( $extract_dir );
			wp_send_json_error( 'ZIP contains a path traversal entry and cannot be used.' );
		}
	}

	$zip->extractTo( $extract_dir );
	$zip->close();
	@unlink( $file['tmp_name'] );

	// Scan extracted directory for publication folders
	$publications = pub_assets_scan( $extract_dir );

	if ( empty( $publications ) ) {
		pub_assets_rmdir( $extract_dir );
		wp_send_json_error(
			'No publications found. Expected folders named by publication number, each containing ' .
			'an HTML file of the same name (e.g. B-1461/B-1461.html).'
		);
	}

	// Clear any previous state, including its extracted files
	$old_state = pub_assets_get_state();
	if ( ! empty( $old_state['upload_dir'] ) && is_dir( $old_state['upload_dir'] ) ) {
		pub_assets_rmdir( $old_state['upload_dir'] );
	}

	$state                  = pub_assets_default_state();
	$state['status']        = 'ready';
	$state['upload_dir']    = $extract_dir;
	$state['publications']  = $publications;
	$state['total']         = count( $publications );

	pub_assets_log( $state, 'info', 'Payload scanned: ' . count( $publications ) . ' publication(s) found.' );
	pub_assets_save_state( $state );

	wp_send_json_success( [
		'state'       => $state,
		'pub_count'   => count( $publications ),
		'pub_numbers' => array_column( $publications, 'number' ),
	] );
}

// ── Scan helper ────────────────────────────────────────────────────────────────

function pub_assets_scan( $base_dir ) {
	$publications = [];
	$real_base    = realpath( $base_dir );

	try {
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iter as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			if ( strtolower( $file->getExtension() ) !== 'html' ) {
				continue;
			}

			$pub_dir    = $file->getPath();
			$pub_number = basename( $pub_dir );

			// HTML file must be named [publication-number].html
			if ( $file->getBasename( '.html' ) !== $pub_number ) {
				continue;
			}

			// Skip macOS metadata directories
			if ( strpos( $pub_dir, '__MACOSX' ) !== false ) {
				continue;
			}

			// Ensure the path hasn't escaped the base directory
			$real_pub_dir = realpath( $pub_dir );
			if ( $real_pub_dir === false || strpos( $real_pub_dir, $real_base ) !== 0 ) {
				continue;
			}

			$publications[ $pub_number ] = [
				'number' => $pub_number,
				'dir'    => $pub_dir,
			];
		}
	} catch ( Exception $e ) {
		// Return whatever was found before the error
	}

	// Sort by publication number for deterministic processing order
	uksort( $publications, 'strnatcasecmp' );

	return array_values( $publications );
}

// ── Start / Resume ─────────────────────────────────────────────────────────────

function pub_assets_ajax_start() {
	check_ajax_referer( 'pub_assets_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	$state = pub_assets_get_state();

	if ( ! in_array( $state['status'], [ 'ready', 'stopped' ], true ) ) {
		wp_send_json_error( 'Cannot start: current status is "' . $state['status'] . '".' );
	}

	$state['status']         = 'running';
	$state['stop_requested'] = false;
	if ( ! $state['started_at'] ) {
		$state['started_at'] = time();
	}

	$resume_msg = $state['current_index'] > 0
		? ' Resuming from item ' . ( $state['current_index'] + 1 ) . ' of ' . $state['total'] . '.'
		: '';
	pub_assets_log( $state, 'info', 'Import started.' . $resume_msg );
	pub_assets_save_state( $state );

	wp_send_json_success( [ 'state' => $state ] );
}

// ── Tick ───────────────────────────────────────────────────────────────────────

function pub_assets_ajax_tick() {
	check_ajax_referer( 'pub_assets_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	// Give each tick its own generous time budget
	@set_time_limit( 120 );

	$state = pub_assets_get_state();

	// Honor a stop request before doing any work
	if ( $state['stop_requested'] ) {
		$state['status']         = 'stopped';
		$state['stop_requested'] = false;
		pub_assets_log(
			$state, 'warning',
			'Stopped by user at item ' . ( $state['current_index'] + 1 ) . ' of ' . $state['total'] . '.'
		);
		pub_assets_save_state( $state );
		wp_send_json_success( pub_assets_tick_response( $state, [] ) );
		return;
	}

	if ( $state['status'] !== 'running' ) {
		wp_send_json_success( pub_assets_tick_response( $state, [] ) );
		return;
	}

	// Check for completion
	if ( $state['current_index'] >= $state['total'] ) {
		$state['status']       = 'complete';
		$state['completed_at'] = time();
		pub_assets_log( $state, 'info', sprintf(
			'Import complete. Updated: %d | Skipped (not found): %d | Errors: %d',
			$state['updated'], $state['skipped'], $state['error_count']
		) );
		pub_assets_save_state( $state );
		wp_send_json_success( pub_assets_tick_response( $state, [] ) );
		return;
	}

	$pub           = $state['publications'][ $state['current_index'] ];
	$log_idx_start = count( $state['log'] );

	$result = pub_assets_process_one( $pub['number'], $pub['dir'], $state );

	switch ( $result['status'] ) {
		case 'updated':
			$state['updated']++;
			pub_assets_log( $state, 'success',
				'[' . $pub['number'] . '] Updated (post ID ' . $result['post_id'] . ').'
			);
			if ( ! empty( $result['images_uploaded'] ) ) {
				pub_assets_log( $state, 'info',
					'[' . $pub['number'] . '] ' . $result['images_uploaded'] . ' image(s) uploaded.'
				);
			}
			if ( ! empty( $result['images_reused'] ) ) {
				pub_assets_log( $state, 'info',
					'[' . $pub['number'] . '] ' . $result['images_reused'] . ' image(s) already in media library.'
				);
			}
			break;

		case 'skipped':
			$state['skipped']++;
			pub_assets_log( $state, 'warning',
				'[' . $pub['number'] . '] No matching post found — skipped.'
			);
			break;

		case 'error':
			$state['error_count']++;
			$state['errors'][] = '[' . $pub['number'] . '] ' . $result['message'];
			pub_assets_log( $state, 'error',
				'[' . $pub['number'] . '] Error: ' . $result['message']
			);
			break;
	}

	$state['current_index']++;
	$new_log = array_slice( $state['log'], $log_idx_start );
	pub_assets_save_state( $state );

	wp_send_json_success( pub_assets_tick_response( $state, $new_log ) );
}

function pub_assets_tick_response( $state, $new_log ) {
	return [
		'new_log' => $new_log,
		'state'   => [
			'status'        => $state['status'],
			'current_index' => $state['current_index'],
			'total'         => $state['total'],
			'updated'       => $state['updated'],
			'skipped'       => $state['skipped'],
			'error_count'   => $state['error_count'],
			'completed_at'  => $state['completed_at'],
		],
	];
}

// ── Stop ───────────────────────────────────────────────────────────────────────

function pub_assets_ajax_stop() {
	check_ajax_referer( 'pub_assets_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	$state                   = pub_assets_get_state();
	$state['stop_requested'] = true;
	pub_assets_save_state( $state );

	wp_send_json_success( [ 'message' => 'Stop requested.' ] );
}

// ── Reset ──────────────────────────────────────────────────────────────────────

function pub_assets_ajax_reset() {
	check_ajax_referer( 'pub_assets_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	$state = pub_assets_get_state();
	if ( ! empty( $state['upload_dir'] ) && is_dir( $state['upload_dir'] ) ) {
		pub_assets_rmdir( $state['upload_dir'] );
	}

	delete_option( PUB_ASSETS_STATE_KEY );

	wp_send_json_success( [ 'message' => 'State reset.' ] );
}

// ── Core: process one publication ──────────────────────────────────────────────

/**
 * Convert a payload folder name to the WordPress publication_number value.
 * The first hyphen in the folder name is a space in the stored pub number;
 * all subsequent hyphens are literal (e.g. "B-1524-3" → "B 1524-3").
 */
function pub_assets_folder_to_pub_number( $folder_name ) {
	return preg_replace( '/-/', ' ', $folder_name, 1 );
}

function pub_assets_process_one( $pub_number, $pub_dir, &$state ) {
	$result = [
		'status'          => '',
		'message'         => '',
		'post_id'         => null,
		'images_uploaded' => 0,
		'images_reused'   => 0,
	];

	if ( ! is_dir( $pub_dir ) ) {
		$result['status']  = 'error';
		$result['message'] = 'Directory not found on server (was the session reset?): ' . $pub_dir;
		return $result;
	}

	$html_file = $pub_dir . '/' . $pub_number . '.html';
	if ( ! file_exists( $html_file ) ) {
		$result['status']  = 'error';
		$result['message'] = 'HTML file not found: ' . basename( $html_file );
		return $result;
	}

	// Look up the post by publication_number ACF field.
	// Folder names use a hyphen where the stored pub number has a space (first hyphen only).
	$wp_pub_number = pub_assets_folder_to_pub_number( $pub_number );
	$posts = get_posts( [
		'post_type'      => 'publications',
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'fields'         => 'ids',
		'meta_query'     => [ [
			'key'   => 'publication_number',
			'value' => $wp_pub_number,
		] ],
	] );

	if ( empty( $posts ) ) {
		$result['status'] = 'skipped';
		return $result;
	}

	$post_id         = (int) $posts[0];
	$result['post_id'] = $post_id;

	$html = file_get_contents( $html_file );
	if ( $html === false ) {
		$result['status']  = 'error';
		$result['message'] = 'Could not read HTML file.';
		return $result;
	}

	// Upload images and replace placeholder src URLs
	$image_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ];
	foreach ( scandir( $pub_dir ) as $filename ) {
		if ( $filename === '.' || $filename === '..' ) {
			continue;
		}
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $image_extensions, true ) ) {
			continue;
		}

		$image_path    = $pub_dir . '/' . $filename;
		$attachment_id = pub_assets_upload_image( $image_path, $post_id, $filename );

		if ( is_wp_error( $attachment_id ) ) {
			pub_assets_log( $state, 'warning',
				'[' . $pub_number . '] Image upload failed for ' . $filename . ': ' .
				$attachment_id->get_error_message()
			);
			continue;
		}

		if ( is_array( $attachment_id ) && isset( $attachment_id['reused'] ) ) {
			$result['images_reused']++;
			$attachment_id = $attachment_id['id'];
		} else {
			$result['images_uploaded']++;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_url ) {
			continue;
		}

		// Replace the YYYY/MM placeholder src (absolute or root-relative) with the real URL
		$escaped = preg_quote( $filename, '#' );
		$html    = preg_replace(
			'#(?:https?://[^/]+)?/wp-content/uploads/YYYY/MM/' . $escaped . '#',
			$attachment_url,
			$html
		);
	}

	// Wrap in a Gutenberg custom HTML block
	$post_content = "<!-- wp:html -->\n" . trim( $html ) . "\n<!-- /wp:html -->";

	$update = wp_update_post( [
		'ID'           => $post_id,
		'post_content' => $post_content,
	], true );

	if ( is_wp_error( $update ) ) {
		$result['status']  = 'error';
		$result['message'] = 'wp_update_post failed: ' . $update->get_error_message();
		return $result;
	}

	$result['status'] = 'updated';
	return $result;
}

// ── Image upload helper ────────────────────────────────────────────────────────

function pub_assets_upload_image( $image_path, $post_id, $filename ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// Check if an attachment with this filename already exists in the media library
	$existing = get_posts( [
		'post_type'   => 'attachment',
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_query'  => [ [
			'key'     => '_wp_attached_file',
			'value'   => $filename,
			'compare' => 'LIKE',
		] ],
	] );

	if ( ! empty( $existing ) ) {
		return [ 'reused' => true, 'id' => (int) $existing[0] ];
	}

	// Copy to a true temp file so media_handle_sideload can move it without
	// destroying our extracted copy (needed in case of resume after stop).
	$tmp = wp_tempnam( $filename );
	if ( ! copy( $image_path, $tmp ) ) {
		return new WP_Error( 'copy_failed', 'Could not copy image to temp location.' );
	}

	$file_array = [
		'name'     => $filename,
		'tmp_name' => $tmp,
		'error'    => 0,
		'size'     => filesize( $tmp ),
		'type'     => mime_content_type( $tmp ),
	];

	$attachment_id = media_handle_sideload( $file_array, $post_id, null, [ 'test_form' => false ] );

	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
	}

	return $attachment_id;
}

// ── Filesystem helper ──────────────────────────────────────────────────────────

function pub_assets_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( array_diff( scandir( $dir ), [ '.', '..' ] ) as $item ) {
		$path = $dir . '/' . $item;
		is_dir( $path ) ? pub_assets_rmdir( $path ) : @unlink( $path );
	}
	@rmdir( $dir );
}

// ── CSS ────────────────────────────────────────────────────────────────────────

function pub_assets_import_css() {
	return '
		.pub-assets-wrap { max-width: 960px; margin: 20px 0; }
		.pub-assets-section { margin-bottom: 28px; }
		.pub-assets-upload-area {
			border: 2px dashed #aaa;
			border-radius: 6px;
			padding: 28px 24px;
			background: #fafafa;
			margin: 12px 0;
		}
		.pub-assets-upload-area p { margin: 0 0 10px; }
		.pub-assets-upload-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
		.pub-assets-console {
			background: #1e1e1e;
			color: #d4d4d4;
			font-family: "Courier New", Courier, monospace;
			font-size: 12px;
			line-height: 1.7;
			padding: 14px 16px;
			height: 360px;
			overflow-y: auto;
			border-radius: 4px;
			border: 1px solid #3c3c3c;
			margin-top: 8px;
		}
		.pub-assets-console .log-info    { color: #9cdcfe; }
		.pub-assets-console .log-success { color: #4ec9b0; }
		.pub-assets-console .log-warning { color: #dcdcaa; }
		.pub-assets-console .log-error   { color: #f44747; }
		.pub-assets-console .log-time    { color: #6a9955; margin-right: 8px; }
		.pub-assets-progress-wrap { margin: 10px 0 4px; }
		.pub-assets-progress {
			background: #e0e0e0;
			border-radius: 3px;
			height: 20px;
			overflow: hidden;
		}
		.pub-assets-progress-bar {
			height: 100%;
			background: #0073aa;
			border-radius: 3px;
			transition: width 0.4s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #fff;
			font-size: 11px;
			min-width: 0;
		}
		.pub-assets-progress-text { font-size: 12px; color: #666; margin: 4px 0 12px; }
		.pub-assets-stats { display: flex; gap: 14px; margin: 12px 0; flex-wrap: wrap; }
		.pub-assets-stat {
			background: #f5f5f5;
			border: 1px solid #ddd;
			border-radius: 3px;
			padding: 8px 14px;
			font-size: 13px;
			min-width: 110px;
		}
		.pub-assets-stat strong { display: block; font-size: 22px; line-height: 1.2; }
		.pub-assets-actions { margin: 14px 0; display: flex; gap: 8px; align-items: center; }
		#pub-assets-messages .notice { margin: 6px 0 14px; }
		.pub-assets-scan-table { margin-top: 10px; }
	';
}

// ── Page render ────────────────────────────────────────────────────────────────

function pub_assets_import_page() {
	$state  = pub_assets_get_state();
	$status = $state['status'];
	$nonce  = wp_create_nonce( 'pub_assets_nonce' );

	$show_upload  = ! in_array( $status, [ 'running', 'stopped', 'complete' ], true );
	$show_control = $status !== 'idle';
	?>
	<div class="wrap pub-assets-wrap">
		<h1>Bulk Publication Assets Import</h1>
		<p>
			Upload a ZIP payload to update existing <code>publications</code> posts with HTML content
			and images. Publications not found in WordPress are reported and skipped — no post is
			ever created by this tool.
		</p>
		<p style="color:#666; font-size:12px;">
			Content is stored as a single <strong>Custom HTML</strong> Gutenberg block. After import
			you can open any post in the block editor and use <em>Transform → Convert to blocks</em>
			to decompose it into native paragraph, heading, and image blocks.
		</p>

		<div id="pub-assets-messages"></div>

		<?php /* ── Step 1: Upload ─────────────────────────────────────────── */ ?>
		<div id="pub-assets-upload-section" class="pub-assets-section"
			 style="<?php echo $show_upload ? '' : 'display:none'; ?>">
			<h2>Step 1 — Upload Payload ZIP</h2>
			<div class="pub-assets-upload-area">
				<p>
					Select the payload ZIP file. Inside the ZIP, each publication lives in its own
					folder named by its publication number (e.g. <code>B-1461/</code>), containing
					<code>B-1461.html</code> and any image files. Any level of outer wrapper folders
					is fine.
				</p>
				<div class="pub-assets-upload-row">
					<input type="file" id="pub-assets-zip-input" accept=".zip" />
					<button id="pub-assets-upload-btn" class="button button-primary">Upload &amp; Scan</button>
					<span id="pub-assets-upload-status" style="font-style:italic; color:#0073aa;"></span>
				</div>
			</div>
		</div>

		<?php /* ── Step 2: Review & run ──────────────────────────────────── */ ?>
		<div id="pub-assets-control-section" class="pub-assets-section"
			 style="<?php echo $show_control ? '' : 'display:none'; ?>">
			<h2>Step 2 — Review &amp; Run Import</h2>

			<div class="pub-assets-stats">
				<div class="pub-assets-stat">
					<strong id="stat-total"><?php echo esc_html( $state['total'] ); ?></strong>
					In payload
				</div>
				<div class="pub-assets-stat">
					<strong id="stat-updated"><?php echo esc_html( $state['updated'] ); ?></strong>
					Updated
				</div>
				<div class="pub-assets-stat">
					<strong id="stat-skipped"><?php echo esc_html( $state['skipped'] ); ?></strong>
					Not found
				</div>
				<div class="pub-assets-stat">
					<strong id="stat-errors"><?php echo esc_html( $state['error_count'] ); ?></strong>
					Errors
				</div>
			</div>

			<div class="pub-assets-progress-wrap">
				<div class="pub-assets-progress">
					<?php
					$pct = $state['total'] > 0
						? round( ( $state['current_index'] / $state['total'] ) * 100 )
						: 0;
					?>
					<div class="pub-assets-progress-bar" id="pub-assets-progress-bar"
						 style="width:<?php echo $pct; ?>%">
						<?php echo $pct > 4 ? $pct . '%' : ''; ?>
					</div>
				</div>
				<p class="pub-assets-progress-text" id="pub-assets-progress-text">
					<?php
					if ( $state['current_index'] > 0 && $state['total'] > 0 ) {
						echo esc_html( $state['current_index'] . ' / ' . $state['total'] . ' processed' );
					}
					?>
				</p>
			</div>

			<div class="pub-assets-actions">
				<button id="pub-assets-start-btn" class="button button-primary"
						style="<?php echo $status === 'running' ? 'display:none' : ''; ?>">
					<?php echo $status === 'stopped' ? 'Resume Import' : 'Start Import'; ?>
				</button>
				<button id="pub-assets-stop-btn" class="button button-secondary"
						style="<?php echo $status === 'running' ? '' : 'display:none'; ?>">
					Stop Import
				</button>
				<button id="pub-assets-reset-btn" class="button">
					Reset / New Upload
				</button>
			</div>

			<h3 style="margin-bottom:0;">Console</h3>
			<div id="pub-assets-console" class="pub-assets-console">
				<?php
				foreach ( $state['log'] as $entry ) {
					echo pub_assets_format_log_entry_html( $entry );
				}
				?>
			</div>

			<?php if ( ! empty( $state['publications'] ) ) : ?>
			<details style="margin-top:18px;">
				<summary style="cursor:pointer; font-weight:600; font-size:14px;">
					Publication List (<?php echo count( $state['publications'] ); ?> items)
				</summary>
				<table class="wp-list-table widefat fixed striped pub-assets-scan-table">
					<thead>
						<tr>
							<th style="width:50px;">#</th>
							<th>Publication Number</th>
							<th>Directory (server path)</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $state['publications'] as $i => $pub ) : ?>
						<tr>
							<td><?php echo $i + 1; ?></td>
							<td><?php echo esc_html( $pub['number'] ); ?></td>
							<td style="font-size:11px; color:#888;"><?php echo esc_html( $pub['dir'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</details>
			<?php endif; ?>
		</div>
	</div>

	<script>
	jQuery(document).ready(function ($) {

		var nonce      = <?php echo json_encode( $nonce ); ?>;
		var ajaxUrl    = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var isRunning  = <?php echo json_encode( $status === 'running' ); ?>;
		var total      = <?php echo json_encode( (int) $state['total'] ); ?>;
		var TICK_DELAY = 300; // ms gap between end of one tick and start of next

		// ── Helpers ───────────────────────────────────────────────────────────

		function escHtml(s) {
			return String(s)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}

		function formatEntry(entry) {
			var cls = 'log-' + (entry.level || 'info');
			return '<div class="' + cls + '">' +
				'<span class="log-time">[' + escHtml(entry.time) + ']</span>' +
				escHtml(entry.message) +
				'</div>';
		}

		function appendLog(entries) {
			if (!entries || !entries.length) return;
			var $c = $('#pub-assets-console');
			$.each(entries, function (_, e) { $c.append(formatEntry(e)); });
			$c[0].scrollTop = $c[0].scrollHeight;
		}

		function showNotice(type, msg) {
			var cls = { error: 'notice-error', success: 'notice-success', info: 'notice-info' }[type] || 'notice-info';
			$('#pub-assets-messages').html(
				'<div class="notice ' + cls + ' is-dismissible"><p>' + escHtml(msg) + '</p></div>'
			);
		}

		function updateStats(s) {
			if (s.total         != null) { total = s.total; $('#stat-total').text(s.total); }
			if (s.updated       != null) $('#stat-updated').text(s.updated);
			if (s.skipped       != null) $('#stat-skipped').text(s.skipped);
			if (s.error_count   != null) $('#stat-errors').text(s.error_count);
			if (s.current_index != null) {
				var pct = total > 0 ? Math.round((s.current_index / total) * 100) : 0;
				$('#pub-assets-progress-bar').css('width', pct + '%').text(pct > 4 ? pct + '%' : '');
				$('#pub-assets-progress-text').text(s.current_index + ' / ' + total + ' processed');
			}
		}

		function applyStatus(status) {
			if (status === 'running') {
				$('#pub-assets-start-btn').hide();
				$('#pub-assets-stop-btn').show();
				$('#pub-assets-upload-section').hide();
			} else if (status === 'stopped') {
				$('#pub-assets-start-btn').text('Resume Import').show();
				$('#pub-assets-stop-btn').hide();
			} else if (status === 'complete') {
				$('#pub-assets-start-btn').hide();
				$('#pub-assets-stop-btn').hide();
			} else if (status === 'ready') {
				$('#pub-assets-start-btn').text('Start Import').show();
				$('#pub-assets-stop-btn').hide();
				$('#pub-assets-control-section').show();
			} else if (status === 'idle') {
				$('#pub-assets-control-section').hide();
				$('#pub-assets-upload-section').show();
			}
		}

		// ── Upload ────────────────────────────────────────────────────────────

		$('#pub-assets-upload-btn').on('click', function () {
			var file = $('#pub-assets-zip-input')[0].files[0];
			if (!file) { showNotice('error', 'Please select a ZIP file first.'); return; }

			var $btn = $(this).prop('disabled', true);
			$('#pub-assets-upload-status').text('Uploading & scanning\u2026');

			var fd = new FormData();
			fd.append('action',      'pub_assets_upload');
			fd.append('nonce',       nonce);
			fd.append('payload_zip', file);

			$.ajax({
				url: ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false,
				success: function (r) {
					$btn.prop('disabled', false);
					$('#pub-assets-upload-status').text('');
					if (r.success) {
						showNotice('success', 'Payload scanned: ' + r.data.pub_count + ' publication(s) found.');
						total = r.data.pub_count;
						updateStats(r.data.state);
						appendLog(r.data.state.log || []);
						applyStatus('ready');
					} else {
						showNotice('error', 'Upload failed: ' + (r.data || 'Unknown error.'));
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					$('#pub-assets-upload-status').text('');
					showNotice('error', 'AJAX error during upload. Check your server error log.');
				}
			});
		});

		// ── Start / Resume ────────────────────────────────────────────────────

		$('#pub-assets-start-btn').on('click', function () {
			$.post(ajaxUrl, { action: 'pub_assets_start', nonce: nonce }, function (r) {
				if (r.success) {
					isRunning = true;
					applyStatus('running');
					tick();
				} else {
					showNotice('error', 'Could not start: ' + (r.data || 'Unknown error.'));
				}
			});
		});

		// ── Stop ──────────────────────────────────────────────────────────────

		$('#pub-assets-stop-btn').on('click', function () {
			$(this).prop('disabled', true).text('Stopping\u2026');
			isRunning = false;
			$.post(ajaxUrl, { action: 'pub_assets_stop', nonce: nonce }, function () {
				$('#pub-assets-stop-btn').prop('disabled', false).text('Stop Import');
				// Actual stopped status appears when the in-flight tick resolves
			});
		});

		// ── Reset ─────────────────────────────────────────────────────────────

		$('#pub-assets-reset-btn').on('click', function () {
			if (!confirm('Reset will clear all import progress and delete the extracted payload files from the server. Continue?')) return;
			isRunning = false;
			$.post(ajaxUrl, { action: 'pub_assets_reset', nonce: nonce }, function (r) {
				if (r.success) { location.reload(); }
				else { showNotice('error', 'Reset failed: ' + (r.data || 'Unknown error.')); }
			});
		});

		// ── Tick loop ─────────────────────────────────────────────────────────

		function tick() {
			if (!isRunning) return;

			$.post(ajaxUrl, { action: 'pub_assets_tick', nonce: nonce })
				.done(function (r) {
					if (!r.success) {
						isRunning = false;
						showNotice('error', 'Tick error: ' + (r.data || 'Unknown.'));
						applyStatus('stopped');
						return;
					}

					var d = r.data;
					appendLog(d.new_log || []);
					updateStats(d.state || {});

					var status = (d.state || {}).status || 'running';

					if (status === 'running') {
						setTimeout(tick, TICK_DELAY);
					} else {
						isRunning = false;
						applyStatus(status);
						if (status === 'complete') {
							showNotice('success', 'Import complete!');
						} else if (status === 'stopped') {
							showNotice('info', 'Import stopped. Click \u201cResume Import\u201d to continue.');
						}
					}
				})
				.fail(function () {
					isRunning = false;
					showNotice('error', 'AJAX request failed during processing. Import paused — click Resume to retry.');
					applyStatus('stopped');
				});
		}

		// Auto-resume if a run was in progress when the page loaded (e.g. after a browser refresh)
		if (isRunning) { tick(); }

		// Auto-scroll console to bottom on load
		var $c = $('#pub-assets-console');
		if ($c.length) { $c[0].scrollTop = $c[0].scrollHeight; }
	});
	</script>
	<?php
}

// ── Log entry formatter (PHP, for initial page render) ─────────────────────────

function pub_assets_format_log_entry_html( $entry ) {
	$cls  = 'log-' . esc_attr( $entry['level'] ?? 'info' );
	$time = esc_html( $entry['time'] ?? '' );
	$msg  = esc_html( $entry['message'] ?? '' );
	return "<div class=\"{$cls}\"><span class=\"log-time\">[{$time}]</span>{$msg}</div>\n";
}
