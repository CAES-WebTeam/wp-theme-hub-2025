<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: fieldreport.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */


/*------------------------------------*\
	Load files
\*------------------------------------*/

require get_template_directory() . '/inc/theme-support.php';
require get_template_directory() . '/inc/analytics.php';
require get_template_directory() . '/inc/post-types.php';
require get_template_directory() . '/inc/blocks.php';
require get_template_directory() . '/inc/acf.php';
require get_template_directory() . '/inc/caes-tools.php';
require get_template_directory() . '/inc/publications-support.php';
require get_template_directory() . '/inc/user-support.php';
require get_template_directory() . '/inc/person-cpt-helpers.php';
require get_template_directory() . '/inc/person-cpt-migration.php';
require get_template_directory() . '/inc/news-support.php';
require get_template_directory() . '/block-variations/index.php';
require get_template_directory() . '/inc/custom-rewrites.php';
require get_template_directory() . '/inc/rss-support.php';
// require get_template_directory() . '/inc/multisite-script-protect.php';
require get_template_directory() . '/inc/symplectic-query-tool.php';
// require get_template_directory() . '/inc/symplectic-individual-user-import.php'; // Retired
// require get_template_directory() . '/inc/symplectic-scheduled-import.php'; // Retired -- replaced by person-cpt-data-sync.php
// require get_template_directory() . '/inc/symplectic-scheduled-import-cpt.php'; // Retired -- merged into person-cpt-data-sync.php
require get_template_directory() . '/inc/person-cpt-data-sync.php';

// Switch to simplified block template for person posts without Symplectic Elements data
add_filter( 'get_block_templates', function ( $templates, $query, $template_type ) {
	error_log( '[person-template] get_block_templates fired. type=' . $template_type . ' is_singular=' . ( is_singular( 'caes_hub_person' ) ? 'yes' : 'no' ) . ' queried_id=' . get_queried_object_id() . ' slugs=' . implode( ',', array_map( fn($t) => $t->slug, $templates ) ) );
	if ( $template_type !== 'wp_template' || ! is_singular( 'caes_hub_person' ) ) {
		return $templates;
	}
	$has_symplectic = get_post_meta( get_queried_object_id(), 'elements_user_id', true );
	error_log( '[person-template] post_id=' . get_queried_object_id() . ' has_symplectic=' . ( $has_symplectic ? 'yes' : 'no' ) );
	if ( ! $has_symplectic ) {
		foreach ( $templates as &$template ) {
			if ( $template->slug === 'single-caes_hub_person' ) {
				$file = get_template_directory() . '/templates/single-caes_hub_person-no-symplectic.html';
				if ( file_exists( $file ) ) {
					$template->content = file_get_contents( $file );
				}
				break;
			}
		}
	}
	return $templates;
}, 10, 3 );

// Publications PDF generation
require get_template_directory() . '/inc/publications-pdf/publications-pdf-mpdf.php';
require get_template_directory() . '/inc/publications-pdf/pdf-queue.php';
require get_template_directory() . '/inc/publications-pdf/pdf-cron.php';
require get_template_directory() . '/inc/publications-pdf/pdf-admin.php';

// Events
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/events/events-main.php';


// CAES Admin Tools to keep
require get_template_directory() . '/inc/topic-management.php';


// Temp include
require get_template_directory() . '/inc/detect-duplicates.php';
require get_template_directory() . '/inc/pub-sunset-tool.php';
require get_template_directory() . '/inc/pub-assets-import.php';

// Plugin overrides
require get_template_directory() . '/inc/plugin-overrides/relevanssi-search.php';
require get_template_directory() . '/inc/plugin-overrides/yoast-schema.php';

/**
 * Log wp_mail failures
 */
add_action('wp_mail_failed', function($wp_error) {
    $log_entry = date('[Y-m-d H:i:s]') . ' wp_mail FAILED: ' . $wp_error->get_error_message() . "\n";
    $log_entry .= 'Error Data: ' . print_r($wp_error->get_error_data(), true) . "\n";
    error_log($log_entry);
}, 10, 1);

/**
 * Log all wp_mail attempts (optional - can be verbose)
 */
add_filter('wp_mail', function($args) {
    $log_entry = date('[Y-m-d H:i:s]') . " wp_mail ATTEMPT\n";
    $log_entry .= 'To: ' . (is_array($args['to']) ? implode(', ', $args['to']) : $args['to']) . "\n";
    $log_entry .= 'Subject: ' . $args['subject'] . "\n";
    error_log($log_entry);
    return $args;
}, 10, 1);

/**
 * Capture PHPMailer errors directly
 */
add_action('phpmailer_init', function($phpmailer) {
    $phpmailer->SMTPDebug = 0; // Set to 2 or 3 for verbose SMTP logging (careful on production)
    $phpmailer->Debugoutput = function($str, $level) {
        error_log("PHPMailer [{$level}]: {$str}");
    };
}, 10, 1);

/////////////////////    START: Global SMTP   ////////////////////
/*
- This function will connect wp_mail to your authenticated SMTP server.
- Find more info at https://butlerblog.com/easy-smtp-email-wordpress-wp_mail/
- Values for constants are set in wp-config.php
 */
add_action( 'phpmailer_init', 'send_smtp_email' );
function send_smtp_email( $phpmailer ) {
	$phpmailer->isSMTP();
	$phpmailer->Host       = SMTP_HOST;
	$phpmailer->SMTPAuth   = SMTP_AUTH;
	$phpmailer->Port       = SMTP_PORT;
	$phpmailer->Username   = SMTP_USER;
	$phpmailer->Password   = SMTP_PASS;
	$phpmailer->SMTPSecure = SMTP_SECURE;
	$phpmailer->From       = SMTP_FROM;
	$phpmailer->FromName   = SMTP_NAME;
}
/////////////////////    END: Global SMTP   ////////////////////