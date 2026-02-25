<?php
/**
 * Symplectic Elements Individual User Import Tool
 *
 * Developer tool for testing single-user data imports from Symplectic Elements
 * into WordPress user profile ACF fields.
 *
 * Accepts a proprietary ID, fetches all relevant data from the Elements API,
 * finds the matching WordPress user by personnel_id meta, writes data to ACF
 * fields, and returns a verbose write report.
 */

// --- Admin menu registration ---

add_action('admin_menu', 'symplectic_import_add_admin_page');

function symplectic_import_add_admin_page() {
	add_submenu_page(
		'caes-tools',
		'Symplectic Elements User Import',
		'Symplectic User Import',
		'manage_options',
		'symplectic-user-import',
		'symplectic_import_render_page'
	);
}

// --- Scripts and styles ---

add_action('admin_enqueue_scripts', 'symplectic_import_enqueue_scripts');

function symplectic_import_enqueue_scripts($hook) {
	if ($hook !== 'caes-tools_page_symplectic-user-import') {
		return;
	}

	wp_enqueue_style('wp-admin');
	wp_enqueue_script('jquery');

	wp_add_inline_style('wp-admin', '
		.symplectic-import-wrapper { max-width: 900px; margin: 20px 0; }
		.symplectic-form-group { margin-bottom: 20px; }
		.symplectic-form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
		.symplectic-form-group input[type="text"] { width: 300px; }
		#symplectic-import-results { margin-top: 24px; }
		.import-section { margin-bottom: 20px; padding: 16px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
		.import-section h3 { margin-top: 0; padding-bottom: 8px; border-bottom: 1px solid #e5e5e5; }
		.import-row { display: flex; gap: 12px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-family: monospace; font-size: 13px; align-items: flex-start; }
		.import-row:last-child { border-bottom: none; }
		.import-status { flex-shrink: 0; font-weight: bold; min-width: 20px; }
		.import-status.ok      { color: #46b450; }
		.import-status.err     { color: #dc3232; }
		.import-status.skip    { color: #999; }
		.import-status.pending { color: #0073aa; }
		.import-field { flex-shrink: 0; min-width: 240px; color: #555; }
		.import-value { color: #23282d; word-break: break-word; }
		.import-error { color: #dc3232; }
		.import-summary { padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
		.import-summary.all-ok   { background: #ecf7ed; border-left: 4px solid #46b450; }
		.import-summary.partial  { background: #fff8e5; border-left: 4px solid #ffb900; }
		.import-summary.all-err  { background: #fbeaea; border-left: 4px solid #dc3232; }
		.import-summary.dry-run  { background: #e5f0fa; border-left: 4px solid #0073aa; }
		#symplectic-post-changes-btn { margin-top: 16px; }
		.repeater-row { padding: 4px 0; border-bottom: 1px solid #f0f0f0; font-size: 12px; font-family: monospace; }
		.repeater-row:last-child { border-bottom: none; }
		@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
		.dashicons.spin { animation: spin 1s linear infinite; display: inline-block; }
	');

	wp_add_inline_script('jquery', '
		jQuery(function($) {

			var nonce = ' . json_encode(wp_create_nonce('symplectic_import_nonce')) . ';

			// Shared request function — dryRun: true previews without writing.
			function runRequest(pid, dryRun) {
				var $importBtn  = $("#symplectic-import-btn");
				var $dryRunBtn  = $("#symplectic-dry-run-btn");
				var $results    = $("#symplectic-import-results");
				var label       = dryRun ? "Running dry run\u2026" : "Importing\u2026";

				$importBtn.prop("disabled", true);
				$dryRunBtn.prop("disabled", true);
				$results.html("<p><span class=\"dashicons dashicons-update spin\"></span> " + label + "</p>");

				$.ajax({
					url: ajaxurl,
					method: "POST",
					timeout: 300000,
					data: {
						action: "symplectic_import_user",
						nonce: nonce,
						personnel_id: pid,
						dry_run: dryRun ? 1 : 0
					},
					success: function(response) {
						$importBtn.prop("disabled", false);
						$dryRunBtn.prop("disabled", false);
						if (response.success) {
							var html = renderReport(response.data);
							// After a dry run, append the "Post Changes" button.
							if (dryRun) {
								html += "<p id=\"symplectic-post-changes-btn\">"
									+ "<button type=\"button\" class=\"button button-primary\" id=\"symplectic-confirm-btn\">"
									+ "Post Changes"
									+ "</button>"
									+ "&ensp;<span style=\"color:#555;font-style:italic\">No data has been written yet. Click to commit the changes shown above.</span>"
									+ "</p>";
							}
							$results.html(html);
							// Bind the "Post Changes" button if present.
							if (dryRun) {
								$("#symplectic-confirm-btn").on("click", function() {
									runRequest(pid, false);
								});
							}
						} else {
							var msg = (response.data && response.data.error_message) ? response.data.error_message : JSON.stringify(response.data);
							$results.html("<div class=\"notice notice-error\"><p><strong>" + (dryRun ? "Dry run" : "Import") + " failed:</strong> " + esc(msg) + "</p></div>");
						}
					},
					error: function(xhr, status, error) {
						$importBtn.prop("disabled", false);
						$dryRunBtn.prop("disabled", false);
						$results.html("<div class=\"notice notice-error\"><p><strong>AJAX error (" + esc(status) + "):</strong> " + esc(error) + "</p><pre>" + esc(xhr.responseText.substring(0, 2000)) + "</pre></div>");
					}
				});
			}

			// "Run Import" — write immediately.
			$("#symplectic-import-form").on("submit", function(e) {
				e.preventDefault();
				var pid = $("#proprietary-id").val().trim();
				if (!pid) { alert("Please enter a Personnel ID."); return; }
				runRequest(pid, false);
			});

			// "Dry Run" — fetch and preview, no writes.
			$("#symplectic-dry-run-btn").on("click", function() {
				var pid = $("#proprietary-id").val().trim();
				if (!pid) { alert("Please enter a Personnel ID."); return; }
				runRequest(pid, true);
			});

			function esc(s) {
				if (s === null || s === undefined) return "(null)";
				return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
			}

			function trunc(s, n) {
				if (!s) return "";
				var str = String(s);
				return str.length > n ? esc(str.substring(0, n)) + "\u2026" : esc(str);
			}

			function statusAttrs(result) {
				// Returns { sc: css-class, si: symbol } for a write result value.
				if (result === true)         return { sc: "ok",      si: "\u2713" };  // ✓
				if (result === "verified")   return { sc: "ok",      si: "\u2713" };  // ✓ (written, false return)
				if (result === "skipped")    return { sc: "skip",    si: "\u2014" };  // —
				if (result === "dry_run")    return { sc: "pending", si: "\u25cb" };  // ○
				return                              { sc: "err",     si: "\u2717" };  // ✗
			}

			function renderReport(d) {
				var html    = "";
				var s       = d.summary;
				var isDry   = d.dry_run === true;
				var cls;

				if (isDry) {
					cls = "dry-run";
				} else {
					cls = s.failed > 0 ? "all-err" : (s.skipped > 0 ? "partial" : "all-ok");
				}

				// Summary bar
				html += "<div class=\"import-summary " + cls + "\">";
				if (isDry) html += "<strong>DRY RUN \u2014 no data written.</strong>&ensp;";
				html += "<strong>" + esc(d.wp_user.display_name) + "</strong> ";
				html += "(WP user ID&nbsp;" + esc(d.wp_user.id) + ", login:&nbsp;" + esc(d.wp_user.login) + ")&ensp;&mdash;&ensp;";
				if (isDry) {
					html += s.pending + "&nbsp;field" + (s.pending !== 1 ? "s" : "") + " would be written, " + s.skipped + " skipped.";
				} else {
					html += s.ok + "&nbsp;write" + (s.ok !== 1 ? "s" : "") + " succeeded, " + s.failed + " failed, " + s.skipped + " skipped.";
				}
				html += "</div>";

				// Scalar field writes
				if (d.writes && d.writes.length) {
					if (d.fetch_errors && d.fetch_errors.length) {
					html += "<div class=\"import-section\" style=\"border-color:#dc3232\"><h3 style=\"color:#dc3232\">Fetch Errors (" + d.fetch_errors.length + ")</h3>";
					d.fetch_errors.forEach(function(msg) {
						html += "<div class=\"import-row\"><span class=\"import-status err\">\u2717</span><span class=\"import-value import-error\">" + esc(msg) + "</span></div>";
					});
					html += "</div>";
				}

				html += "<div class=\"import-section\"><h3>Scalar Field Writes</h3>";
					d.writes.forEach(function(w) {
						var a = statusAttrs(w.result);
						html += "<div class=\"import-row\">";
						html += "<span class=\"import-status " + a.sc + "\">" + a.si + "</span>";
						html += "<span class=\"import-field\">" + esc(w.field) + "</span>";
						if (w.error) {
							html += "<span class=\"import-error\">" + esc(w.error) + "</span>";
						} else if (w.result === "skipped") {
							html += "<span class=\"import-value\" style=\"color:#999\">No value available \u2014 field not written.</span>";
						} else {
							html += "<span class=\"import-value\">" + trunc(w.display_value, 140) + "</span>";
						if (w.note) html += "<span style=\"color:#999;font-style:italic;margin-left:8px\">" + esc(w.note) + "</span>";
						}
						html += "</div>";
					});
					html += "</div>";
				}

				// Taxonomy operations
				if (d.taxonomy_ops && d.taxonomy_ops.length) {
					html += "<div class=\"import-section\"><h3>Areas of Expertise \u2014 Taxonomy Operations</h3>";
					d.taxonomy_ops.forEach(function(t) {
						var sc, si, badge;
						if (t.error) {
							sc = "err"; si = "\u2717";
						} else if (t.action === "would_create") {
							sc = "pending"; si = "\u25cb"; // ○ — would create
						} else {
							sc = "ok"; si = "\u2713";
						}
						badge = (t.action === "created" || t.action === "would_create") ? "[new]&nbsp;" : "[exists]&nbsp;";
						html += "<div class=\"import-row\">";
						html += "<span class=\"import-status " + sc + "\">" + si + "</span>";
						html += "<span class=\"import-field\">" + badge + "</span>";
						html += "<span class=\"import-value\">" + esc(t.term);
						if (t.term_id) html += " &nbsp;<span style=\"color:#999\">(term_id:&nbsp;" + esc(t.term_id) + ")</span>";
						html += "</span>";
						if (t.error) html += "&nbsp;<span class=\"import-error\">" + esc(t.error) + "</span>";
						html += "</div>";
					});
					html += "</div>";
				}

				// Repeater sections
				var repeaterLabels = { scholarly_works: "Scholarly Works", distinctions: "Distinctions", courses_taught: "Courses Taught" };
				["scholarly_works", "distinctions", "courses_taught"].forEach(function(key) {
					if (!d.repeaters || !d.repeaters[key]) return;
					var r   = d.repeaters[key];
					var a   = statusAttrs(r.write_result);
					var lbl = repeaterLabels[key] || key;
					var writtenLabel = isDry ? "rows pending" : "rows written";
					html += "<div class=\"import-section\">";
					html += "<h3>" + lbl + " &mdash; <span class=\"import-status " + a.sc + "\">" + a.si + "</span> " + r.row_count + " " + writtenLabel + "</h3>";
					if (r.error) {
						html += "<p class=\"import-error\">" + esc(r.error) + "</p>";
				} else if (r.write_result === "skipped") {
					html += "<p style=\"color:#999\">No rows available \u2014 field not written.</p>";
				} else {
					if (r.note) html += "<p style=\"color:#999;font-style:italic\">" + esc(r.note) + "</p>";
					r.rows.forEach(function(row, i) {
							html += "<div class=\"repeater-row\"><strong>Row&nbsp;" + (i+1) + ":</strong>&nbsp;";
							var parts = [];
							for (var k in row) {
								if (row[k] !== "" && row[k] !== null) {
									parts.push("<code>" + esc(k) + "</code>:&nbsp;" + trunc(String(row[k]), 80));
								}
							}
							html += parts.join("&ensp;|&ensp;");
							html += "</div>";
						});
					}
					html += "</div>";
				});

				return html;
			}
		});
	');
}

// --- Admin page render ---

function symplectic_import_render_page() {
	$credentials_ok = defined('SYMPLECTIC_API_USERNAME') && defined('SYMPLECTIC_API_PASSWORD') && defined('CF_810_API_ENDPOINT_KEY');
	?>
	<div class="wrap">
		<h1>Symplectic Elements User Import Tool</h1>
		<p>Fetches data for a single user from the Symplectic Elements API and writes it to their WordPress user profile ACF fields. Developer use only.</p>

		<?php if (!$credentials_ok): ?>
			<div class="notice notice-error">
				<p><strong>Configuration Required:</strong> <code>SYMPLECTIC_API_USERNAME</code>, <code>SYMPLECTIC_API_PASSWORD</code>, and/or <code>CF_810_API_ENDPOINT_KEY</code> are not defined in wp-config.php.</p>
			</div>
		<?php endif; ?>

		<div class="symplectic-import-wrapper">
			<form id="symplectic-import-form">
				<div class="symplectic-form-group">
					<label for="proprietary-id">Personnel ID</label>
					<input type="text" id="proprietary-id" placeholder="e.g. 3885">
					<p class="description">The CAES personnel ID stored on the WordPress user profile. Used to look up the UGA ID (810 number) from the internal personnel API, which is then used to query the Symplectic Elements API.</p>
				</div>
				<input type="submit" id="symplectic-import-btn" class="button button-primary" value="Run Import"
					<?php echo $credentials_ok ? '' : 'disabled'; ?>>
			<button type="button" id="symplectic-dry-run-btn" class="button button-secondary"
				<?php echo $credentials_ok ? '' : 'disabled'; ?>>Dry Run</button>
			<p class="description" style="margin-top:8px">
				<strong>Dry Run</strong> fetches the data and previews what would be written without touching the database.
				<strong>Run Import</strong> writes immediately.
			</p>
			</form>

			<div id="symplectic-import-results"></div>
		</div>
	</div>
	<?php
}

// --- AJAX handler ---

add_action('wp_ajax_symplectic_import_user', 'symplectic_import_user_handler');

function symplectic_import_user_handler() {

	if (!wp_verify_nonce($_POST['nonce'], 'symplectic_import_nonce')) {
		wp_send_json_error(array('error_message' => 'Security check failed.'));
		return;
	}

	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('error_message' => 'Insufficient permissions.'));
		return;
	}

	if (!defined('SYMPLECTIC_API_USERNAME') || !defined('SYMPLECTIC_API_PASSWORD') || !defined('CF_810_API_ENDPOINT_KEY')) {
		wp_send_json_error(array('error_message' => 'SYMPLECTIC_API_USERNAME, SYMPLECTIC_API_PASSWORD, or CF_810_API_ENDPOINT_KEY not configured in wp-config.php.'));
		return;
	}

	$personnel_id = sanitize_text_field($_POST['personnel_id']);
	if (empty($personnel_id)) {
		wp_send_json_error(array('error_message' => 'Personnel ID is required.'));
		return;
	}

	$dry_run = !empty($_POST['dry_run']) && $_POST['dry_run'] === '1';

	// -------------------------------------------------------------------------
	// Step 1: Find the WordPress user by personnel_id
	// -------------------------------------------------------------------------

	$wp_users = get_users(array(
		'meta_key'   => 'personnel_id',
		'meta_value' => $personnel_id,
		'number'     => 1,
	));

	if (empty($wp_users)) {
		wp_send_json_error(array(
			'error_message' =>
				'No WordPress user found with personnel_id = ' . $personnel_id . '. ' .
				'The user must have been synced from the CAES personnel database with a matching personnel_id before importing Elements data.',
		));
		return;
	}

	$wp_user    = $wp_users[0];
	$wp_user_id = $wp_user->ID;
	$user_acf   = 'user_' . $wp_user_id;

	// -------------------------------------------------------------------------
	// Step 2: Fetch UGA ID (810 number) from CAES internal personnel API
	// -------------------------------------------------------------------------

	$caes_api_url = 'https://secure.caes.uga.edu/rest/personnel/getUGAids'
		. '?PersonnelID=' . urlencode($personnel_id)
		. '&APIkey=' . urlencode(CF_810_API_ENDPOINT_KEY);

	$caes_response = wp_remote_get($caes_api_url, array(
		'timeout'   => 15,
		'sslverify' => true,
	));

	if (is_wp_error($caes_response)) {
		wp_send_json_error(array('error_message' => 'CAES personnel API request failed: ' . $caes_response->get_error_message()));
		return;
	}

	$caes_response_code = wp_remote_retrieve_response_code($caes_response);
	$caes_response_body = wp_remote_retrieve_body($caes_response);

	if ($caes_response_code !== 200) {
		wp_send_json_error(array(
			'error_message' => 'CAES personnel API returned HTTP ' . $caes_response_code . ': ' . substr($caes_response_body, 0, 500),
		));
		return;
	}

	$caes_data = json_decode($caes_response_body, true);

	if (empty($caes_data) || !isset($caes_data[0]['UGA_ID']) || empty($caes_data[0]['UGA_ID'])) {
		wp_send_json_error(array(
			'error_message' => 'No UGA ID (810 number) found for personnel_id ' . $personnel_id . ' in the CAES personnel API.',
		));
		return;
	}

	$uga_id = $caes_data[0]['UGA_ID'];

	// -------------------------------------------------------------------------
	// Step 3: Fetch user object from the Elements API
	// -------------------------------------------------------------------------

	@set_time_limit(300);

	$api_args = array(
		'headers'   => array(
			'Authorization' => 'Basic ' . base64_encode(SYMPLECTIC_API_USERNAME . ':' . SYMPLECTIC_API_PASSWORD),
		),
		'timeout'   => 60,
		'sslverify' => true,
	);

	$user_api_url = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users'
		. '?query=proprietary-id%3D%22' . urlencode($uga_id) . '%22&detail=full';

	$user_response = wp_remote_get($user_api_url, $api_args);

	if (is_wp_error($user_response)) {
		wp_send_json_error(array('error_message' => 'Elements user API request failed: ' . $user_response->get_error_message()));
		return;
	}

	$user_response_code = wp_remote_retrieve_response_code($user_response);
	$user_response_body = wp_remote_retrieve_body($user_response);

	if ($user_response_code !== 200) {
		wp_send_json_error(array(
			'error_message' => 'Elements user API returned HTTP ' . $user_response_code . ': ' . substr($user_response_body, 0, 500),
		));
		return;
	}

	libxml_use_internal_errors(true);
	$user_xml = simplexml_load_string($user_response_body);

	if ($user_xml === false) {
		$xml_errors = array_map(function($e) { return trim($e->message); }, libxml_get_errors());
		wp_send_json_error(array('error_message' => 'Failed to parse Elements user XML: ' . implode('; ', $xml_errors)));
		return;
	}

	$user_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
	$user_objects = $user_xml->xpath('//api:object[@category="user"]');

	if (empty($user_objects)) {
		wp_send_json_error(array(
			'error_message' => 'No user object found in Elements API response for UGA ID ' . $uga_id . ' (personnel_id ' . $personnel_id . ').',
		));
		return;
	}

	$object    = $user_objects[0];
	$user_info = array();

	foreach ($object->attributes() as $k => $v) {
		$user_info[$k] = (string)$v;
	}

	$object->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	// Keywords (Areas of Expertise)
	$keyword_nodes = $object->xpath('./api:all-labels/api:keywords/api:keyword');
	if (!empty($keyword_nodes)) {
		$user_info['keywords'] = array();
		foreach ($keyword_nodes as $kw) {
			$user_info['keywords'][] = (string)$kw;
		}
	}

	// Overview (plain-text version)
	$overview_nodes = $object->xpath('.//api:record/api:native/api:field[@name="overview"]/api:text');
	if (!empty($overview_nodes)) {
		$user_info['overview'] = (string)$overview_nodes[0];
	}

	$elements_user_id = isset($user_info['id']) ? (int)$user_info['id'] : null;

	// -------------------------------------------------------------------------
	// Step 4: Fetch relationships (publications, distinctions, courses)
	// -------------------------------------------------------------------------

	$publications        = array();
	$activities          = array();
	$teaching_activities = array();
	$fetch_errors        = array();

	if ($elements_user_id) {
		$rel_url       = 'https://uga.elements.symplectic.org:8091/secure-api/v6.13/users/' . $elements_user_id . '/relationships?per-page=100';
		$page_count    = 0;
		$max_pages     = 10;
		$has_next_page = false;

		do {
			$has_next_page = false;
			$page_count++;
			if ($page_count > $max_pages) break;

			$rel_response = wp_remote_get($rel_url, $api_args);
			if (is_wp_error($rel_response)) {
				$fetch_errors[] = 'Relationships page ' . $page_count . ': ' . $rel_response->get_error_message();
				break;
			}
			if (wp_remote_retrieve_response_code($rel_response) !== 200) {
				$fetch_errors[] = 'Relationships page ' . $page_count . ': HTTP ' . wp_remote_retrieve_response_code($rel_response) . ' — ' . substr(wp_remote_retrieve_body($rel_response), 0, 200);
				break;
			}

			$rel_xml = simplexml_load_string(wp_remote_retrieve_body($rel_response));
			if ($rel_xml === false) {
				$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
				libxml_clear_errors();
				$fetch_errors[] = 'Relationships page ' . $page_count . ' XML parse: ' . implode('; ', $xml_errs);
				break;
			}

			$rel_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
			$page_objects = $rel_xml->xpath('//api:object');

			if (!empty($page_objects)) {
				foreach ($page_objects as $rel_obj) {
					$obj_data = array();
					foreach ($rel_obj->attributes() as $k => $v) {
						$obj_data[$k] = (string)$v;
					}

					$category = isset($obj_data['category']) ? $obj_data['category'] : null;

					if ($category === 'publication' && isset($obj_data['href'])) {

						$pub_resp = wp_remote_get($obj_data['href'], $api_args);
						if (is_wp_error($pub_resp)) {
							$fetch_errors[] = 'Publication ' . $obj_data['id'] . ': ' . $pub_resp->get_error_message();
						} elseif (wp_remote_retrieve_response_code($pub_resp) !== 200) {
							$fetch_errors[] = 'Publication ' . $obj_data['id'] . ': HTTP ' . wp_remote_retrieve_response_code($pub_resp);
						} else {
							$pub_xml = simplexml_load_string(wp_remote_retrieve_body($pub_resp));
							if ($pub_xml === false) {
								$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
								libxml_clear_errors();
								$fetch_errors[] = 'Publication ' . $obj_data['id'] . ' XML parse: ' . implode('; ', $xml_errs);
							} else {
								$obj_data = array_merge($obj_data, symplectic_import_extract_publication_fields($pub_xml));
							}
						}
						$publications[] = $obj_data;

					} elseif ($category === 'activity'
						&& isset($obj_data['href'])
						&& isset($obj_data['type'])
						&& $obj_data['type'] === 'distinction'
					) {
						$act_resp = wp_remote_get($obj_data['href'], $api_args);
						if (is_wp_error($act_resp)) {
							$fetch_errors[] = 'Activity ' . $obj_data['id'] . ': ' . $act_resp->get_error_message();
						} elseif (wp_remote_retrieve_response_code($act_resp) !== 200) {
							$fetch_errors[] = 'Activity ' . $obj_data['id'] . ': HTTP ' . wp_remote_retrieve_response_code($act_resp);
						} else {
							$act_xml = simplexml_load_string(wp_remote_retrieve_body($act_resp));
							if ($act_xml === false) {
								$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
								libxml_clear_errors();
								$fetch_errors[] = 'Activity ' . $obj_data['id'] . ' XML parse: ' . implode('; ', $xml_errs);
							} else {
								$obj_data = array_merge($obj_data, symplectic_import_extract_activity_fields($act_xml));
							}
						}
						$activities[] = $obj_data;

					} elseif ($category === 'teaching-activity'
						&& isset($obj_data['href'])
						&& isset($obj_data['type'])
						&& $obj_data['type'] === 'course-taught'
					) {
						$ta_resp = wp_remote_get($obj_data['href'], $api_args);
						if (is_wp_error($ta_resp)) {
							$fetch_errors[] = 'Teaching activity ' . $obj_data['id'] . ': ' . $ta_resp->get_error_message();
						} elseif (wp_remote_retrieve_response_code($ta_resp) !== 200) {
							$fetch_errors[] = 'Teaching activity ' . $obj_data['id'] . ': HTTP ' . wp_remote_retrieve_response_code($ta_resp);
						} else {
							$ta_xml = simplexml_load_string(wp_remote_retrieve_body($ta_resp));
							if ($ta_xml === false) {
								$xml_errs = array_map(fn($e) => trim($e->message), libxml_get_errors());
								libxml_clear_errors();
								$fetch_errors[] = 'Teaching activity ' . $obj_data['id'] . ' XML parse: ' . implode('; ', $xml_errs);
							} else {
								$obj_data = array_merge($obj_data, symplectic_import_extract_teaching_activity_fields($ta_xml));
							}
						}
						if (isset($obj_data['term']) && symplectic_import_is_teaching_term_recent($obj_data['term'])) {
							$teaching_activities[] = $obj_data;
						}
					}
				}
				unset($page_objects);
			}

			// Pagination
			$pag_nodes = $rel_xml->xpath('//api:pagination');
			if (!empty($pag_nodes)) {
				$pag = $pag_nodes[0];
				$pag->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
				$next = $pag->xpath('./api:page[@position="next"]');
				if (!empty($next) && (string)$next[0]['href']) {
					$has_next_page = true;
					$rel_url       = (string)$next[0]['href'];
				}
			}
			unset($rel_xml);

		} while ($has_next_page);
	}

	// Sort publications by citation count descending; keep top 5
	usort($publications, function($a, $b) {
		return (isset($b['citation-count']) ? $b['citation-count'] : -1)
			 - (isset($a['citation-count']) ? $a['citation-count'] : -1);
	});
	$publications = array_slice($publications, 0, 5);

	// -------------------------------------------------------------------------
	// Step 5: Write to ACF fields
	// -------------------------------------------------------------------------

	$writes       = array();
	$taxonomy_ops = array();
	$repeaters    = array();

	// Helper: attempt a scalar field write and record the result.
	// In dry-run mode, skips the actual update_field() call and records 'dry_run'.
	$do_write = function($label, $field_key, $value, $display) use (&$writes, $user_acf, $dry_run) {
		if ($value === null || $value === '' || $value === array()) {
			$writes[] = array('field' => $label, 'field_key' => $field_key, 'result' => 'skipped', 'display_value' => '', 'error' => null);
			return 'skipped';
		}
		if ($dry_run) {
			$writes[] = array('field' => $label, 'field_key' => $field_key, 'result' => 'dry_run', 'display_value' => $display, 'error' => null);
			return 'dry_run';
		}
		$result = update_field($field_key, $value, $user_acf);
		if ($result === false) {
			// update_field() returns false both on genuine failure and when WordPress's
			// update_metadata() considers the value unchanged (no-op), or on first-insert
			// in some ACF versions. Read back to distinguish the two cases.
			$stored = get_field($field_key, $user_acf);
			if ($stored !== false && $stored !== null && $stored !== '') {
				$writes[] = array(
					'field'         => $label,
					'field_key'     => $field_key,
					'result'        => 'verified',
					'display_value' => $display,
					'error'         => null,
					'note'          => 'update_field() returned false; value confirmed present via get_field() — ACF/WP no-op or first-insert return ambiguity.',
				);
				return 'verified';
			}
			$writes[] = array(
				'field'         => $label,
				'field_key'     => $field_key,
				'result'        => false,
				'display_value' => $display,
				'error'         => 'update_field() returned false; subsequent get_field() also found no value — likely a genuine write failure.',
				'note'          => null,
			);
			return false;
		}
		$writes[] = array(
			'field'         => $label,
			'field_key'     => $field_key,
			'result'        => $result,
			'display_value' => $display,
			'error'         => null,
			'note'          => null,
		);
		return $result;
	};

	// elements_user_id
	$do_write('elements_user_id', 'field_elements_user_id', $elements_user_id, (string)$elements_user_id);

	// elements_overview
	$overview = isset($user_info['overview']) ? $user_info['overview'] : null;
	$do_write('elements_overview', 'field_elements_overview', $overview, $overview ? substr($overview, 0, 120) . '…' : '');

	// elements_areas_of_expertise — resolve/create taxonomy terms, then write IDs
	if (!empty($user_info['keywords'])) {
		$term_ids = array();
		foreach ($user_info['keywords'] as $keyword) {
			$existing = get_term_by('name', $keyword, 'areas_of_expertise');
			if ($existing && !is_wp_error($existing)) {
				$term_ids[]    = $existing->term_id;
				$taxonomy_ops[] = array(
					'term'    => $keyword,
					'action'  => 'existing',
					'term_id' => $existing->term_id,
					'error'   => null,
				);
			} elseif ($dry_run) {
				// In dry-run mode, note that this term would be created without actually creating it.
				$taxonomy_ops[] = array(
					'term'    => $keyword,
					'action'  => 'would_create',
					'term_id' => null,
					'error'   => null,
				);
			} else {
				$inserted = wp_insert_term($keyword, 'areas_of_expertise');
				if (is_wp_error($inserted)) {
					$taxonomy_ops[] = array(
						'term'    => $keyword,
						'action'  => 'failed',
						'term_id' => null,
						'error'   => $inserted->get_error_message(),
					);
				} else {
					$term_ids[]    = $inserted['term_id'];
					$taxonomy_ops[] = array(
						'term'    => $keyword,
						'action'  => 'created',
						'term_id' => $inserted['term_id'],
						'error'   => null,
					);
				}
			}
		}
		$do_write(
			'elements_areas_of_expertise',
			'field_elements_areas_of_expertise',
			$term_ids ?: null,
			implode(', ', $user_info['keywords'])
		);
	} else {
		$writes[] = array('field' => 'elements_areas_of_expertise', 'field_key' => 'field_elements_areas_of_expertise', 'result' => 'skipped', 'display_value' => '', 'error' => null);
	}

	// elements_scholarly_works (repeater)
	$pub_rows = array();
	foreach ($publications as $pub) {
		$pub_rows[] = array(
			'pub_title'          => isset($pub['title'])            ? $pub['title']            : '',
			'pub_type'           => isset($pub['type'])             ? $pub['type']             : '',
			'pub_journal'        => isset($pub['journal'])          ? $pub['journal']          : '',
			'pub_doi'            => isset($pub['doi'])              ? $pub['doi']              : '',
			'pub_year'           => isset($pub['publication_date']) ? $pub['publication_date'] : '',
			'pub_citation_count' => isset($pub['citation-count'])  ? (int)$pub['citation-count'] : '',
		);
	}
	$pub_note = null;
	if (!empty($pub_rows)) {
		$pub_result = $dry_run ? 'dry_run' : update_field('field_elements_scholarly_works', $pub_rows, $user_acf);
		if ($pub_result === false) {
			$stored_pubs = get_field('field_elements_scholarly_works', $user_acf);
			if (!empty($stored_pubs)) {
				$pub_result = 'verified';
				$pub_note   = 'update_field() returned false; ' . count($stored_pubs) . ' row(s) confirmed present via get_field() — ACF/WP no-op or first-insert return ambiguity.';
			}
		}
	} else {
		$pub_result = 'skipped';
	}
	$repeaters['scholarly_works'] = array(
		'row_count'    => count($pub_rows),
		'rows'         => $pub_rows,
		'write_result' => $pub_result,
		'error'        => ($pub_result === false) ? 'update_field() returned false; subsequent get_field() found no rows — likely a genuine write failure.' : null,
		'note'         => $pub_note,
	);

	// elements_distinctions (repeater)
	$dist_rows = array();
	foreach ($activities as $act) {
		$dist_rows[] = array(
			'distinction_title'       => isset($act['title'])       ? $act['title']       : '',
			'distinction_date'        => isset($act['date'])        ? $act['date']        : '',
			'distinction_description' => isset($act['description']) ? $act['description'] : '',
		);
	}
	$dist_note = null;
	if (!empty($dist_rows)) {
		$dist_result = $dry_run ? 'dry_run' : update_field('field_elements_distinctions', $dist_rows, $user_acf);
		if ($dist_result === false) {
			$stored_dist = get_field('field_elements_distinctions', $user_acf);
			if (!empty($stored_dist)) {
				$dist_result = 'verified';
				$dist_note   = 'update_field() returned false; ' . count($stored_dist) . ' row(s) confirmed present via get_field() — ACF/WP no-op or first-insert return ambiguity.';
			}
		}
	} else {
		$dist_result = 'skipped';
	}
	$repeaters['distinctions'] = array(
		'row_count'    => count($dist_rows),
		'rows'         => $dist_rows,
		'write_result' => $dist_result,
		'error'        => ($dist_result === false) ? 'update_field() returned false; subsequent get_field() found no rows — likely a genuine write failure.' : null,
		'note'         => $dist_note,
	);

	// elements_courses_taught (repeater)
	$course_rows = array();
	foreach ($teaching_activities as $ta) {
		$course_rows[] = array(
			'course_title' => isset($ta['title'])       ? $ta['title']       : '',
			'course_code'  => isset($ta['course_code']) ? $ta['course_code'] : '',
			'course_term'  => isset($ta['term'])        ? $ta['term']        : '',
		);
	}
	$course_note = null;
	if (!empty($course_rows)) {
		$course_result = $dry_run ? 'dry_run' : update_field('field_elements_courses_taught', $course_rows, $user_acf);
		if ($course_result === false) {
			$stored_courses = get_field('field_elements_courses_taught', $user_acf);
			if (!empty($stored_courses)) {
				$course_result = 'verified';
				$course_note   = 'update_field() returned false; ' . count($stored_courses) . ' row(s) confirmed present via get_field() — ACF/WP no-op or first-insert return ambiguity.';
			}
		}
	} else {
		$course_result = 'skipped';
	}
	$repeaters['courses_taught'] = array(
		'row_count'    => count($course_rows),
		'rows'         => $course_rows,
		'write_result' => $course_result,
		'error'        => ($course_result === false) ? 'update_field() returned false; subsequent get_field() found no rows — likely a genuine write failure.' : null,
		'note'         => $course_note,
	);

	// -------------------------------------------------------------------------
	// Step 6: Build summary and return
	// -------------------------------------------------------------------------

	$ok      = count(array_filter($writes, fn($w) => $w['result'] === true || $w['result'] === 'verified'));
	$failed  = count(array_filter($writes, fn($w) => $w['result'] === false));
	$skipped = count(array_filter($writes, fn($w) => $w['result'] === 'skipped'));
	$pending = count(array_filter($writes, fn($w) => $w['result'] === 'dry_run'));

	foreach ($repeaters as $r) {
		if ($r['write_result'] === true || $r['write_result'] === 'verified') $ok++;
		elseif ($r['write_result'] === false)                                  $failed++;
		elseif ($r['write_result'] === 'dry_run')                              $pending++;
		else                                                                    $skipped++;
	}

	wp_send_json_success(array(
		'dry_run' => $dry_run,
		'wp_user' => array(
			'id'           => $wp_user_id,
			'login'        => $wp_user->user_login,
			'display_name' => $wp_user->display_name,
		),
		'fetch_errors' => $fetch_errors,
		'writes'       => $writes,
		'taxonomy_ops' => $taxonomy_ops,
		'repeaters'    => $repeaters,
		'summary'      => array(
			'total'   => count($writes) + count($repeaters),
			'ok'      => $ok,
			'failed'  => $failed,
			'skipped' => $skipped,
			'pending' => $pending,
		),
	));
}

// -------------------------------------------------------------------------
// Extraction helpers (prefixed to avoid conflicts with the query tool)
// -------------------------------------------------------------------------

function symplectic_import_extract_publication_fields($pub_xml) {
	$data = array();
	$pub_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$records = $pub_xml->xpath('//api:record[@format="native" or @format="preferred"]');
	if (empty($records)) return $data;

	// Citation count — prefer WoS, then Dimensions, then others
	$priority_order = array('wos', 'dimensions', 'dimensions-for-universities', 'scopus', 'epmc');
	$citation       = null;
	$best           = PHP_INT_MAX;

	foreach ($records as $rec) {
		$rec->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$cn = $rec->xpath('./api:citation-count');
		if (!empty($cn)) {
			$p = array_search((string)$rec['source-name'], $priority_order);
			$p = ($p === false) ? PHP_INT_MAX : $p;
			if ($citation === null || $p < $best) {
				$citation = (int)(string)$cn[0];
				$best     = $p;
			}
		}
	}
	if ($citation !== null) $data['citation-count'] = $citation;

	$record = $records[0];
	$record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	foreach ($record->xpath('.//api:field') as $field) {
		$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$name = (string)$field['name'];
		$type = (string)$field['type'];
		$val  = null;

		if ($type === 'text') {
			$t = $field->xpath('./api:text');
			if (!empty($t)) $val = (string)$t[0];
		} elseif ($type === 'date') {
			$d = $field->xpath('./api:date');
			if (!empty($d)) {
				$y = (string)$d[0]->year; $m = (string)$d[0]->month; $day = (string)$d[0]->day;
				if ($y) { $val = $y; if ($m) { $val = $m . '/' . $val; if ($day) $val = $day . '/' . $val; } }
			}
		}

		if ($val) {
			switch ($name) {
				case 'title':            $data['title']            = $val; break;
				case 'journal':          $data['journal']          = $val; break;
				case 'publication-date': $data['publication_date'] = $val; break;
				case 'doi':              $data['doi']              = $val; break;
			}
		}
	}

	return $data;
}

function symplectic_import_extract_activity_fields($activity_xml) {
	$data = array();
	$activity_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$records = $activity_xml->xpath('//api:record[@format="native" or @format="preferred"]');
	if (empty($records)) return $data;

	$record = $records[0];
	$record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	foreach ($record->xpath('.//api:field') as $field) {
		$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$name = (string)$field['name'];
		$type = (string)$field['type'];
		$val  = null;

		if ($type === 'text') {
			$t = $field->xpath('./api:text');
			if (!empty($t)) $val = (string)$t[0];
		} elseif ($type === 'date') {
			$d = $field->xpath('./api:date');
			if (!empty($d)) {
				$y = (string)$d[0]->year; $m = (string)$d[0]->month; $day = (string)$d[0]->day;
				if ($y) { $val = $y; if ($m) { $val = $m . '/' . $val; if ($day) $val = $day . '/' . $val; } }
			}
		}

		if ($val) {
			switch ($name) {
				case 'title':        $data['title']       = $val; break;
				case 'name':         if (!isset($data['title'])) $data['title'] = $val; break;
				case 'start-date':
				case 'awarded-date': $data['date']        = $val; break;
				case 'description':  $data['description'] = $val; break;
			}
		}
	}

	return $data;
}

function symplectic_import_extract_teaching_activity_fields($teaching_xml) {
	$data = array();
	$teaching_xml->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	$records = $teaching_xml->xpath('//api:record[@format="native" or @format="preferred"]');
	if (empty($records)) return $data;

	$record = $records[0];
	$record->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');

	foreach ($record->xpath('.//api:field') as $field) {
		$field->registerXPathNamespace('api', 'http://www.symplectic.co.uk/publications/api');
		$name = (string)$field['name'];
		$type = (string)$field['type'];
		$val  = null;

		if ($type === 'text') {
			$t = $field->xpath('./api:text');
			if (!empty($t)) $val = (string)$t[0];
		}

		if ($val) {
			switch ($name) {
				case 'title':       $data['title']       = $val; break;
				case 'c-term-year': $data['term']        = $val; break;
				case 'course-code': $data['course_code'] = $val; break;
			}
		}
	}

	return $data;
}

function symplectic_import_is_teaching_term_recent($term_string) {
	if (!preg_match('/^(Spring|Summer|Fall)\s+(\d{4})$/i', trim($term_string), $matches)) {
		return false;
	}

	$season = ucfirst(strtolower($matches[1]));
	$year   = (int)$matches[2];

	switch ($season) {
		case 'Spring': $end = new DateTime("$year-05-15"); break;
		case 'Summer': $end = new DateTime("$year-08-01"); break;
		case 'Fall':   $ny  = $year + 1; $end = new DateTime("$ny-01-01"); break;
		default: return false;
	}

	$cutoff = new DateTime();
	$cutoff->modify('-1 year');
	return $end >= $cutoff;
}
