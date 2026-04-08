<?php
$author_id = isset($GLOBALS['caes_current_user_id']) ? $GLOBALS['caes_current_user_id'] : get_queried_object_id();
$person_post_id = function_exists('resolve_person_post_id') ? resolve_person_post_id($author_id) : null;

// Check data availability per section
$has = [
    'showAreasOfExpertise' => false,
    'showAbout'            => false,
    'showEducation'        => false,
    'showAwards'           => false,
    'showCourses'          => false,
    'showScholarlyWorks'   => false,
];

if ($person_post_id) {
    $term_ids = get_field('elements_areas_of_expertise', $person_post_id);
    $has['showAreasOfExpertise'] = !empty($term_ids);

    $has['showAbout'] = !empty(get_field('elements_overview', $person_post_id));

    $has['showEducation'] = !empty(get_field('elements_degrees', $person_post_id));

    $has['showAwards']       = !empty(get_field('elements_distinctions', $person_post_id));
    $has['showCourses']      = !empty(get_field('elements_courses_taught', $person_post_id));
    $has['showScholarlyWorks'] = !empty(get_field('elements_scholarly_works', $person_post_id));
}

$sections = [
    'showAreasOfExpertise' => [ 'label' => 'Areas of expertise', 'anchor' => 'areas-of-expertise' ],
    'showAbout'            => [ 'label' => 'About',              'anchor' => 'about' ],
    'showEducation'        => [ 'label' => 'Education',          'anchor' => 'education' ],
    'showAwards'           => [ 'label' => 'Awards and honors',  'anchor' => 'awards-and-honors' ],
    'showCourses'          => [ 'label' => 'Courses',            'anchor' => 'courses' ],
    'showScholarlyWorks'   => [ 'label' => 'Scholarly works',    'anchor' => 'scholarly-works' ],
];

$links = [];
foreach ($sections as $attr => $section) {
    $enabled = isset($attributes[$attr]) ? (bool) $attributes[$attr] : true;
    if ($enabled && $has[$attr]) {
        $links[] = $section;
    }
}

if (empty($links)) {
    return;
}

$attrs = get_block_wrapper_attributes(['class' => 'person-section-nav']);

echo '<nav ' . $attrs . '>';
foreach ($links as $link) {
    echo '<a href="#' . esc_attr($link['anchor']) . '" class="person-section-nav__link">' . esc_html($link['label']) . '</a>';
}
echo '</nav>';
?>
