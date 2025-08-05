<?php
$post_url = esc_url(get_permalink());
$path_url = wp_make_link_relative(get_permalink($post_id));
$post_title = esc_js(get_the_title());
$post_image = esc_url(get_the_post_thumbnail_url());
$unique_id = 'caes-hub-copy-url-' . uniqid();
?>


<div data-wp-interactive="action-share" <?php echo \get_block_wrapper_attributes(); ?>
    <?php echo \wp_interactivity_data_wp_context(['isOpen' => false, 'postUrl' => $post_url, 'postTitle' => $post_title, 'postImage' => $post_image]); ?>>
    <button class="caes-hub-action-share__button" data-wp-on-async--click="actions.toggle"><span class="label">Share</span></button>
    <dialog
        data-wp-watch="callbacks.openModal"
        class="caes-hub-modal">
        <form method="dialog">
            <button type="submit" data-wp-on-async--click="actions.toggle" class="caes-hub-modal__close"><span class="sr-only">Close</span></button>
        </form>
        <div class="caes-hub-modal__content">
            <h2>Share</h2>
            <ul class="caes-hub-action-share__list">
                <li><button class="caes-hub-action-share-social__button caes-hub-action-share-fb__button" data-wp-on-async--click="actions.shareOnFacebook" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="facebook"><span class="sr-only">Share on Facebook</span></button></li>
                <li><button class="caes-hub-action-share-social__button caes-hub-action-share-x__button" data-wp-on-async--click="actions.shareOnX" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="x"><span class="sr-only">Share on X</span></button></li>
                <li><button class="caes-hub-action-share-social__button caes-hub-action-share-pinterest__button" data-wp-on-async--click="actions.shareOnPinterest" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="pinterest"><span class="sr-only">Share on Pinterest</span></button></li>
                <li><button class="caes-hub-action-share-social__button caes-hub-action-share-linkedin__button" data-wp-on-async--click="actions.shareOnLinkedIn" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="linkedin"><span class="sr-only">Share on LinkedIn</span></button></li>
                <li><button class="caes-hub-action-share-social__button caes-hub-action-share-reddit__button" data-wp-on-async--click="actions.shareOnReddit" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="reddit"><span class="sr-only">Share on Reddit</span></button></li>
                <li><button class="caes-hub-action-share-social__button caes-hub-action-share-email__button" data-wp-on-async--click="actions.shareByEmail" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="email"><span class="sr-only">Share via Email</span></button></li>
            </ul>
            <div class="caes-hub-copy-url">
                <div class="caes-hub-form__input-button-container caes-hub-copy-url__container">
                    <label for="<?php echo esc_attr($unique_id); ?>" class="sr-only">Copy URL:</label>
                    <input
                        id="<?php echo esc_attr($unique_id); ?>"
                        type="text"
                        class="caes-hub-form__input caes-hub-copy-url__field"
                        value="<?php echo esc_url(get_permalink()); ?>"
                        readonly>
                    <button class="caes-hub-form__button caes-hub-copy-url__button" data-share-url="<?php echo esc_attr($path_url); ?>" data-share-platform="copy_url" data-wp-on-async--click="actions.copyUrl">Copy</button>
                    <span class="caes-hub-copy-url__tooltip" style="display: none;">Copied!</span>
                </div>
            </div>
        </div>
    </dialog>
</div>