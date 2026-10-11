<?php
/** @var NewsletterEmailsAdmin $this */
/** @var NewsletterControls $controls */
/** @var NewsletterLogger $logger */

defined('ABSPATH') || exit;

wp_enqueue_style('tnpc-newsletter-style', home_url('/') . '?na=emails-composer-css');

include NEWSLETTER_INCLUDES_DIR . '/codemirror.php';

$email = null;
$id = (int)($_GET['id'] ?? 0);

if ($controls->is_action()) {

    if ($controls->is_action('reset')) {
        $redirect = $this->get_admin_page_url('composer');
        if (isset($_GET['id'])) {
            $redirect = $this->add_qs($redirect, 'id=' . ((int) $_GET['id']));
        }
        $controls->js_redirect($redirect);
    }


    if (!$id) {

        $logger->info('Saving new newsletter from composer');

        // Create a new email
        $email = new stdClass();
        $email->status = TNP_Email::STATUS_DRAFT;
        $email->track = Newsletter::instance()->get_option('track');
        $email->token = $this->get_token();
        $email->message_text = NewsletterModuleBase::get_email_default_text_part();
        $email->editor = NewsletterEmails::EDITOR_COMPOSER;
        $email->type = 'message';
        $email->send_on = time();
        $email->query = "select * from " . NEWSLETTER_USERS_TABLE . " where status='C'";

        NewsletterComposer::instance()->update_email($email, $controls);

        $email = $this->save_email($email);

        if ($controls->is_action('preview')) {
            $controls->js_redirect('?page=newsletter_emails_edit&id=' . $email->id);
        } else {
            $controls->js_redirect('?page=newsletter_emails_composer&id=' . $email->id);
        }
    } else {

        $email = $this->get_email($id);

        if ($email->status == TNP_Email::STATUS_SENDING) {
            $controls->errors = 'The newsletter is "sending" cannot be modified.';
        } elseif ($email->updated != $controls->data['updated']) {
            $controls->errors = 'This newsletter has been modified by someone else, cannot save. Do you have another tab editing this newsletter?';
        } else {
            NewsletterComposer::instance()->update_email($email, $controls);

            if (empty($email->options['text_message_mode'])) {
                $text = TNP_Composer::convert_to_text($email->message);
                if ($text) {
                    $email->message_text = TNP_Composer::convert_to_text($email->message);
                }
            }

            $email->updated = time();

            $email = $this->save_email($email);

            Newsletter\Logs::add('newsletter-version-' . $email->id, date('Y-m-d H:i:s'), 0, $email->message);

            if (is_wp_error($email)) {
                $controls->errors = $email->get_error_message();
            } else {
                NewsletterComposer::instance()->update_controls($controls, $email);
                if ($controls->is_action('save')) {
                    $controls->add_toast_saved();
                }
            }
        }
    }

    if ($controls->is_action('preview')) {
        $controls->js_redirect('?page=newsletter_emails_edit&id=' . $email->id);
    }

} else {

    if ($id) {
        $email = NewsletterAdmin::instance()->get_email($id);
        if ($email && $email->status == TNP_Email::STATUS_SENDING) {
            die('That newsletter is on sending, cannot be edited');
        }
    }
    NewsletterComposer::instance()->update_controls($controls, $email);
}
?>

<style>
    .tnp-composer-footer {
        background-color: #0073aa;
        border-radius: 3px !important;
        margin: 15px 0px 10px 0;
        padding: 10px;
        font-size: 15px;
        color: #fff !important;
        line-height: 32px;
    }

    .tnp-composer-footer form {
        display: inline-block;
        /*margin-left: 30px;*/
    }

    #wpfooter {
        display: none;
    }
</style>



<div class="wrap tnp-emails-composer" id="tnp-wrap">

    <div id="tnp-body" style="display: flex; flex-direction: column">

        <?php $controls->show() ?>

        <form method="post" action="" id="tnpc-form" style="margin-top: 1rem">
            <?php $controls->init(); ?>

            <?php $controls->button_confirm_secondary('reset', __('Back to last save', 'newsletter'), 'Are you sure?'); ?>
            <?php $controls->button('save', __('Save', 'newsletter')); ?>
            <?php $controls->button('preview', __('Next', 'newsletter') . ' &raquo;'); ?>


            <div>
                <?php $controls->composer_v3(true, true); ?>
            </div>

        </form>


    </div>
</div>