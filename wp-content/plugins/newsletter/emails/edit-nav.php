<?php
/** @var NewsletterEmailsAdmin $this */
$p = $_GET['page'];
$id = (int) $_GET['id'];
$nav_email = $this->get_email($id);
if (!$nav_email) {
    die('Newsletter not found');
}
$can_edit = $nav_email->status == TNP_Email::STATUS_DRAFT || $nav_email->status == TNP_Email::STATUS_PAUSED;
$editor_type = $this->get_editor_type($nav_email);
$edit_url = $this->get_editor_url($nav_email->id, $editor_type);
?>
<ul class="tnp-nav">
    <li class="tnp-nav-title"><a href="?page=newsletter_emails_index"><i class="fas fa-chevron-left"></i></a></li>
    <?php if ($can_edit) { ?>
        <li class="<?php echo $p === 'newsletter_emails_composer' ? 'active' : '' ?>"><a href="<?php echo $edit_url; ?>"><?php esc_html_e('Edit', 'newsletter') ?></a></li>
    <?php } ?>
    <li class="<?php echo $p === 'newsletter_emails_edit' ? 'active' : '' ?>"><a href="?page=newsletter_emails_edit&id=<?php echo $id; ?>"><?php esc_html_e('Sending', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_emails_logs' ? 'active' : '' ?>"><a href="?page=newsletter_emails_logs&id=<?php echo $id; ?>"><?php esc_html_e('Logs', 'newsletter') ?></a></li>
    <li class="<?php echo $p === 'newsletter_emails_versions' ? 'active' : '' ?>"><a href="?page=newsletter_emails_versions&id=<?php echo $id; ?>"><?php esc_html_e('Versions', 'newsletter') ?></a></li>
</ul>
<?php
unset($p);
unset($id);
?>