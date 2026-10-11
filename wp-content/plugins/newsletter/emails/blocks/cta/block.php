<?php

/*
 * Name: Call To Action
 * Section: content
 * Description: Call to action button
 */

/** @var array $options */
/** @var array $composer */
/** @var array $info */
/** @var string $context */

$defaults = array(
    'button_label' => 'Call to action',
    'button_url' => home_url(),
    'button_font_family' => '',
    'button_font_size' => '',
    'button_font_weight' => '',
    'button_font_color' => '',
    'button_background' => '',
    'button_border_color' => '',
    'align' => 'center',
    'block_background' => '',
    'button_width' => '',
    'button_align' => 'center',
    'block_padding_top' => 16,
    'block_padding_bottom' => 16,
    'block_style' => '',
);

if ($context['type'] === 'confirmation') {
    $defaults['button_url'] = '{confirmation_url}';
    $defaults['button_label'] = __('Confirm subscription', 'newsletter');
}

if (!empty($options['block_style'])) {
    if ($options['block_style'] === 'wire') {
        $options['button_background'] = $composer['block_background'];
        $options['button_border_color'] = $composer['button_background_color'];
        $options['button_font_color'] = '#000000';
    } elseif ($options['block_style'] === 'inverted') {
        $options['button_background'] = '#000000';
        $options['button_border_color'] = '';
        $options['button_font_color'] = '#ffffff';
    } elseif ($options['block_style'] === 'default') {
        $options['button_background'] = '';
        $options['button_border_color'] = '';
        $options['button_font_color'] = '';
    }
}

// Migration from old option names
if (!empty($options['font_color']))
    $options['button_font_color'] = $options['font_color'];
if (!empty($options['url']))
    $options['button_url'] = $options['url'];
if (!empty($options['font_family']))
    $options['button_font_family'] = $options['font_family'];
if (!empty($options['font_size']))
    $options['button_font_size'] = $options['font_size'];
if (!empty($options['font_weight']))
    $options['button_font_weight'] = $options['font_weight'];
if (!empty($options['background']))
    $options['button_background'] = $options['background'];
if (!empty($options['text']))
    $options['button_label'] = $options['text'];
if (!empty($options['width']))
    $options['button_width'] = $options['width'];

unset($options['font_color']);
unset($options['url']);
unset($options['font_family']);
unset($options['font_size']);
unset($options['font_weight']);
unset($options['background']);
unset($options['text']);
unset($options['width']);

$options = array_merge($defaults, $options);

$button_options = $options;

if (method_exists('NewsletterReports', 'build_lists_change_url')) {
    $lists = [];
    if (!empty($options['list'])) {
        $lists[$options['list']] = 1;
    }
    if (!empty($options['unlist'])) {
        $lists[$options['unlist']] = 0;
    }
    if ($lists) {
        // @phpstan-ignore-next-line
        $button_options["button_url"] = NewsletterReports::build_lists_change_url($options["button_url"], $lists);
    }
}

echo TNP_Composer::button($button_options, 'button', $composer);