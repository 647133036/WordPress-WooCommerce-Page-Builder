<?php
/** @var array $options */
/** @var NewsletterControls $controls  */
/** @var NewsletterFields $fields */
?>

<?php
$fields->select('editor', __('Editor', 'newsletter'), [
    'default' => __('Default', 'newsletter'),
    'full' => __('Full', 'newsletter'),
], ['after-rendering' => 'reload']);

$background = $options['block_background'] ?? '#aaa';
$color = $options['font_color'] ?? '#fff';

?>

<div class="tnp-accordion">

    <h3><?php esc_html_e('Appearance', 'newsletter'); ?></h3>
    <div>
        <?php if ($options['editor'] === 'full') { ?>
        <?php $fields->wp_editor_simple('text', __('Text', 'newsletter'), ['background' => $background, 'color' => $color]); ?>
        <?php } else { ?>
        <?php $fields->textarea('text', __('Text', 'newsletter')); ?>
        <?php } ?>
        <?php $fields->font('font', false, ['family_default' => true, 'size_default' => true, 'weight_default' => true]); ?>
        <?php $fields->align(); ?>
    </div>

    <h3><?php esc_html_e('Commons', 'newsletter'); ?></h3>
    <div>
        <?php $fields->block_commons() ?>
    </div>
</div>
