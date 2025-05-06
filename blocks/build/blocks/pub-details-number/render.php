<?php
$post_id = get_the_ID();
$pubNumber = get_field('publication_number', $post_id);
$link = !empty($attributes['link']) ? $attributes['link'] : null;
$showTooltip = $attributes['showTooltip'] ?? true;
$displayAsInfo = $attributes['displayAsInfo'] ?? false;
$attrs = $is_preview ? ' ' : get_block_wrapper_attributes();

$tooltip = '';
$pubType = '';
if ($pubNumber) {
    $firstChar = strtoupper(substr($pubNumber, 0, 1));
    switch ($firstChar) {
        case 'B':
            $tooltip = 'This is a bulletin.';
            $pubType = 'Bulletin';
            $description = 'Bulletins represent a major writing effort and cover a broad subject area. They address individual topics in a particular discipline for a specific commodity.';
            break;
        case 'C':
            $tooltip = 'This is a circular.';
            $pubType = 'Circular';
            $description = 'Circulars are more focused than Bulletins and will discuss one subject in a limited form.';
            break;
        default:
            $tooltip = 'This is a general publication.';
            $pubType = 'general publication';
            $description = 'This is where the text for this type will go.';
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
                        <?php echo esc_html($pubNumber); ?>
                    </a>
                <?php else: ?>
                    <span
                        class="pub-number-link tooltip-trigger"
                        aria-describedby="<?php echo esc_attr($tooltip_id); ?>"
                        tabindex="0">
                        <?php echo esc_html($pubNumber); ?>
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
                <span class="pub-number-link">
                    <?php echo esc_html($pubNumber); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </p>
<?php endif; ?>
