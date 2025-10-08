<?php

/**
 * RSS2 Feed Template for displaying RSS2 Posts feed.
 * Custom version with ACF authors support
 *
 * @package WordPress
 */

header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . '>';

/**
 * Fires between the xml and rss tags in a feed.
 *
 * @since 4.0.0
 *
 * @param string $context Type of feed. Possible values include 'rss2', 'rss2-comments',
 *                        'rdf', 'atom', and 'atom-comments'.
 */
do_action('rss_tag_pre', 'rss2');
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	xmlns:media="http://search.yahoo.com/mrss"
	<?php
	/**
	 * Fires at the end of the RSS root to add namespaces.
	 *
	 * @since 2.0.0
	 */
	do_action('rss2_ns');
	?>>

	<channel>
		<title><?php wp_title_rss(); ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php bloginfo_rss('url'); ?></link>
		<description><?php bloginfo_rss('description'); ?></description>
		<lastBuildDate><?php echo get_feed_build_date('r'); ?></lastBuildDate>
		<language><?php bloginfo_rss('language'); ?></language>
		<sy:updatePeriod>
			<?php
			$duration = 'hourly';

			/**
			 * Filters how often to update the RSS feed.
			 *
			 * @since 2.1.0
			 *
			 * @param string $duration The update period. Accepts 'hourly', 'daily', 'weekly', 'monthly',
			 *                         'yearly'. Default 'hourly'.
			 */
			echo apply_filters('rss_update_period', $duration);
			?>
		</sy:updatePeriod>
		<sy:updateFrequency>
			<?php
			$frequency = '1';

			/**
			 * Filters the RSS update frequency.
			 *
			 * @since 2.1.0
			 *
			 * @param string $frequency An integer passed as a string representing the frequency
			 *                          of RSS updates within the update period. Default '1'.
			 */
			echo apply_filters('rss_update_frequency', $frequency);
			?>
		</sy:updateFrequency>
		<?php
		/**
		 * Fires at the end of the RSS2 Feed Header.
		 *
		 * @since 2.0.0
		 */
		do_action('rss2_head');

		while (have_posts()) :
			the_post();
		?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<?php if (get_comments_number() || comments_open()) : ?>
					<comments><?php comments_link_feed(); ?></comments>
				<?php endif; ?>

				<?php
				// Custom ACF authors instead of default WordPress author
				$acf_authors = get_acf_authors_for_feed(get_the_ID());
				foreach ($acf_authors as $author_name) {
					if (!empty($author_name)) {
						echo '<dc:creator><![CDATA[' . esc_html($author_name) . ']]></dc:creator>' . "\n\t\t";
					}
				}
				?>
				<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
				<?php the_category_rss('rss2'); ?>
				<guid isPermaLink="false"><?php the_guid(); ?></guid>

				<?php
				// Updated description logic with priority system
				$post_id = get_the_ID();
				$post_type = get_post_type($post_id);
				$description = '';

				// Priority 1: Try Yoast meta description
				$yoast_meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
				if (!empty($yoast_meta_desc)) {
					// Check if it contains unprocessed Yoast variables (like %%excerpt%%)
					if (strpos($yoast_meta_desc, '%%') !== false) {
						// Has unprocessed variables - try to replace %%excerpt%% specifically
						if (strpos($yoast_meta_desc, '%%excerpt%%') !== false) {
							$excerpt = get_the_excerpt($post_id);
							if (!empty($excerpt)) {
								// Replace %%excerpt%% with actual excerpt
								$yoast_meta_desc = str_replace('%%excerpt%%', $excerpt, $yoast_meta_desc);
							}
						}

						// After replacement, check if there are still unprocessed variables
						if (strpos($yoast_meta_desc, '%%') === false) {
							// All variables processed, use it
							$description = wp_strip_all_tags($yoast_meta_desc);
							$description = trim($description);
						}
						// If still has %% variables, leave $description empty to fall through
					} else {
						// No variables, use Yoast description as is
						$description = wp_strip_all_tags($yoast_meta_desc);
						$description = trim($description);
					}
				}

				// Priority 2: Try excerpt (if Yoast didn't work)
				if (empty($description)) {
					$excerpt = get_the_excerpt($post_id);
					if (!empty($excerpt)) {
						$description = wp_strip_all_tags($excerpt);
						$description = trim($description);
					}
				}

				// Priority 3: For publications, try ACF summary field (if excerpt didn't work)
				if (empty($description) && $post_type === 'publications') {
					$summary = get_field('summary', $post_id);
					if (!empty($summary)) {
						$description = wp_strip_all_tags($summary);
						$description = trim($description);
					}
				}

				// Priority 4: Use beginning of post content (if still no description)
				if (empty($description)) {
					$content = get_post_field('post_content', $post_id);
					if (!empty($content)) {
						// Strip HTML tags and shortcodes
						$content = wp_strip_all_tags(strip_shortcodes($content));
						// Remove extra whitespace and line breaks
						$content = preg_replace('/\s+/', ' ', $content);
						$content = trim($content);

						// Truncate to reasonable length for RSS description (around 160-200 characters)
						if (strlen($content) > 200) {
							$description = substr($content, 0, 197) . '...';
						} else {
							$description = $content;
						}
					}
				}

				// Final fallback if nothing was found
				if (empty($description)) {
					$description = get_bloginfo('description');
				}
				?>

				<?php if (get_option('rss_use_excerpt')) : ?>
					<description>
						<![CDATA[<?php echo $description; ?>]]>
					</description>
				<?php else : ?>
					<description>
						<![CDATA[<?php echo $description; ?>]]>
					</description>
					<?php $content = get_the_content_feed('rss2'); ?>
					<?php if (strlen($content) > 0) : ?>
						<content:encoded>
							<![CDATA[<?php echo $content; ?>]]>
						</content:encoded>
					<?php else : ?>
						<content:encoded>
							<![CDATA[<?php echo $description; ?>]]>
						</content:encoded>
					<?php endif; ?>
				<?php endif; ?>

				<?php if (get_comments_number() || comments_open()) : ?>
					<wfw:commentRss><?php echo esc_url(get_post_comments_feed_link(null, 'rss2')); ?></wfw:commentRss>
					<slash:comments><?php echo get_comments_number(); ?></slash:comments>
				<?php endif; ?>

				<?php rss_enclosure(); ?>

				<?php
				// Add featured image with size parameter support
				if (has_post_thumbnail(get_the_ID())) {
					$imgsize = 'full';
					if (isset($_GET['imgsize'])) {
						$imgsize_param = sanitize_text_field($_GET['imgsize']);
						if ($imgsize_param === 'lg') {
							$imgsize = 'large';
						}
					}

					$thumbnail_ID = get_post_thumbnail_id(get_the_ID());
					$thumbnail = wp_get_attachment_image_src($thumbnail_ID, $imgsize);

					if (!empty($thumbnail)) {
						$thumbnail_url = esc_url($thumbnail[0]);
						$width = intval($thumbnail[1]);
						$height = intval($thumbnail[2]);

						echo '<media:content url="' . $thumbnail_url . '" medium="image" width="' . $width . '" height="' . $height . '" />' . "\n\t\t";
					}
				}
				?>

				<?php
				/**
				 * Fires at the end of each RSS2 feed item.
				 *
				 * @since 2.0.0
				 */
				do_action('rss2_item');
				?>
			</item>
		<?php endwhile; ?>
	</channel>
</rss>