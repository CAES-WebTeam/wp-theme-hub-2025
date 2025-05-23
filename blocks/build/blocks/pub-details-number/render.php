<?php
$post_id = get_the_ID();
$pubNumber = get_field('publication_number', $post_id);
$link = !empty($attributes['link']) ? $attributes['link'] : null;
$showTooltip = $attributes['showTooltip'] ?? true;
$displayAsInfo = $attributes['displayAsInfo'] ?? false;
$attrs = get_block_wrapper_attributes();

$tooltip = '';
$pubType = '';
$description = '';

if ($pubNumber) {
    $prefix = strtoupper(substr($pubNumber, 0, 2));
    $firstChar = strtoupper(substr($pubNumber, 0, 1));

    switch ($prefix) {
        case 'AP':
            $tooltip = 'This is an annual publication. Learn more by clicking the publication number.';
            $pubType = 'Annual Publication';
            $description = 'Annual publications address a comprehensive issue and are updated each year. These are typically large handbooks or commodity reports.';
            break;
        case 'TP':
            $tooltip = 'This is a temporary publication. Learn more by clicking the publication number.';
            $pubType = 'Temporary Publication';
            $description = 'Temporary publications are issue- or event-related and require immediate dissemination to the public. These are only available on the UGA Extension website for three months.';
            break;
        // Add more 2-character prefixes here if needed
        default:
            switch ($firstChar) {
                case 'B':
                    $tooltip = 'This is a bulletin. Learn more by clicking the publication number.';
                    $pubType = 'Bulletin';
                    $description = 'Bulletins represent a major writing effort and cover a broad subject area. They address individual topics in a particular discipline for a specific commodity.';
                    break;
                case 'C':
                    $tooltip = 'This is a circular. Learn more by clicking the publication number.';
                    $pubType = 'Circular';
                    $description = 'Circulars are more focused than Bulletins and will discuss one subject in a limited form.';
                    break;
                default:
                    $tooltip = 'This is a general publication.';
                    $pubType = 'general publication';
                    $description = 'This is where the text for this type will go.';
                    break;
            }
            break;
    }
}

$tooltip_id = 'pub-tooltip-' . $post_id;
?>

<?php if ($displayAsInfo): ?>
    <div <?php echo $attrs; ?>>
        <h2 class="is-style-caes-hub-section-heading has-x-large-font-size"><?php echo esc_html__('What is a ' . $pubType . '?', 'caes-hub'); ?></h2>
        <p>
            <?php echo esc_html($description); ?>
        </p>
    </div>
<?php elseif ($pubNumber): ?>
    <p <?php echo $attrs; ?>>
        <?php if ($showTooltip): ?>
            <span class="tooltip-container">
                <?php if ($link): ?>
                    <a
                        href="<?php echo esc_url($link); ?>"
                        class="pub-number-link tooltip-trigger"
                        aria-describedby="<?php echo esc_attr($tooltip_id); ?>"
                        tabindex="0">
                        <span><?php echo esc_html($pubNumber); ?></span>
                    </a>
                <?php else: ?>
                    <span
                        class="pub-number-link tooltip-trigger"
                        aria-describedby="<?php echo esc_attr($tooltip_id); ?>"
                        tabindex="0">
                        <span><?php echo esc_html($pubNumber); ?></span>
                    </span>
                <?php endif; ?>

                <span
                    id="<?php echo esc_attr($tooltip_id); ?>"
                    class="tooltip"
                    role="tooltip">
                    <span class="tooltip-content"><?php echo esc_html($tooltip); ?></span>
                </span>
            </span>
        <?php else: ?>
            <?php if ($link): ?>
                <a href="<?php echo esc_url($link); ?>" class="pub-number-link">
                    <?php echo esc_html($pubNumber); ?>
                </a>
            <?php else: ?>
                <span>
                    <?php echo esc_html($pubNumber); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </p>
<?php endif; ?>