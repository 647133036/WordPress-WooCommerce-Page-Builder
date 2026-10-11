<?php

defined('ABSPATH') || exit;

function tnp_register_block($dir) {
    return NewsletterComposer::instance()->register_block($dir);
}

function tnp_register_template($dir) {
    return NewsletterComposer::instance()->register_template($dir);
}

class NewsletterComposer extends NewsletterModule {

    static $instance;
    static $block_dirs = [];
    static $template_dirs = [];
    var $blocks = null;
    var $templates = null;
    static $default_templates = ['blank', 'posts-1', 'posts-2', 'zen', 'valentine', 'event', 'invite-1', 'sale-1', 'black-friday', 'black-friday-2', 'welcome-1', 'halloween', 'confirmation-1', 'welcome-2'];
//    static $old_default_templates = ['announcement', 'cta', 'invite', 'posts', 'product', 'simple', 'tour'];

    const OUTLOOK_START_IF = '<!--[if mso | IE]>';
    const OUTLOOK_END_IF = '<![endif]-->';

    /**
     * @return NewsletterComposer
     */
    static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    function __construct() {
        parent::__construct('composer');
    }

    /**
     * Checks if a file exists on provided filder and return the normalized folder or WP_Error.
     * Logs errors.
     *
     * @param string $dir
     * @param string $file
     * @return string|\WP_Error
     */
    static function check_dir($dir, $file) {
        $dir = realpath($dir);
        if (!$dir) {
            $error = new WP_Error('1', 'Seems not a valid path: ' . $dir);
            self::instance()->logger->error($error);
            return $error;
        }
        $dir = wp_normalize_path($dir);
        $dir = untrailingslashit($dir);
        if (!file_exists($dir . '/' . $file)) {
            $error = new WP_Error('1', $file . ' missing on folder ' . $dir);
            self::instance()->logger->error($error);
            return $error;
        }

        return $dir;
    }

    static function register_block($dir) {
        //self::instance()->logger->info('Registering block ' . $dir);
        $dir = self::check_dir($dir, 'block.php');
        if (is_wp_error($dir)) {
            return $dir;
        } else {
            self::$block_dirs[] = $dir;
            return true;
        }
    }

    static function register_template($dir) {
        //self::instance()->logger->info('Registering template ' . $dir);
        $dir = self::check_dir($dir, 'template.json');
        if (is_wp_error($dir)) {
            return $dir;
        } else {
            self::$template_dirs[] = $dir;
            return true;
        }
    }

