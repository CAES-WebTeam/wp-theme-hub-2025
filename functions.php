<?php
/*
 *  Author: UGA - CAES OIT, Frankel Agency
 *  URL: hub.caes.uga.edu
 *  Custom functions, support, custom post types and more.
 */

/*------------------------------------*\
	Load files
\*------------------------------------*/

require get_template_directory() . '/inc/theme-support.php';
require get_template_directory() . '/inc/post-types.php';
require get_template_directory() . '/inc/blocks.php';
require get_template_directory() . '/inc/acf.php';
require get_template_directory() . '/inc/events-support.php';
require get_template_directory() . '/inc/publications-support.php';

/* Filter for search form */
add_filter('get_search_form', function($form) {
    // Customize the default search form markup
    $form = '
    <form role="search" method="get" class="caes-hub-form__input-button-container" action="' . esc_url(home_url('/')) . '">
        <label>
            <span class="screen-reader-text">' . _x('Search for:', 'label') . '</span>
            <input type="search" class="caes-hub-form__input" placeholder="' . esc_attr_x('Search …', 'placeholder') . '" value="' . get_search_query() . '" name="s">
        </label>
    </form>';
    return $form;
});
