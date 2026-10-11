<?php

/**
 * Generates and HTML button for email using the values found on $options and
 * prefixed by $prefix, with the standard syntax of NewsletterFields::button().
 *
 * @param array $options
 * @param string $prefix
 * @return string
 */
function tnpc_button($options, $prefix = 'button') {
    return TNP_Composer::button($options, $prefix);
}

class TNP_Composer {

    /**
     * @deprecated since version 8.8.3 user NewsletterComposer::register_block()
     * @param string $dir
     * @return type
     */
    static function register_block($dir) {
        return NewsletterComposer::instance()->register_block($dir);
    }

    /**
     * @deprecated since version 8.8.3
     * @param string $dir
     * @return Newsletter\Composer\Template
     */
    static function register_template($dir) {
        return NewsletterComposer::instance()->register_template($dir);
    }

    /**
     * @deprecated since version 8.8.5
     */
    static function update_email($email, $controls) {
        return NewsletterComposer::instance()->update_email($email, $controls);
    }

    /**
     * @deprecated since version 8.8.5
     */
    static function prepare_controls($controls, $email = null) {
        return NewsletterComposer::instance()->update_controls($controls, $email);
    }

    /**
     * Extract inline edited post field from inline_edit_list[]
     *
     * @param array $inline_edit_list
     * @param string $field_type
     * @param int $post_id
     *
     * @return string
     */
    static function get_edited_inline_post_field($inline_edit_list, $field_type, $post_id) {

        foreach ($inline_edit_list as $edit) {
            if ($edit['type'] == $field_type && $edit['post_id'] == $post_id) {
                return $edit['content'];
            }
        }

        return '';
    }

