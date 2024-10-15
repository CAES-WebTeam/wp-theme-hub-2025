<div class="wp-block-caes-hub-article-action-icons">
  <?php echo '<img loading="lazy" class="article-action-icon bookmark" src="' . esc_url(get_template_directory_uri() . '/assets/images/bookmark.svg') . '" alt="Save to Favorites" /> ' ?>
  <!-- Opens a modal. -->
  <?php echo '<img loading="lazy" class="article-action-icon share" src="' . esc_url(get_template_directory_uri() . '/assets/images/share.svg') . '" alt="Share Article" /> ' ?>
  <?php echo '<img loading="lazy" class="article-action-icon pdf" src="' . esc_url(get_template_directory_uri() . '/assets/images/pdf.svg') . '" alt="Download PDF" /> ' ?>
  <?php echo '<img loading="lazy" class="article-action-icon print" src="' . esc_url(get_template_directory_uri() . '/assets/images/print.svg') . '" alt="Print Article" /> ' ?>
</div>

<?php
$unique_id = wp_unique_id('p-');
?>

<div
  <?php echo get_block_wrapper_attributes(); ?>
  data-wp-interactive="article-action-icons"
  <?php echo wp_interactivity_data_wp_context(array('isOpen' => false)); ?>
  data-wp-watch="callbacks.logIsOpen">
  <button
    data-wp-on--click="actions.toggle"
    data-wp-bind--aria-expanded="context.isOpen"
    aria-controls="<?php echo esc_attr($unique_id); ?>">
    <?php esc_html_e('Toggle', 'article-action-icons'); ?>
  </button>

  <div
    id="<?php echo esc_attr($unique_id); ?>"
    class="wp-block-caes-hub-modal modal-hidden"
    data-wp-class--modal-hidden="!context.isOpen"
    data-wp-class--is-visible="context.isOpen">
    <div class="wp-block-caes-hub-modal__content">
      <?php
      esc_html_e('Donation Calculator - hello from an interactive block!', 'article-action-icons');
      ?>
    </div>
  </div>
</div>
