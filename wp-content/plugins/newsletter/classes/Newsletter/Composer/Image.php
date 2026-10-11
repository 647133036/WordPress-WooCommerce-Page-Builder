<?php
namespace Newsletter\Composer;

defined('ABSPATH') || exit;

/**
 * Used only for IDE autocompletion!
 */
class Image {
    var $id;
    var $url;
    var $width;
    var $height;
    var $alt;
    var $link;
    var $align = 'center';

    /** Sets the width keeping the aspect ratio */
    public function set_width($width) {
        $width = (int) $width;
        if (empty($width)) {
            return;
        }
        if ($this->width < $width) {
            return;
        }
        $this->height = floor(($width / $this->width) * $this->height);
        $this->width = $width;
    }

    /** Sets the height  keeping the aspect ratio */
    public function set_height($height) {
        $height = (int) $height;
        if (empty($height)) {
            return;
        }
        $this->width = floor(($height / $this->height) * $this->width);
        $this->height = $height;
    }
}

