<?php
/** @var array $options */
/** @var NewsletterControls $controls  */
/** @var NewsletterFields $fields */
?>
<p>
    We suggest <a href="https://wordpress.org/plugins/instant-images/" target="_blank">Instant Images</a> to get all images
    you need directly on the media gallery.
</p>

<?php $controls->hidden('placeholder') ?>

<div class="tnp-accordion">
    <h3><?php esc_html_e('Media gallery', 'newsletter'); ?></h3>
    <div>
        <?php $fields->media('image', 'Choose an image', ['alt' => true]) ?>
    </div>

    <h3><?php esc_html_e('External URL', 'newsletter'); ?></h3>
    <div>
        <p>
            Use a direct image URL to external services
            (for example <a href="https://niftyimages.com/" target="_blank">niftyimages.com</a>).
            <strong>It has priority over the media selected from your gallery.</strong>
        </p>

        <?php $fields->url('image-url', __('Image URL', 'newsletter')) ?>
        <?php $fields->text('image-alt', 'Alternative text') ?>
    </div>

    <h3><?php esc_html_e('Link and appearance', 'newsletter'); ?></h3>
    <div>
        <?php $fields->url('url', __('Link URL', 'newsletter')) ?>

        <div class="tnp-field-row">
            <div class="tnp-field-col-2">
                <?php $fields->size('width', __('Width', 'newsletter')) ?>
            </div>
            <div class="tnp-field-col-2">
                <?php $fields->align(); ?>
            </div>
        </div>
    </div>

    <h3><?php esc_html_e('Commons', 'newsletter'); ?></h3>
    <div>
        <?php $fields->block_commons() ?>
    </div>
</div>


