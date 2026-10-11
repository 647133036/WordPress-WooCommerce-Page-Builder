<?php
/*
 * Name: Heading
 * Section: content
 * Description: Section title
 */

/** @var array $options */
/** @var array $composer */
/** @var string $dir */

$defaults = array(
    'text' => 'An Awesome Title',
    'align' => 'center',
    'font_family' => '',
    'font_size' => '',
    'font_color' => '',
    'font_weight' => '',
    'block_padding_left' => 15,
    'block_padding_right' => 15,
    'block_padding_bottom' => 15,
    'block_padding_top' => 15,
    'block_background' => '',
    'block_background_wide' => '0'
);
$options = array_merge($defaults, $options);

$title_style = TNP_Composer::get_title_style($options, '', $composer);
$options['text'] = strip_tags($options['text'], '<br><span><b><strong><i><em>')
?>

<style>
    .title {
        <?php $title_style->echo_css(); ?>
        padding: 0;
        line-height: 130% !important;
        letter-spacing: normal;
    }
    .title-td {
        text-align: <?php echo esc_html($options['align']); ?>;
    }
</style>

<table border="0" cellspacing="0" cellpadding="0" width="100%" role="presentation">
    <tr>
        <td align="<?php echo esc_attr($options['align']) ?>" valign="middle" dir="<?php echo $dir; ?>" inline-class="title-td">
            <div inline-class="title" role="heading" aria-level="1"><?php echo wp_kses_post($options['text']); ?></div>
        </td>
    </tr>
</table>