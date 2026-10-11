<?php

defined('ABSPATH') || exit;

/** @var NewsletterSubscriptionAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */
/** @var string $language */
if (!$controls->is_action()) {
    $controls->data = $this->get_options('customfields', $language);
} else {
    if ($controls->is_action('save')) {

        // Processing profile fields
        if ($language) {
            for ($i = 0; $i <= NEWSLETTER_PROFILE_MAX; $i++) {
                if (empty($controls->data['profile_' . $i])) {
                    unset($controls->data['profile_' . $i]);
                }
                if (empty($controls->data['profile_' . $i . '_options'])) {
                    unset($controls->data['profile_' . $i . '_options']);
                }
                if (empty($controls->data['profile_' . $i . '_placeholder'])) {
                    unset($controls->data['profile_' . $i . '_placeholder']);
                }
            }
        } else {
            for ($i = 0; $i <= NEWSLETTER_PROFILE_MAX; $i++) {
                if (empty($controls->data['profile_' . $i]) && $controls->data['profile_' . $i . '_placeholder']) {
                    unset($controls->data['profile_' . $i]);
                    unset($controls->data['profile_' . $i . '_options']);
                    unset($controls->data['profile_' . $i . '_type']);
                    unset($controls->data['profile_' . $i . '_status']);
                    unset($controls->data['profile_' . $i . '_rules']);
                    unset($controls->data['profile_' . $i . '_placeholder']);
                }
            }
        }
        $controls->data = wp_kses_post_deep($controls->data);
        $this->save_options($controls->data, 'customfields', $language);
        $controls->data = $this->get_options('customfields', $language);
        $controls->add_toast_saved();
    }
}

$status = [0 => __('Private', 'newsletter'), 1 => __('Public', 'newsletter')];
$rules = [0 => __('Optional', 'newsletter'), 1 => __('Required', 'newsletter')];
$extra_type = ['text' => __('Text', 'newsletter'), 'select' => __('List', 'newsletter')];

$main_options = $this->get_main_options('customfields');
?>

<div class="wrap" id="tnp-wrap">

    <?php include NEWSLETTER_ADMIN_HEADER; ?>

    <div id="tnp-heading">
        <ul class="tnp-nav">
            <li class="tnp-nav-title"><?php esc_html_e('Custom fields', 'newsletter') ?></li>
        </ul>
    </div>

    <div id="tnp-body">

        <?php $controls->show(); ?>

        <form action="" method="post">
            <?php $controls->init(); ?>

            <p>
                Change the <a href="?page=newsletter_subscription_form">Subscription Form</a> and the
                <a href="?page=newsletter_profile_index">Profile Page</a> selecting the fields to show.</p>
            </p>

            <div id="tabs">

                <ul>
                    <li><a href="#tabs-fields"><?php esc_html_e('Fields', 'newsletter') ?></a></li>

                    <?php if (NEWSLETTER_DEBUG) { ?>
                        <li><a href="#tabs-debug">Debug</a></li>
                    <?php } ?>
                </ul>

                <div id="tabs-fields">

                    <?php $this->language_notice(); ?>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Field', 'newsletter') ?></th>
                                <th><?php esc_html_e('Name/Label', 'newsletter') ?></th>
                                <th><?php esc_html_e('Placeholder', 'newsletter') ?></th>

                                <?php if (!$language) { ?>
                                    <th><?php esc_html_e('Status', 'newsletter') ?></th>
                                    <th><?php esc_html_e('Type', 'newsletter') ?></th>
                                    <th><?php esc_html_e('Rule', 'newsletter') ?></th>
                                <?php } ?>
                                <th><?php esc_html_e('List values comma separated', 'newsletter') ?></th>
                            </tr>
                        </thead>
                        <?php for ($i = 1; $i <= NEWSLETTER_PROFILE_MAX; $i++) { ?>
                            <tr>
                                <td><?php echo $i; ?></td>
                                <td>
                                    <?php $placeholder = !$language ? '' : $main_options['profile_' . $i] ?? '' ?>
                                    <?php $controls->text('profile_' . $i, ['placeholder' => $placeholder]); ?>

                                </td>
                                <td>
                                    <?php $placeholder = !$language ? '' : $main_options['profile_' . $i . '_placeholder'] ?? '' ?>
                                    <?php $controls->text('profile_' . $i . '_placeholder', ['placeholder' => $placeholder]); ?>
                                </td>
                                <?php if (!$language) { ?>
                                    <td><?php $controls->select('profile_' . $i . '_status', $status); ?></td>
                                    <td><?php $controls->select('profile_' . $i . '_type', $extra_type); ?></td>
                                    <td><?php $controls->select('profile_' . $i . '_rules', $rules); ?></td>
                                <?php } ?>
                                <td>
                                    <?php $placeholder = !$language ? '' : $main_options['profile_' . $i . '_options'] ?? '' ?>
                                    <?php $controls->textarea('profile_' . $i . '_options', ['width' => '200px', 'height' => '50px', 'placeholder' => $placeholder]); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>

                </div>

                <?php if (NEWSLETTER_DEBUG) { ?>
                    <div id="tabs-debug">
                        <pre><?php echo esc_html(json_encode($this->get_db_options('customfields', $language), JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                <?php } ?>



            </div>

            <p>
                <?php $controls->button_save(); ?>
            </p>

        </form>


    </div>

    <?php include NEWSLETTER_ADMIN_FOOTER; ?>

</div>
