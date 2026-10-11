<?php

namespace Newsletter\Composer;

defined('ABSPATH') || exit;

/**
 * Used only for IDE autocompletion!
 */
class Block {

    var $options = [];

    var $layout = 'default';
    var $style = '';
    var $width;
    var $content_width;
    var $background_color = '';
    var $background_wide = false;
    var $padding_left = '';
    var $padding_right = '';
    var $padding_top = '';
    var $padding_bottom = '';
    var $border_radius = 0;
    var $border_color = 0;
}
