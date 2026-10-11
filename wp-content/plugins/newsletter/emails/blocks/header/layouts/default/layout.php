<?php
/** @var array $options */
/** @var array $composer */
/** @var string $dir */
/** @var array $info */
/** @var TNP_Media $media */

$text_style = TNP_Composer::get_text_style($options, '', $composer);
$title_style = TNP_Composer::get_title_style($options, 'title', $composer);
if ($media) {
    $image_width = (int) (600 - $options['block_padding_left'] - $options['block_padding_right']) / 2;
    if ($options['logo_width']) {
        $image_width = min($options['logo_width'], $image_width);
    }
    $media->set_width($image_width);
}
?>
<style>

    .link {
        text-decoration: none;
        line-height: normal;
    }
    .text {
        <?php $text_style->echo_css(0.9) ?>
        text-decoration: none;
        line-height: 150%;
    }

    .title {
        <?php $title_style->echo_css(0.9) ?>
        text-decoration: none;
        line-height: normal;
        margin: 0;
    }

    .logo {
        <?php $text_style->echo_css() ?>
        line-height: normal !important;
    }
</style>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0; border-collapse: collapse;" role="presentation">
    <tr>
        <td align="center" width="20%" valign="center" dir="<?php echo $dir ?>">
            <?php if ($media) { ?>
                <?php echo TNP_Composer::image($media) ?>
            <?php } ?>

        </td>
        <td width="80%" align="center" valign="center" dir="<?php echo $dir ?>">
            <a href="<?php echo esc_attr(home_url()); ?>" target="_blank" inline-class="link">
                <span inline-class="title"><?php echo esc_attr($info['header_title']) ?></span>
                <br>
                <span inline-class="text"><?php echo esc_html($info['header_sub']) ?></span>
            </a>
        </td>
    </tr>
</table>
