<?php
namespace Newsletter\Composer;

defined('ABSPATH') || exit;

/**
 * Used only for IDE autocompletion!
 */
class Event {
    var $title;
    var $excerpt;
    var $url;
    /** @var Image */
    var $image;

    var $location_address;
    var $location_name;

    var $start_timestamp = 0;
    var $end_timestamp = 0;

    
}

