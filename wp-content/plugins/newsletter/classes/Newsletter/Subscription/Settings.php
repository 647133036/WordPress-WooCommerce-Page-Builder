<?php

/**
 * @property string $optin
 *
 * @property int $welcome_page_id
 * @property string $welcome_page_url
 * @property string $welcome_page_text
 *
 * @property int $welcome_email
 * @property int $welcome_email_id
 * @property string $welcome_email_subject
 * @property string $welcome_email_body
 *
 *
 *
 * @property int $confirmation_page_id
 * @property string $confirmation_page_url
 * @property string $confirmation_page_text
 *
 * @property int $confirmation_email_type
 * @property int $confirmation_email_id
 * @property string $confirmation_email_subject
 * @property string $confirmation_email_body
 *
 * @property int $notify
 * @property string $notify_email
 *
 */
class Settings {

    var $data = [];
    /** @var NewsletterSubscription */
    var $module = null;

    function __construct($module) {
        $this->module = $module;
    }

    function __get($name) {
        switch ($name) {
            case 'optin':
                return ($this->data['noconfirmation'] ?? 1) ? 'single' : 'double';

            case 'welcome_email_body':
                return $this->module->get_text('confirmed_message');

                return ($this->data['noconfirmation'] ?? 1) ? 'single' : 'double';

        }
        return $this->data[$name] ?? null;
    }
}
