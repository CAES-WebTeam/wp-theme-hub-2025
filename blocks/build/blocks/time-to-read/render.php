<?php
function time_to_read()
{
    // Get the current post's content.
    $post = get_post();
    $content = $post->post_content;

    // Get the number of words in the content.
    $word_count = str_word_count(strip_tags($content));

    // Calculate the reading time.
    $reading_time = ceil($word_count / 200);

    return $reading_time;
}
?>

<p <?php echo get_block_wrapper_attributes(); ?>><?php echo time_to_read(); ?> min read</p>