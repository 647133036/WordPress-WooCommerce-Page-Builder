<?php

/** @var array $options */
/** @var NewsletterFields $fields */
/** @var NewsletterControls $controls */
/** @var array $composer */

$background = empty($options['block_background']) ? $composer['block_background'] : $options['block_background'];
?>
<p>
    <a href="https://www.thenewsletterplugin.com/documentation/newsletters/newsletter-tags/"
       target="_blank">You can use tags to inject subscriber fields</a>.
</p>

<p>Ask AI what you want to be written</p>
<div style="display: flex; width: 100%;">
    <div style="flex-grow: 2; margin-right: .5em;">
        <?php $fields->text('prompt', '') ?>
    </div>
    <div class="tnpf-field" style="flex-grow: 0; max-width: fit-content">
        <button class="" onclick="tnp_ai_generate(this)"><i class="far fa-smile"></i></button>
    </div>
</div>

<?php

$fields->wp_editor('html', 'Content', [
    'text_font_family' => $composer['text_font_family'],
    'text_font_size' => $composer['text_font_size'],
    'text_font_weight' => $composer['text_font_weight'],
    'text_font_color' => $composer['text_font_color'],
    'background' => $background
])
?>
<?php $fields->block_commons() ?>
