<?php
$sections = [
    'showAreasOfExpertise' => [ 'label' => 'Areas of expertise',  'anchor' => 'areas-of-expertise' ],
    'showAbout'            => [ 'label' => 'About',               'anchor' => 'about' ],
    'showEducation'        => [ 'label' => 'Education',           'anchor' => 'education' ],
    'showAwards'           => [ 'label' => 'Awards and honors',   'anchor' => 'awards-and-honors' ],
    'showCourses'          => [ 'label' => 'Courses',             'anchor' => 'courses' ],
    'showScholarlyWorks'   => [ 'label' => 'Scholarly works',     'anchor' => 'scholarly-works' ],
];

$links = [];
foreach ($sections as $attr => $section) {
    $enabled = isset($block[$attr]) ? (bool) $block[$attr] : true;
    if ($enabled) {
        $links[] = $section;
    }
}

if (empty($links)) {
    return;
}

$attrs = $is_preview ? ' ' : get_block_wrapper_attributes(['class' => 'person-section-nav']);

echo '<nav ' . $attrs . '>';
foreach ($links as $link) {
    echo '<a href="#' . esc_attr($link['anchor']) . '" class="person-section-nav__link">' . esc_html($link['label']) . '</a>';
}
echo '</nav>';
?>
