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
            $description = 'An annual Extension publication provides timely, research-based information that is updated annually, such as spray guides for commercial fruit growers, or reports about UGA research trials on turfgrass, vegetables, and more.';
            break;
        case 'TP':
            $tooltip = 'This is a temporary publication. Learn more by clicking the publication number.';
            $pubType = 'Temporary Publication';
            $description = 'A temporary publication provides current-issue or event-related information that needs to be provided to the public immediately.';
            break;
        // Add more 2-character prefixes here if needed
        default:
            switch ($firstChar) {
                case 'B':
                    $tooltip = 'This is a bulletin. Learn more by clicking the publication number.';
                    $pubType = 'Bulletin';
                    $description = 'A bulletin is an Extension publication that covers a broad subject area, such as native plants in Georgia or how to prepare your family for emergencies or natural disasters.';
                    break;
                case 'C':
                    $tooltip = 'This is a circular. Learn more by clicking the publication number.';
                    $pubType = 'Circular';
                    $description = 'A circular is an Extension publication that covers a single topic briefly but thoroughly.';
                    break;
                default:
                    $tooltip = 'This is a general publication.';
                    $pubType = 'general publication';
                    $description = '';
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