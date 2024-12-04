<?php
// Get the current post ID
$post_id = get_the_ID();

?>

<h3>Additional Documents</h3>

<?php if( !empty(get_field('documents', $post_id)) ): ?>
	<?php foreach(get_field('documents', $post_id) as $item): ?>

		<?php  if($item['document_type']=='link'): ?>
		<div class="event-detail-document-link"><a href="<?php echo $item['link']; ?>"><?php echo $item['link']; ?></a></div>
		<?php endif; ?>

		<?php  if($item['document_type']=='file'): ?>
		<div class="event-detail-document-file"><a href="<?php echo $item['file']['url']; ?>"><?php echo $item['file']['title']; ?></a></div>
		<?php endif; ?>

	<?php endforeach; ?>
<?php endif; ?>