    /**
     * Encodes an array of options to be inserted in the block HMTL.
     *
     * @param array $options
     * @return string
     */
    static function options_encode($options) {
        return base64_encode(json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP));
    }

    /**
     * Decodes a string representing a set of encoded options of a block.
     * For compatibility tries different kinds of decoding.
     *
     * @param string $options
     * @return array
     */
    static function options_decode($options) {
        // Old "query string" format
        if (is_string($options) && strpos($options, 'options[') !== false) {
            $opts = [];
            parse_str($options, $opts);
            $options = $opts['options'];
        }

        if (is_array($options)) {
            return $options;
        }

        // Json data should be base64 encoded, but for short time it wasn't
        $tmp = json_decode($options, true);
        if (is_null($tmp)) {
            return json_decode(base64_decode($options), true);
        } else {
            return $tmp;
        }
    }

    /**
     * Return a single block (associative array) checking for legacy ID as well.
     *
     * @param string $id
     * @return array
     */
    function get_block($id) {
        $blocks = $this->get_blocks();
        return $blocks[$id] ?? null;
    }

    /**
     * Array of arrays with every registered block and legacy block converted to the new
     * format.
     *
     * @return array
     */
    function get_blocks() {

        if (!is_null($this->blocks)) {
            return $this->blocks;
        }

        $this->blocks = $this->scan_blocks_dir(NEWSLETTER_DIR . '/emails/blocks');

        $extended = $this->scan_blocks_dir(WP_CONTENT_DIR . '/extensions/newsletter/blocks');

        $this->blocks = array_merge($extended, $this->blocks);

        // Old way to register a folder of blocks to be scanned
        $dirs = apply_filters('newsletter_blocks_dir', []);

        $this->logger->debug('Folders registered to be scanned for blocks:');
        $this->logger->debug($dirs);

        foreach ($dirs as $dir) {
            $list = $this->scan_blocks_dir($dir);
            $this->blocks = array_merge($list, $this->blocks);
        }

        do_action('newsletter_register_blocks');

        foreach (self::$block_dirs as $dir) {
            $block = $this->build_block($dir);
            if (is_wp_error($block)) {
                $this->logger->error($block);
                continue;
            }
            if (!isset($this->blocks[$block['id']])) {
                $this->blocks[$block['id']] = $block;
            } else {
                $this->logger->error('The block "' . $block['id'] . '" has already been registered');
            }
        }

        $this->blocks = array_reverse($this->blocks);
        return $this->blocks;
    }

    function scan_blocks_dir($dir) {
        $dir = realpath($dir);
        if (!$dir) {
            return [];
        }
        $dir = wp_normalize_path($dir);

        $list = [];
        $handle = opendir($dir);
        while ($file = readdir($handle)) {
            if (substr($file, 0, 1) === '.') {
                continue;
            }

            $data = $this->build_block($dir . '/' . $file);

            if (is_wp_error($data)) {
                $this->logger->error($data);
                continue;
            }
            $list[$data['id']] = $data;
        }
        closedir($handle);
        return $list;
    }

    /**
     * Builds a block data structure starting from the folder containing the block
     * files.
     *
     * @param string $dir
     * @return array | WP_Error
     */
    function build_block($dir) {
        $dir = realpath($dir);
        $dir = wp_normalize_path($dir);
        $full_file = $dir . '/block.php';
        if (!is_file($full_file)) {
            return new WP_Error('1', 'Missing block.php file in ' . $dir);
        }

        $wp_content_dir = wp_normalize_path(realpath(WP_CONTENT_DIR));

        $relative_dir = substr($dir, strlen($wp_content_dir));
        $file = basename($dir);

        $data = get_file_data($full_file, ['name' => 'Name', 'section' => 'Section', 'description' => 'Description', 'type' => 'Type']);
        $defaults = ['section' => 'content', 'name' => ucfirst($file), 'descritpion' => '', 'icon' => plugins_url('newsletter') . '/admin/images/block-icon.png'];
        $data = array_merge($defaults, $data);

        if (is_file($dir . '/icon.png')) {
            $data['icon'] = content_url($relative_dir . '/icon.png');
        }

        $data['id'] = sanitize_key($file);

        // Absolute path of the block files
        $data['dir'] = $dir;
        $data['url'] = content_url($relative_dir);

        return $data;
    }

    /**
     * Buils the global email CSS merging the standard ones with all blocks' global
     * CSS.
     *
     * @return type
     */
    function get_composer_css($attrs = []) {
        $css = file_get_contents(__DIR__ . '/css/newsletter.css');

        $css .= "/* Custom CSS */\n";

        $css .= NewsletterEmails::instance()->get_main_option('css');

        $css .= "/* End Custom CSS */\n";

        $blocks = $this->get_blocks();
        foreach ($blocks as $block) {
            if (!file_exists($block['dir'] . '/style.css')) {
                continue;
            }
            $css .= "\n\n";
            $css .= "/* " . $block['name'] . " */\n";
            $css .= file_get_contents($block['dir'] . '/style.css');
        }

        $keys = array_map(
                function ($key) {
                    return 'var(--' . sanitize_key($key) . ')';
                },
                array_keys($attrs)
        );

        $css = str_replace($keys, $attrs, $css);

        return $css;
    }

    function get_composer_backend_css($attrs = []) {
        $css = file_get_contents(__DIR__ . '/css/backend.css');
        $css .= "\n\n";
        $css .= $this->get_composer_css();

        $keys = array_map(
                function ($key) {
                    return 'var(--' . sanitize_key($key) . ')';
                },
                array_keys($attrs)
        );

        $css = str_replace($keys, $attrs, $css);

        return $css;
    }

    /**
     * Creates a template object using the content of a template folder.
     *
     * @param string $dir
     * @return Newsletter\Composer\Template|\WP_Error
     */
    function build_template($dir) {
        $dir = self::check_dir($dir, '/template.json');
        if (is_wp_error($dir)) {
            return $dir;
        }

        $wp_content_dir = wp_normalize_path(realpath(WP_CONTENT_DIR));

        $relative_dir = substr($dir, strlen($wp_content_dir));

        $template_url = content_url($relative_dir);
        $json = file_get_contents($dir . '/template.json');
        $json = str_replace("{template_url}", $template_url, $json);
        $data = json_decode($json);
        if (!$data) {
            $error = new WP_Error('1', 'Unable to decode the template JSON in ' . $dir);
            $this->logger->error($error);
            return $error;
        }
        $data->dir = $dir;
        $data->icon = $template_url . "/icon.png?ver=2";
        $data->id = sanitize_key(basename($dir));
        $data->url = $template_url;
        if (empty($data->name)) {
            $data->name = $data->subject;
        }

        return $data;
    }

    /**
     * Returns all the available email templates.
     *
     * @return Newsletter\Composer\Template[]
     */
    function get_templates() {

        // Caching
        if (!is_null($this->templates)) {
            return $this->templates;
        }

        $this->templates = [];

        // Let addons, themes, plugins add their own templates
        do_action('newsletter_register_templates');

        // Builds the packaged templates folder list, they have priority since we don't
        // want them to be overriden.
        $default_dirs = array_map(function ($item) {
            return NEWSLETTER_DIR . '/emails/templates/' . $item;
        }, self::$default_templates);

        $dirs = array_merge(self::$template_dirs, $default_dirs);

        foreach ($dirs as $dir) {
            $template = $this->build_template($dir);
            if (is_wp_error($template)) {
                continue;
            }

            // Add the template only if the ID (folder name) is not already present
            if (!isset($this->templates[$template->id])) {
                $this->templates[$template->id] = $template;
            } else {
                $this->logger->error('The template "' . $template->id . '" has already been registered');
            }
        }

        // Old presets to be converted or deleted
//        foreach (self::$old_default_templates as $id) {
//
//            if (isset($this->templates[$id])) {
//                continue;
//            }
//            $file = NEWSLETTER_DIR . '/emails/presets/' . $id . '/preset.json';
//
//            $json = file_get_contents($file);
//            $json = str_replace("{placeholder_base_url}", plugins_url('newsletter') . '/emails/presets', $json);
//            $data = json_decode($json);
//            if (!$data) {
//                continue;
//            }
//
//            $data->id = $id;
//            $data->dir = NEWSLETTER_DIR . '/emails/presets/' . $id;
//            $data->icon = Newsletter::plugin_url() . "/emails/presets/$id/icon.png?ver=2";
//
//            $this->templates[$id] = $data;
//        }

        return $this->templates;
    }

    /**
     * Returns a single template by its ID (folder name)
     *
     * @param string $id
     * @return Newsletter\Composer\Template
     */
    function get_template($id) {
        $templates = $this->get_templates();
        return $templates[$id] ?? null;
    }

    static function extract_composer_options($email) {
        $composer = ['width' => 600];
        foreach ($email->options as $k => $v) {
            if (strpos($k, 'composer_') === 0) {
                $composer[substr($k, 9)] = $v;
            }
        }
        return $composer;
    }

    /**
     * Creates an email using the template stored in the provided folder. Asking for a folder
     * contraints to use a standard structure for the template avoiding wilde behaviors. :-)
     *
     * The returned email is not saved into the database!
     *
     * @param string $id Template identifier
     * @return \WP_Error|\TNP_Email
     */
    function build_email_from_template($id) {

        $template = $this->get_template($id);
        if (!$template) {
            return new WP_Error('missing', 'Template not found: ' . $id);
        }

        $email = new TNP_Email();
        $email->editor = TNP_Email::EDITOR_COMPOSER;
        $email->track = Newsletter::instance()->get_option('track');
        $email->type = $template->type ?? 'message';
        $content = '';

        // Manage the old format untile we get rid of it definitively
        $old = strpos($template->dir, '/emails/presets/') !== false;
        if (!$old) {
            foreach ($template->settings ?? [] as $k => $v) {
                $email->options['composer_' . $k] = $v;
            }
            $email->options['preheader'] = $template->snippet ?? '';
            $email->subject = $template->subject ?? '[missing subject]';

            $content = '';
            foreach ($template->blocks as $item) {
                $options = (array) $item;
                // Convert structured options to array (the json is decoded as "object")
                foreach ($options as &$o) {
                    if (is_object($o)) {
                        $o = (array) $o;
                    }
                }
                ob_start();
                $this->render_block($item->block_id, true, $options, [], (array) $template->settings);
                $content .= trim(ob_get_clean());
            }
        } else {

            $email->subject = $template->name ?? '[missing subject]';
            // Preset version 1 haven't global options
            $composer = [];
            $options = TNP_Composer::get_global_style_defaults();
            foreach ($options as $k => $v) {
                if (strpos($k, 'options_composer_') === 0) {
                    $email->options[substr($k, 8)] = $v;
                    $composer[substr($k, 17)] = $v;
                }
            }

            foreach ($template->blocks as $item) {
                ob_start();
                $this->render_block($item->block, true, (array) $item->options, [], $composer);
                $content .= trim(ob_get_clean());
            }
        }

        $content = str_replace('{blog_title}', html_entity_decode(get_bloginfo('name')), $content);
        $content = str_replace('{blog_description}', get_option('blogdescription'), $content);

        self::update_email_message($email, $content);

        $email->subject = str_replace('{blog_title}', html_entity_decode(get_bloginfo('name')), $email->subject);

        return $email;
    }

    /**
     * Renders a block identified by its id, using the block options and adding a wrapper
     * if required (for the first block rendering).
     *
     * @param string $block_id
     * @param boolean $wrapper
     * @param array $options
     * @param array $context
     * @param array $composer
     */
    function render_block($block_id = null, $wrapper = false, $options = [], $context = [], $composer = []) {
        static $kses_style_filter = false;
        include_once NEWSLETTER_INCLUDES_DIR . '/helper.php';

        $block = $this->get_block($block_id);

        // Block not found
        if (!$block) {
            if ($wrapper) {
                echo '<table border="0" cellpadding="0" cellspacing="0" align="center" width="100%" style="border-collapse: collapse; width: 100%;" class="tnpc-row tnpc-row-block" data-id="', esc_attr($block_id), '">';
                echo '<tr>';
                echo '<td bgcolor="#ffffff" align="center" style="padding: 0; font-family: Helvetica, Arial, sans-serif; mso-line-height-rule: exactly;" class="edit-block">';
            }

            echo '<p>Ops, this block type is not avalable.</p>';

            if ($wrapper) {
                echo '</td></tr></table>';
            }
            return;
        }

        if (!is_array($options)) {
            $options = [];
        }

        // Include the defaults.php block file that should define the $defaults array
        $defaults_file = $block['dir'] . '/defaults.php';
        if (file_exists($defaults_file)) {
            include $defaults_file;
        }

        if (!isset($defaults) || !is_array($defaults)) {
            $defaults = [];
        }

        // On block first creation we still do not have the defaults... this is a problem we need to address in a new
        // composer version
        $common_defaults = array(
            'block_background' => '',
            'block_background_2' => '',
            'block_width' => $composer['width'],
            'block_align' => 'center',
            'block_border_color' => '',
            'block_border_radius' => '0',
        );

        $options = array_merge($common_defaults, $defaults, $options);

        //Remove 'options_composer_' prefix
        $composer_defaults = ['width' => 600];
        foreach (TNP_Composer::get_global_style_defaults() as $global_option_name => $global_option) {
            $composer_defaults[str_replace('options_composer_', '', $global_option_name)] = $global_option;
        }
        $composer = array_merge($composer_defaults, $composer);
        $composer['width'] = (int) $composer['width'];
        if (empty($composer['width'])) {
            $composer['width'] = 600;
        }

        $block_padding_right = empty($options['block_padding_right']) ? 0 : intval($options['block_padding_right']);
        $block_padding_left = empty($options['block_padding_left']) ? 0 : intval($options['block_padding_left']);

        $composer['content_width'] = $composer['width'] - $block_padding_left - $block_padding_right;

        $width = $composer['width'];
        $font_family = 'Helvetica, Arial, sans-serif';

        $global_title_font_family = $composer['title_font_family'];
        $global_title_font_size = $composer['title_font_size'];
        $global_title_font_color = $composer['title_font_color'];
        $global_title_font_weight = $composer['title_font_weight'];

        $global_text_font_family = $composer['text_font_family'];
        $global_text_font_size = $composer['text_font_size'];
        $global_text_font_color = $composer['text_font_color'];
        $global_text_font_weight = $composer['text_font_weight'];

        $global_button_font_family = $composer['button_font_family'];
        $global_button_font_size = $composer['button_font_size'];
        $global_button_font_color = $composer['button_font_color'];
        $global_button_font_weight = $composer['button_font_weight'];
        $global_button_background_color = $composer['button_background_color'] ?? '#000';

        $global_block_background = sanitize_hex_color($composer['block_background']);

        $info = Newsletter::instance()->get_options('info');

        // This code filters the HTML to remove javascript and unsecure attributes and enable the
        // "display" rule for CSS which is needed in blocks to force specific "block" or "inline" or "table".
        add_filter('safe_style_css', [$this, 'hook_safe_style_css'], 9999);
        //if (!Newsletter::instance()->is_html_allowed()) {
            $options = wp_kses_post_deep($options);
        //}
        remove_filter('safe_style_css', [$this, 'hook_safe_style_css']);

        if (!isset($context['type'])) {
            $context['type'] = '';
        }



        $out = ['subject' => '', 'return_empty_message' => false, 'stop' => false, 'skip' => false];

        $dir = is_rtl() ? 'rtl' : 'ltr';
        $align_left = is_rtl() ? 'right' : 'left';
        $align_right = is_rtl() ? 'left' : 'right';

        ob_start();
        $logger = $this->logger;
        include $block['dir'] . '/block.php';
        $content = trim(ob_get_clean());

        $content = NewsletterEmails::instance()->inline_css($content, true);

        // CSS driven by the block
        // Requited for the server side parsing and rendering
        $options['block_id'] = $block_id;

        // Fixes missing defaults by some old blocks
        $options = array_merge([
            'block_padding_top' => '0',
            'block_padding_bottom' => '0',
            'block_padding_right' => '0',
            'block_padding_left' => '0'
                ], $options);

        $options['block_padding_top'] = (int) str_replace('px', '', $options['block_padding_top']);
        $options['block_padding_bottom'] = (int) str_replace('px', '', $options['block_padding_bottom']);
        $options['block_padding_right'] = (int) str_replace('px', '', $options['block_padding_right']);
        $options['block_padding_left'] = (int) str_replace('px', '', $options['block_padding_left']);

        $block_background = empty($options['block_background']) ? $global_block_background : sanitize_hex_color($options['block_background']);

        // Internal TD wrapper
        $style = 'text-align: center; ';
        //$style .= 'width: 100% !important; ';
        $style .= 'line-height: normal !important; ';
        $style .= 'letter-spacing: normal; ';
        $style .= 'mso-line-height-rule: exactly; outline: none; ';
        $style .= 'padding: ' . $options['block_padding_top'] . 'px ' . $options['block_padding_right'] . 'px ' . $options['block_padding_bottom'] . 'px ' .
                $options['block_padding_left'] . 'px;';

        if (!empty($options['block_border_color'])) {
            $style .= 'border: 1px solid ' . sanitize_hex_color($options['block_border_color']) . ';';
        }

        if (!empty($options['block_border_radius'])) {
            $style .= 'border-collapse: separate !important;border-radius: ' . ((int) $options['block_border_radius']) . 'px;';
        }

        $background_style = '';

        if (!empty($block_background)) {
            $background_style .= 'background-color: ' . $block_background . ';';
        }

        if (!empty($options['block_background_2'])) {
            $options['block_background_2'] = sanitize_hex_color($options['block_background_2']);
            $angle = (int) ($options['block_background_angle'] ?? 180);
            $background_style .= 'background: linear-gradient(' . $angle . 'deg, ' . $block_background . ' 0%, ' . $options['block_background_2'] . '  100%);';
        }

        $block_class = $options['block_class'] ?? '';

        $data = $this->options_encode($options);
        // First time block creation wrapper
        if ($wrapper) {
            echo '<table role="presentation" border="0" cellpadding="0" cellspacing="0" align="center" width="100%" style="border-collapse: collapse; width: 100%;" class="tnpc-row tnpc-row-block" data-id="', esc_attr($block_id), '">', "\n";
            echo "<tr>";
            echo '<td align="center" style="padding: 0;" class="edit-block">', "\n";
        }


        // Container to make the background color 100% wide
        if (!empty($options['block_background_wide'])) {
            echo '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate !important; width: 100%!important;">', "\n";
            echo "<tr>";
            echo '<td align="center" width="100%" style="width: 100%; ', esc_attr($background_style), '" bgcolor="', esc_attr($block_background), '">';
            $block_background = '';
        } else {
            // Applied to the internal container
            $style .= $background_style;
        }

        // Container that fixes the width and makes the block responsive

        echo $this->get_outlook_wrapper_open($composer['width']);
        echo '<table role="presentation" type="options" data-json="', esc_attr($data), '" class="tnpc-block-content" border="0" cellpadding="0" align="center" cellspacing="0" width="100%" style="border-collapse: separate !important; width: 100%!important; max-width: ', $composer['width'], 'px!important">', "\n";

        echo "<tr>";
        //echo '<td align="', esc_attr($options['block_align']), '" style="', esc_attr($style), '" bgcolor="', esc_attr($block_background), '" width="100%">';
        echo '<td align="', esc_attr($options['block_align']), '" style="', esc_attr($style), '" bgcolor="', esc_attr($block_background), '" class="', esc_attr($block_class), '">';

        //echo "<!-- block generated content -->\n";
        echo trim($content);
        //echo "\n<!-- /block generated content -->\n";

        echo "</td></tr></table>";

        echo $this->get_outlook_wrapper_close();

        if (!empty($options['block_background_wide'])) {
            echo "</td></tr></table>";
        }
        // First time block creation wrapper
        if ($wrapper) {
            echo "</td></tr></table>";
        }

        return $out;
    }

    /**
     * Filter to enable the "display" attribute on CSS filterred by wp_kses_post_deep used
     * when rendering a block.
     *
     * Used by render_block() and sanitize_html().
     *
     * @param array $rules
     * @return string
     */
    static function hook_safe_style_css($rules) {
        $rules[] = 'display';
        $rules[] = 'mso-*';
        $rules[] = 'background';
        $rules[] = 'background-*';
        return $rules;
    }

    static function sanitize_html($html) {
        if (Newsletter::instance()->is_html_allowed()) {
            return $html;
        }

        $html = str_replace([self::OUTLOOK_START_IF, self::OUTLOOK_END_IF], ['###OUTLOOK_START_IF###', '###OUTLOOK_END_IF###'], $html);

        add_filter('safe_style_css', ['NewsletterComposer', 'hook_safe_style_css'], 9999);
        $html = wp_kses_post($html);
        remove_filter('safe_style_css', ['NewsletterComposer', 'hook_safe_style_css']);

        $html = str_replace(['###OUTLOOK_START_IF###', '###OUTLOOK_END_IF###'], [self::OUTLOOK_START_IF, self::OUTLOOK_END_IF], $html);

        return $html;
    }

    static function get_outlook_wrapper_open($width = 600) {
        $width = (int) $width;
        return self::OUTLOOK_START_IF . '<table role="presentation" border="0" cellpadding="0" align="center" cellspacing="0" width="' . $width . '"><tr><td width="' . $width . '" style="vertical-align:top;width:' . $width . 'px;">' . self::OUTLOOK_END_IF;
    }

    static function get_outlook_wrapper_close() {
        return self::OUTLOOK_START_IF . '</td></tr></table>' . self::OUTLOOK_END_IF;
    }

    /**
     *
     * @param TNP_Email $email
     */
    function to_json($email) {
        $data = ['version' => 2];
        $data['subject'] = $email->subject;
        $data['snippet'] = $email->options['preheader'];
        $data['type'] = $email->type;
        $data['name'] = $email->type;

        $data['settings'] = $this->extract_composer_options($email);

        preg_match_all('/data-json="(.*?)"/m', $email->message, $matches, PREG_PATTERN_ORDER);

        $data['blocks'] = [];
        foreach ($matches[1] as $match) {
            $a = html_entity_decode($match, ENT_QUOTES, 'UTF-8');
            $data['blocks'][] = self::options_decode($a);
        }
        echo wp_json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Regenerates a saved email refreshing each block. Regeneration is
     * conditioned by the context. The context is usually passed to blocks
     * so they can act in the right manner.
     *
     * $context contains a type and, for automated, the last_run.
     *
     * @param TNP_Email $email
     * @return WP_Error
     */
    function regenerate($email, $context = [], $wp_error = false) {

        $this->logger->debug('Regenerating email ' . $email->id);

        $context = array_merge(['last_run' => 0, 'type' => ''], $context);

        $this->logger->debug($context);

        $composer = $this->extract_composer_options($email);

        $result = $this->regenerate_blocks($email->message, $context, $composer, $wp_error);

        // One block is signalling the email should not be regenerated (usually from Automated)
        if (is_wp_error($result)) {
            $this->logger->debug($result);
            return $result;
        }

        if ($result === false) {
            $this->logger->debug('A block stopped the regeneration');
            return false;
        }

        self::update_email_message($email, $result['content']);


        if ($context['type'] === 'automated') {
            if (!empty($result['subject'])) {
                $email->subject = $result['subject'];
            }
        }

        $this->logger->debug('Regeneration completed');

        return true;
    }

    /**
     * Regenerates all blocks found in the content (email body) and return the new content (without the
     * HTML wrap)
     *
     * @param string $content
     * @param array $context
     * @param array $composer
     * @return array content and subject or false
     */
    function regenerate_blocks($content, $context = [], $composer = [], $wp_error = false) {
        $this->logger->debug('Blocks regeneration started');

        $result = ['content' => '', 'subject' => ''];

        if (empty($content)) {
            return $result;
        }

        preg_match_all('/data-json="(.*?)"/m', $content, $matches, PREG_PATTERN_ORDER);

        $this->logger->debug('Found ' . count($matches[1]) . ' blocks');

        // Compatibility
        $width = $composer['width'];

        $count = 0;
        foreach ($matches[1] as $match) {
            $count++;

            $a = html_entity_decode($match, ENT_QUOTES, 'UTF-8');
            $options = $this->options_decode($a);

            $this->logger->debug('Regenerating block ' . $options['block_id']);

            $block = $this->get_block($options['block_id']);
            if (!$block) {
                $this->logger->debug('Unable to load the block ' . $options['block_id']);
                continue;
            }

            ob_start();
            $out = $this->render_block($options['block_id'], true, $options, $context, $composer);
            $block_html = ob_get_clean();

            if (is_array($out)) {
                if ($out['return_empty_message'] || $out['stop']) {
                    $this->logger->debug('The block ' . $count . ' stopped the regeneration');
                    if ($wp_error) {
                        return new WP_Error('stop', 'The block number ' . $count . ' stopped the generation');
                    }
                    return false;
                }
                if ($out['skip']) {
                    $this->logger->debug('The block indicated to skip it');
                    continue;
                }
                if (empty($result['subject']) && !empty($out['subject'])) {
                    $this->logger->debug('The block suggested the subject: ' . $out['subject']);
                    $result['subject'] = wp_strip_all_tags($out['subject']);
                }
            }

            $result['content'] .= $block_html;
        }

        $this->logger->debug('Blocks regeneration completed');

        return $result;
    }

    static function extract_body($email) {

        // Deprecated, old messages without a marker
        if (strpos($email->message, '<!-- tnp -->') === false) {
            return $email->message;
        }

        $pattern = sprintf('/%s(.*?)%s/s', '<!-- tnp -->', '<!-- \\/tnp -->');

        $matches = [];
        preg_match($pattern, $email->message, $matches);

        // Required since esc_html DOES NOT escape the HTML entities (apparently)
        //$body = str_replace(['&', '"', '<', '>'], ['&amp;', '&quot;', '&lt;', '&gt;'], $matches[1]);

        return $matches[1];
    }

    /**
     *
     * @param NewsletterControls $controls
     * @param TNP_Email $email
     */
    static function update_controls($controls, $email = null) {

        // Controls for a new email (which actually does not exist yet
        if (!empty($email)) {

            foreach ($email->options as $name => $value) {
                $controls->data['options_' . $name] = $value;
            }

            $controls->data['message'] = self::extract_body($email);
            $controls->data['subject'] = $email->subject;
            $controls->data['updated'] = $email->updated;

            // Extra data used when testing
            $controls->data['track'] = $email->track;
            $controls->data['email_id'] = $email->id;

        }

        if (!empty($email->options['sender_email'])) {
            $controls->data['sender_email'] = $email->options['sender_email'];
        } else {
            $controls->data['sender_email'] = '';
        }

        if (!empty($email->options['sender_name'])) {
            $controls->data['sender_name'] = $email->options['sender_name'];
        } else {
            $controls->data['sender_name'] = '';
        }

        $controls->data = array_merge(TNP_Composer::get_global_style_defaults(), $controls->data);
    }

    /**
     * Update an email using the data captured by the NewsletterControl object
     * processing the composer fields.
     *
     * Updates only the options, message and subject.
     *
     * @param TNP_Email $email
     * @param NewsletterControls $controls
     */
    static function update_email($email, $controls) {
        if (isset($controls->data['subject'])) {
            $email->subject = wp_strip_all_tags($controls->data['subject']);
        }

        // They should be only composer options
        foreach ($controls->data as $name => $value) {
            if (strpos($name, 'options_') === 0) {
                $email->options[substr($name, 8)] = wp_strip_all_tags($value);
            }
        }

        $email->editor = NewsletterEmails::EDITOR_COMPOSER;

        $message = self::sanitize_html($controls->data['message']);

        self::update_email_message($email, $message);
    }

    /**
     * Sources:
     * - https://webdesign.tutsplus.com/tutorials/creating-a-future-proof-responsive-email-without-media-queries--cms-23919
     *
     * @param TNP_Email $email
     * @return string
     */
    static function get_html_open($email) {

        $css_attrs = [
            'body_padding_left' => ($email->options['composer_padding'] ?? '0') . 'px',
            'body_padding_right' => ($email->options['composer_padding'] ?? '0') . 'px',
            'body_padding_bottom' => ($email->options['composer_padding'] ?? '0') . 'px',
            'body_padding_top' => ($email->options['composer_padding'] ?? '0') . 'px',
        ];

        $open = '<!DOCTYPE html>' . "\n";
        $open .= '<html xmlns="https://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">' . "\n";
        $open .= '<head>' . "\n";
        $open .= '<title>{email_subject}</title>' . "\n";
        $open .= '<meta charset="utf-8">' . "\n";
        $open .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        $open .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . "\n";
        $open .= '<meta name="format-detection" content="address=no">' . "\n";
        $open .= '<meta name="format-detection" content="telephone=no">' . "\n";
        $open .= '<meta name="format-detection" content="email=no">' . "\n";
        $open .= '<meta name="x-apple-disable-message-reformatting">' . "\n";
        $open .= '<!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->' . "\n";
        $open .= '<style type="text/css">' . "\n";
        $open .= NewsletterComposer::instance()->get_composer_css($css_attrs);
        $open .= "\n</style>\n";
        $open .= "</head>\n";

        $open .= '<body style="margin: 0; padding: 0; line-height: normal; word-spacing: normal;" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '">';
        $open .= "\n";

        $open .= self::get_html_preheader($email);

        return $open;
    }

    /**
     *
     * @param TNP_Email $email
     * @return string
     */
    static function get_main_wrapper_open($email) {
        if (!isset($email->options['composer_background']) || $email->options['composer_background'] == 'inherit') {
            $bgcolor = '';
        } else {
            $bgcolor = $email->options['composer_background'];
        }

        return "\n<table cellpadding='0' cellspacing='0' border='0' width='100%' role='presentation'>\n" .
                "<tr>\n" .
                "<td bgcolor='$bgcolor' valign='top' class='wrapper'><!-- tnp -->";
    }

    static private function get_html_preheader($email) {

        if (empty($email->options['preheader'])) {
            return "";
        }

        $preheader_text = esc_html($email->options['preheader']);
        $html = "<div style=\"display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;\">$preheader_text</div>";
        $html .= "\n";

        return $html;
    }

    /**
     *
     * @param TNP_Email $email
     * @return string
     */
    static function get_main_wrapper_close($email) {
        return "\n<!-- /tnp -->\n" .
                "</td>\n" .
                "</tr>\n" .
                "</table>\n\n";
    }

    static function get_html_close($email) {
        return "</body>\n</html>";
    }

    static function update_email_message($email, $content) {
        $email->message = self::get_html_open($email) . self::get_main_wrapper_open($email) .
                $content . self::get_main_wrapper_close($email) . self::get_html_close($email);
    }
}
