<?php
namespace Newsletter\Composer;

defined('ABSPATH') || exit;

/**
 * Used only for IDE autocompletion!
 */
class Style {

    var $font_family;
    var $font_size;
    var $font_weight;
    var $font_color;
    var $background;
    var $align;
    var $scalable = true;

    function echo_css($scale = 1.0) {
        echo 'font-size: ', round($this->font_size * $scale), 'px;';
        echo 'font-family: ', esc_html($this->font_family), ';';
        echo 'font-weight: ', esc_html($this->font_weight), ';';
        echo 'color: ', sanitize_hex_color($this->font_color), ';';
        if (!empty($this->align)) {
            echo 'text-align: ', esc_html($this->align), ';';
        }
    }
}

