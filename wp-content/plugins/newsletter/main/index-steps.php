<?php if (current_user_can('administrator')) { ?>

    <div class="tnp-cards-container">


        <div class="tnp-card">

            <div class="tnp-step sender <?php echo!empty($steps['sender']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Your sender name and address', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('From who your subscribers will see the emails coming from?', 'newsletter'); ?>

                        <a href="?page=newsletter_main_main">Review</a>
                    </p>
                </div>
            </div>

            <div class="tnp-step forms <?php echo!empty($steps['forms']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Subscription: popup and inline forms', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Activate the subscription forms to grow your subscriber list.', 'newsletter'); ?>

                        <a href="?page=newsletter_subscription_sources"><?php esc_html_e('Configure', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>

            <div class="tnp-step <?php echo!empty($steps['notification']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Be notified when someone subscribes', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Activate the notification when you get a new subscriber.', 'newsletter'); ?>

                        <a href="?page=newsletter_subscription_options#advanced"><?php esc_html_e('Configure', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>

            <div class="tnp-step welcome-email <?php echo!empty($steps['welcome-email']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Welcome email: give it your style', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Customize the welcome email to reflect your style.', 'newsletter'); ?>
                        <a href="?page=newsletter_subscription_welcome"><?php esc_html_e('Review', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>



            <div class="tnp-step addons-manager <?php echo!empty($steps['addons-manager']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Get a free license', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('And install free addons to get more power.', 'newsletter'); ?>

                        <a href="?page=newsletter_main_extensions"><?php esc_html_e('Get it', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>
        </div>


        <div class="tnp-card">


            <div class="tnp-step test-email <?php echo!empty($steps['test-email']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Test the email delivery', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Check if your blog can deliver emails.', 'newsletter'); ?>

                        <a href="?page=newsletter_system_delivery"><?php esc_html_e('Run a test', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>

            <div class="tnp-step company <?php echo!empty($steps['company']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Your company info and socials', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Review your company info and socials', 'newsletter'); ?>

                        <a href="?page=newsletter_main_info"><?php esc_html_e('Review', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>

            <div class="tnp-step first-newsletter <?php echo!empty($steps['first-newsletter']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Create your first newsletter', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Explore the composer and send it.', 'newsletter'); ?>

                        <a href="?page=newsletter_emails_index"><?php esc_html_e('Go create', 'newsletter'); ?></a>.
                    </p>
                </div>
            </div>

            <div class="tnp-step <?php echo!empty($steps['delivery-speed']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Change the delivery speed', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Set how many emails per hour you want to send.', 'newsletter'); ?>

                        <a href="?page=newsletter_main_main"><?php esc_html_e('Review', 'newsletter'); ?></a>
                    </p>
                </div>
            </div>

            <div class="tnp-step <?php echo!empty($steps['automated']) ? 'ok' : ''; ?>">
                <div>
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h3><?php esc_html_e('Explore the Automated Newsletters', 'newsletter'); ?></h3>
                    <p>
                        <?php esc_html_e('Everything on autopilot: set the direction and relax', 'newsletter'); ?>

                        <a href="?page=newsletter_main_automated"><?php esc_html_e('Check it out.', 'newsletter'); ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php } ?>