    /**
     * Check if inline_edit_list[] have inline edit field for specific post
     *
     * @param array $inline_edit_list
     * @param string $field_type
     * @param int $post_id
     *
     * @return bool
     */
    static function is_post_field_edited_inline($inline_edit_list, $field_type, $post_id) {
        if (empty($inline_edit_list) || !is_array($inline_edit_list)) {
            return false;
        }
        foreach ($inline_edit_list as $edit) {
            if ($edit['type'] == $field_type && $edit['post_id'] == $post_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Creates the HTML for a button extrating from the options, with the provided prefix, the button attributes:
     *
     * - [prefix]_url The button URL
     * - [prefix]_font_family
     * - [prefix]_font_size
     * - [prefix]_font_weight
     * - [prefix]_label
     * - [prefix]_font_color The label color
     * - [prefix]_background The button color
     *
     * TODO: Add radius and possiblt the alignment
     *
     * @param array $options
     * @param string $prefix
     * @return string
     */
    static function button($options, $prefix = 'button', $composer = []) {
        if (empty($options[$prefix . '_label'])) {
            return;
        }

        $composer = array_filter($composer);

        $defaults = [
            $prefix . '_url' => '#',
            $prefix . '_font_family' => $composer['button_font_family'] ?? 'sans-serif',
            $prefix . '_font_color' => $composer['button_font_color'] ?? '#fff',
            $prefix . '_font_weight' => $composer['button_font_weight'] ?? 'normal',
            $prefix . '_font_size' => $composer['button_font_size'] ?? '16',
            $prefix . '_background' => $composer['button_background'] ?? '#000',
            $prefix . '_border_radius' => $composer['button_border_radius'] ?? '0',
            $prefix . '_border_color' => $composer['button_border_color'] ?? '#000',
            $prefix . '_align' => 'center',
            $prefix . '_width' => 'auto'
        ];

        $options = array_merge($defaults, array_filter($options));

        $width = $options[$prefix . '_width'];
        if (is_numeric($width)) {
            $width .= 'px';
        }

        switch ($options[$prefix . '_border_radius']) {
            case 'squared':
                $border_radius = 0;
                break;
            case 'rounded':
                $border_radius = 999;
                break;
            default:
                $border_radius = (int) $options[$prefix . '_border_radius'];
        }

        $a_style = 'display:inline-block;'
                . 'color:' . $options[$prefix . '_font_color'] . ';font-family:' . $options[$prefix . '_font_family'] . ';'
                . 'font-size:' . $options[$prefix . '_font_size'] . 'px;font-weight:' . $options[$prefix . '_font_weight'] . ';'
                . 'line-height:120%;margin:0;text-decoration:none;text-transform:none;padding:10px 25px;mso-padding-alt:0px;';
        $a_style .= 'border-radius:' . $border_radius . 'px;';
        $table_style = 'border-collapse:separate !important;line-height:100%;';

        $td_style = 'border-collapse:separate !important;cursor:auto;mso-padding-alt:10px 25px;background:' . $options[$prefix . '_background'] . ';';
        $td_style .= 'border-radius:' . $border_radius . 'px;';
        if ($width) {
            $a_style .= ' width:' . $width . ';';
            $table_style .= 'width:' . $width . ';';
        }

        if (!empty($options[$prefix . '_border_color'])) {
            $td_style .= 'border:1px solid ' . $options[$prefix . '_border_color'] . ';';
        }

        $b = '';
        $b .= '<table border="0" cellpadding="0" cellspacing="0" role="presentation" align="' . esc_attr($options[$prefix . '_align']) . '" style="' . esc_attr($table_style) . '">'
                . '<tr>'
                . '<td align="center" bgcolor="' . esc_attr($options[$prefix . '_background']) . '" role="presentation" style="' . esc_attr($td_style) . '" valign="middle">'
                . '<a href="' . esc_attr($options[$prefix . '_url']) . '" style="' . esc_attr($a_style) . '" target="_blank">' . wp_kses_post($options[$prefix . '_label']) . '</a>'
                . '</td></tr></table>';

        return $b;
    }

    /**
     * Generates an IMG tag, linked if the media has an URL.
     *
     * @param TNP_Media $media
     * @param string $style
     * @return string
     */
    static function image($media, $attr = []) {

        if (!$media) {
            return '';
        }

        $default_attrs = [
            'style' => 'display: inline-block; max-width: 100%!important; height: auto; padding: 0; border: 0; font-size: 12px',
            'class' => '',
            'inline-class' => '',
            'link-style' => 'display: inline-block; font-size: 0; text-decoration: none; line-height: normal!important',
            'link-class' => '',
            'link-inline-class' => '',
            'alt' => 'Image'
        ];

        $attr = array_merge($default_attrs, $attr);

        // inline-class and style attribute are mutually exclusive.
        if (!empty($attr['inline-class'])) {
            $styling = ' inline-class="' . esc_attr($attr['inline-class']) . '" ';
        } else {
            $styling = ' style="' . esc_attr($attr['style']) . '" ';
        }

        //Class and style attribute are mutually exclusive.
        //Class take priority to style because classes will transform to inline style inside block rendering operation
        if (!empty($attr['link-inline-class'])) {
            $link_styling = ' inline-class="' . esc_attr($attr['link-inline-class']) . '" ';
        } else {
            $link_styling = ' style="' . esc_attr($attr['link-style']) . '" ';
        }

        $b = '';
        if ($media->link) {
            $b .= '<a href="' . esc_attr($media->link) . '" target="_blank" rel="noopener nofollow" ';
            $b .= $link_styling;
            $b .= ' class="' . esc_attr($attr['link-class']) . '"';
            $b .= '>';
        } else {
            // The span grants images are not upscaled when fluid (two columns posts block)
            $b .= '<span style="display: inline-block; font-size: 0; text-decoration: none; line-height: normal!important">';
        }

        if ($media) {
            $b .= '<img src="' . esc_attr($media->url) . '" width="' . esc_attr($media->width) . '"';
            if ($media->height) {
                $b .= ' height="' . esc_attr($media->height) . '"';
            }
            $b .= ' alt="' . esc_attr(wp_strip_all_tags($media->alt)) . '" '
                    . ' border="0"'
                    . $styling
                    . ' class="' . esc_attr($attr['class']) . '" '
                    . '>';
        }

        if ($media->link) {
            $b .= '</a>';
        } else {
            $b .= '</span>';
        }

        return $b;
    }

    /**
     * Returns a WP media ID for the specified post (or false if nothing can be found)
     * looking for the featured image or, if missing, taking the first media in the gallery and
     * if again missing, searching the first reference to a media in the post content.
     *
     * The media ID is not checked for real existance of the associated attachment.
     *
     * @param int $post_id
     * @param array $attrs 'use_gallery' (default: true) to search in the gallery, 'use_content' (default: true) to search into the content
     * @return int
     */
    static function get_post_thumbnail_id($post_id) {
        if (is_object($post_id)) {
            $post_id = $post_id->ID;
        }

        // Find a media id to be used as featured image
        $media_id = get_post_thumbnail_id($post_id);
        if (!empty($media_id)) {
            return $media_id;
        }

        $emails_options = NewsletterEmails::instance()->get_main_options();

        if (!empty($emails_options['post_image_use_gallery'])) {
            $attachments = get_children(array('numberpost' => 1, 'post_parent' => $post_id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => 'ASC', 'orderby' => 'menu_order'));
            if (!empty($attachments)) {
                foreach ($attachments as $id => &$attachment) {
                    return $id;
                }
            }
        }

        if (!empty($emails_options['post_image_use_content'])) {
            $post = get_post($post_id);

            $r = preg_match('/wp-image-(\d+)/', $post->post_content, $matches);
            if ($matches) {
                return (int) $matches[1];
            }
        }

        return false;
    }

    /**
     * Builds a TNP_Media object to be used in newsletters from a WP media/attachement ID. The returned
     * media has a size which best match the one requested (this is the standard WP behavior, plugins
     * could change it).
     *
     * @param int $media_id
     * @param array $size
     * @return \TNP_Media Returns null if no media can be found
     */
    function get_media($media_id, $size) {
        $src = wp_get_attachment_image_src($media_id, $size);
        if (!$src) {
            return null;
        }
        $media = new TNP_Media();
        $media->id = $media_id;
        $media->url = $src[0];
        $media->width = $src[1];
        $media->height = $src[2];
        return $media;
    }

    static function post_content($post) {
        $content = $post->post_content;

        if (function_exists('has_blocks') && !has_blocks($post)) {
            $content = wpautop($content);
        }


        remove_shortcode('gallery');
        add_shortcode('gallery', 'tnp_gallery_shortcode');
        $content = do_shortcode($content);

        $content = str_replace('<p>', '<p inline-class="p">', $content);
        $content = str_replace('<li>', '<li inline-class="li">', $content);

        $selected_images = array();
        if (preg_match_all('/<img [^>]+>/', $content, $matches)) {
            foreach ($matches[0] as $image) {
                if (preg_match('/wp-image-([0-9]+)/i', $image, $class_id) && ( $attachment_id = absint($class_id[1]) )) {
                    $selected_images[$image] = $attachment_id;
                }
            }
        }

        foreach ($selected_images as $image => $attachment_id) {
            $src = tnp_media_resize($attachment_id, array(600, 0));
            if (is_wp_error($src)) {
                continue;
            }
            $content = str_replace($image, '<img src="' . $src . '" width="600" style="max-width: 100%">', $content);
        }

        return $content;
    }

    static function get_global_style_defaults() {
        return [
            'options_composer_title_font_family' => 'Verdana, Geneva, sans-serif',
            'options_composer_title_font_size' => 32,
            'options_composer_title_font_weight' => 'normal',
            'options_composer_title_font_color' => '#222222',
            'options_composer_text_font_family' => 'Verdana, Geneva, sans-serif',
            'options_composer_text_font_size' => 16,
            'options_composer_text_font_weight' => 'normal',
            'options_composer_text_font_color' => '#222222',
            'options_composer_button_font_family' => 'Verdana, Geneva, sans-serif',
            'options_composer_button_font_size' => 16,
            'options_composer_button_font_weight' => 'normal',
            'options_composer_button_font_color' => '#FFFFFF',
            'options_composer_button_background' => '#256F9C',
            'options_composer_button_border_color' => '#256F9C',
            'options_composer_button_border_radius' => '0',
            'options_composer_background' => '#FFFFFF',
            'options_composer_block_background' => '#FFFFFF',
            'options_composer_width' => '600'
        ];
    }

    /**
     * Inspired by: https://webdesign.tutsplus.com/tutorials/creating-a-future-proof-responsive-email-without-media-queries--cms-23919
     *
     * Attributes:
     * - columns: number of columns [2]
     * - padding: cells padding [10]
     * - responsive: il on mobile the cell should stack up [true]
     * - width: the whole row width, it should reduced by the external row padding [600]
     *
     * @param string[] $items
     * @param array $attrs
     * @return string
     */
    static function grid($items = [], $attrs = []) {
        $attrs = wp_parse_args($attrs, ['width' => 600, 'columns' => 2, 'widths' => [], 'padding' => 10, 'responsive' => true]);
        $width = (int) $attrs['width'] - 2; // To compensate the border
        $columns = (int) $attrs['columns'];
        if (empty($columns)) {
            return;
        }
        $padding = (int) $attrs['padding'];

        if (empty($attrs['widths'])) {
            $attrs['widths'] = array_fill(0, $columns, 1);
        }
        $column_widths = [];
        $td_widths = [];
        $sum = (float) array_sum($attrs['widths']);
        for ($i = 0; $i < $columns; $i++) {
            $column_widths[$i] = floor(($width) * $attrs['widths'][$i] / $sum);
            $td_widths[$i] = floor((100 * $attrs['widths'][$i] / $sum));
        }

        $td_width = 100 / $columns;
        $chunks = array_chunk($items, $columns);

        if ($attrs['responsive']) {

            $e = '';
            foreach ($chunks as &$chunk) {
                $e .= '<div style="text-align:center;font-size:0;">';
                $e .= NewsletterComposer::OUTLOOK_START_IF . '<table role="presentation" width="100%"><tr>' . NewsletterComposer::OUTLOOK_END_IF;
                $i = 0;
                foreach ($chunk as $idx => &$item) {
                    $e .= NewsletterComposer::OUTLOOK_START_IF . '<td width="' . $td_widths[$i] . '%" style="width:' . $td_widths[$i] . '%;padding:' . $padding . 'px" valign="top">' . NewsletterComposer::OUTLOOK_END_IF;

                    $e .= '<div class="max-width-100" style="width:100%;max-width:' . $column_widths[$i] . 'px;display:inline-block;vertical-align: top;box-sizing: border-box;">';

                    // This element to add padding without deal with border-box not well supported
//                    if ($idx === 0) {
//                        $e .= '<div style="padding:' . $padding . 'px; padding-left: 0;" class="p-0">';
//                    } elseif ($idx === count($items)-1) {
//                        $e .= '<div style="padding:' . $padding . 'px; padding-right: 0" class="p-0">';
//                    } else {
                    $e .= '<div style="padding:' . $padding . 'px;" class="p-0">';
//                    }
                    $e .= $item;
                    $e .= '</div>';
                    $e .= '</div>';

                    $e .= NewsletterComposer::OUTLOOK_START_IF . '</td>' . NewsletterComposer::OUTLOOK_END_IF;
                    $i++;
                }
                $e .= NewsletterComposer::OUTLOOK_START_IF . '</tr></table>' . NewsletterComposer::OUTLOOK_END_IF;
                $e .= '</div>';
            }

            return $e;
        } else {
            $e = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; max-width: 100%!important">';
            foreach ($chunks as &$chunk) {
                $e .= '<tr>';
                foreach ($chunk as &$item) {
                    $e .= '<td width="' . $td_width . '%" style="width:' . $td_width . '%; padding:' . $padding . 'px" valign="top">';
                    $e .= $item;
                    $e .= '</td>';
                }
                $e .= '</tr>';
            }
            $e .= '</table>';
            return $e;
        }
    }

    static function get_text_style($options, $prefix, $composer, $attrs = []) {
        return self::get_style($options, $prefix, $composer, 'text', $attrs);
    }

    static function get_title_style($options, $prefix, $composer, $attrs = []) {
        return self::get_style($options, $prefix, $composer, 'title', $attrs);
    }

    static function get_style($options, $prefix, $composer, $type = 'text', $attrs = []) {
        $style = new TNP_Style();
        $style->scalable = empty($options[$prefix . 'font_size']);
        $scale = 1.0;

        if ($style->scalable) {
            if (!empty($attrs['scale'])) {
                $scale = (float) $attrs['scale'];
            }
        }
        if (!empty($prefix)) {
            $prefix .= '_';
        }

        $style->font_family = empty($options[$prefix . 'font_family']) ? $composer[$type . '_font_family'] : $options[$prefix . 'font_family'];
        $style->font_size = empty($options[$prefix . 'font_size']) ? round($composer[$type . '_font_size'] * $scale) : $options[$prefix . 'font_size'];

        $style->font_color = empty($options[$prefix . 'font_color']) ? $composer[$type . '_font_color'] : $options[$prefix . 'font_color'];
        $style->font_weight = empty($options[$prefix . 'font_weight']) ? $composer[$type . '_font_weight'] : $options[$prefix . 'font_weight'];
        if (!empty($options[$prefix . 'font_align'])) {
            $style->align = $options[$prefix . 'font_align'];
        }
        if ($type === 'button') {
            $style->background = empty($options[$prefix . 'background']) ? $composer[$type . '_background_color'] : $options[$prefix . 'background'];
        }
        return $style;
    }

    static function get_button_options($options, $prefix, $composer) {
        $button_options = [];
        $scale = 1;
        $button_options['button_font_family'] = empty($options[$prefix . '_font_family']) ? $composer['button_font_family'] : $options[$prefix . '_font_family'];
        $button_options['button_font_size'] = empty($options[$prefix . '_font_size']) ? round($composer['button_font_size'] * $scale) : $options[$prefix . '_font_size'];
        $button_options['button_font_color'] = empty($options[$prefix . '_font_color']) ? $composer['button_font_color'] : $options[$prefix . '_font_color'];
        $button_options['button_font_weight'] = empty($options[$prefix . '_font_weight']) ? $composer['button_font_weight'] : $options[$prefix . '_font_weight'];
        $button_options['button_background'] = empty($options[$prefix . '_background']) ? $composer['button_background_color'] : $options[$prefix . '_background'];
        $button_options['button_align'] = empty($options[$prefix . '_align']) ? 'center' : $options[$prefix . '_align'];
        $button_options['button_width'] = empty($options[$prefix . '_width']) ? 'center' : $options[$prefix . '_width'];
        $button_options['button_url'] = empty($options[$prefix . '_url']) ? '#' : $options[$prefix . '_url'];
        $button_options['button_label'] = empty($options[$prefix . '_label']) ? '' : $options[$prefix . '_label'];

        return $button_options;
    }

    /**
     * Return the url of a post with a patch for WPML to be sure the link is built considering the
     * post language (argh!)
     *
     * @param WP_Post $post
     * @return string
     */
    static function get_post_url($post) {
        // WPML does not return the correct permalink for a post: on WP frontend it returns the permalink of the
        // translated post for the current language... ok it's complicated!
        $data = null;
        if (class_exists('SitePress')) {
            $data = apply_filters('wpml_post_language_details', [], $post->ID);
            if (isset($data['language_code'])) {
                Newsletter::instance()->switch_language($data['language_code']);
                //do_action('wpml_switch_language', $data['language_code']);
            }
        }
        $url = get_permalink($post->ID);

        if (isset($data['language_code'])) {
            Newsletter::instance()->restore_language();
            //do_action('wpml_switch_language', $data['language_code']);
        }

        return $url;

        // Interesting but WPML redirect to the current language version of the post...
        //return wp_get_shortlink($post->ID);
    }

    static function get_post_content($post) {
        return $post->post_content;
    }

    static function get_post_title($post) {
        return get_the_title($post);
    }

    static function get_post_date($post, $format = null) {
        return get_the_date($format, $post);
    }

    static function convert_to_text($html) {
        if (!class_exists('DOMDocument')) {
            return '';
        }

        if (!function_exists('ctype_space')) {
            return '';
        }

        // Replace '&' with '&amp;' in URLs to avoid warnings about inavlid entities from loadHTML()
        // Todo: make this more general using a regular expression
        //$logger = PlaintextNewsletterAddon::$instance->get_logger();
        //$logger->debug('html="' . $html . '"');
        $html = str_replace(
                array('&nk=', '&nek=', '&id='),
                array('&amp;nk=', '&amp;nek=', '&amp;id='),
                $html);
        //$logger->debug('new html="' . $html . '"');
        //
        $output = '';

        // Prevents warnings for problems with the HTML
        if (function_exists('libxml_use_internal_errors')) {
            libxml_use_internal_errors(true);
        }
        $dom = new DOMDocument();
        $r = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        if (!$r) {
            return '';
        }
        $bodylist = $dom->getElementsByTagName('body');
        // Of course it should be a single element
        foreach ($bodylist as $body) {
            self::process_dom_element($body, $output);
        }
        return $output;
    }

    static function process_dom_element(DOMElement $parent, &$output) {
        foreach ($parent->childNodes as $node) {
            if (is_a($node, 'DOMElement') && ($node->tagName != 'style')) {

                if ($node->tagName == 'br') {
                    $output .= "\n";
                    continue;
                }

                self::process_dom_element($node, $output);

                if ($node->tagName == 'li') {
                    if ((strlen($output) >= 1) && (substr($output, -1) != "\n")) {
                        $output .= "\n";
                    }
                    continue;
                }

                // If the containing tag was a block level tag, we add a couple of line ending
                if ($node->tagName == 'p' || $node->tagName == 'div' || $node->tagName == 'td') {
                    // Avoid more than one blank line between elements
                    if ((strlen($output) >= 2) && (substr($output, -2) != "\n\n")) {
                        $output .= "\n\n";
                    }
                }

                if ($node->tagName == 'a') {
                    // Check if the children is an image
                    if (is_a($node->childNodes[0], 'DOMElement')) {
                        if ($node->childNodes[0]->tagName == 'img') {
                            continue;
                        }
                    }
                    $output .= ' (' . $node->getAttribute('href') . ') ';
                    continue;
                } elseif ($node->tagName == 'img') {
                    $output .= $node->getAttribute('alt');
                }
            } elseif (is_a($node, 'DOMText')) {

                // Rare error reported about uninitialized variable
                if (isset($node->wholeText)) {
                    // ???
                    //$decoded = utf8_decode($node->wholeText);
                    $decoded = $node->wholeText;
                    //$decoded = trim(html_entity_decode($node->wholeText));
                    // We could avoid ctype_*
                    if (ctype_space($decoded)) {
                        // Append blank only if last character output is not blank.
                        if ((strlen($output) > 0) && !ctype_space(substr($output, -1))) {
                            $output .= ' ';
                        }
                    } else {
                        $output .= trim($node->wholeText);
                        $output .= ' ';
                    }
                } else {
                    // ???
                }
            }
        }
    }
}

class TNP_Style {

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

/**
 * Generate multicolumn and responsive html template for email.
 * Initialize class with max columns per row and start to add cells.
 */
class TNP_Composer_Grid_System {

    /**
     * @var TNP_Composer_Grid_Row[]
     */
    private $rows;

    /**
     * @var int
     */
    private $cells_per_row;

    /**
     * @var int
     */
    private $cells_counter;

    /**
     * TNP_Composer_Grid_System constructor.
     *
     * @param int $columns_per_row Max columns per row
     */
    public function __construct($columns_per_row) {
        $this->cells_per_row = $columns_per_row;
        $this->cells_counter = 0;
        $this->rows = [];
    }

    public function __toString() {
        return $this->render();
    }

    /**
     * Add cell to grid
     *
     * @param TNP_Composer_Grid_Cell $cell
     */
    public function add_cell($cell) {

        if ($this->cells_counter % $this->cells_per_row === 0) {
            $this->add_row(new TNP_Composer_Grid_Row());
        }

        $row_idx = (int) floor($this->cells_counter / $this->cells_per_row);
        $this->rows[$row_idx]->add_cell($cell);
        $this->cells_counter++;
    }

    private function add_row($row) {
        $this->rows[] = $row;
    }

    public function render() {

        $str = '';
        foreach ($this->rows as $row) {
            $str .= $row->render();
        }

        return $str;
    }
}

/**
 * Class TNP_Composer_Grid_Row
 */
class TNP_Composer_Grid_Row {

    /**
     * @var TNP_Composer_Grid_Cell[]
     */
    private $cells;

    public function __construct(...$cells) {
        if (!empty($cells)) {
            foreach ($cells as $cell) {
                $this->add_cell($cell);
            }
        }
    }

    /**
     * @param TNP_Composer_Grid_Cell $cell
     */
    public function add_cell($cell) {
        $this->cells[] = $cell;
    }

    public function render() {
        $rendered_cells = '';
        $column_percentage_width = round(100 / $this->cells_count(), 0, PHP_ROUND_HALF_DOWN) . '%';
        foreach ($this->cells as $cell) {
            $rendered_cells .= $cell->render(['width' => $column_percentage_width]);
        }

        $row_template = $this->get_template();

        return str_replace('TNP_ROW_CONTENT_PH', $rendered_cells, $row_template);
    }

    private function cells_count() {
        return count($this->cells);
    }

    private function get_template() {
        return "<table border='0' cellpadding='0' cellspacing='0' width='100%'><tbody><tr><td>TNP_ROW_CONTENT_PH</td></tr></tbody></table>";
    }
}

/**
 * Class TNP_Composer_Grid_Cell
 */
class TNP_Composer_Grid_Cell {

    /**
     * @var string
     */
    private $content;

    /**
     * @var array
     */
    public $args;

    public function __construct($content = null, $args = []) {
        $default_args = [
            'width' => '100%',
            'class' => '',
            'align' => 'left',
            'valign' => 'top'
        ];

        $this->args = array_merge($default_args, $args);

        $this->content = $content ? $content : '';
    }

    public function add_content($content) {
        $this->content .= $content;
    }

    public function render($args) {
        $this->args = array_merge($this->args, $args);

        $column_template = $this->get_template();
        $column = str_replace(
                [
                    'TNP_ALIGN_PH',
                    'TNP_VALIGN_PH',
                    'TNP_WIDTH_PH',
                    'TNP_CLASS_PH',
                    'TNP_COLUMN_CONTENT_PH'
                ], [
            $this->args['align'],
            $this->args['valign'],
            $this->args['width'],
            $this->args['class'],
            $this->content
                ], $column_template);

        return $column;
    }

    private function get_template() {
        return "<table border='0' cellpadding='0' cellspacing='0' width='TNP_WIDTH_PH' align='left' style='table-layout: fixed;' class='responsive'>
                    <tbody>
                            <tr>
                                <td border='0' style='padding: 20px 10px 40px;' align='TNP_ALIGN_PH' valign='TNP_VALIGN_PH' class='TNP_CLASS_PH'>
                                    TNP_COLUMN_CONTENT_PH
                                </td>
                            </tr>
                    </tbody>
                </table>";
    }
}
