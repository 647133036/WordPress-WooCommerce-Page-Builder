<?php
/**
 * "Getting Started" WooCommerce Admin Inbox note.
 *
 * Mirrors the legacy `admin_notices` Getting Started notice (see
 * {@see WWP_Bootstrap::getting_started_notice()}) as a WooCommerce Admin Inbox note, so store
 * managers still see the call to action on WC-Admin pages, where legacy notices are hidden. Both
 * surfaces stay in agreement through the existing `wwp_admin_notice_getting_started_show` option —
 * no new option, transient, or cron is introduced.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 * @see     https://github.com/Rymera-Web-Co/woocommerce-wholesale-prices/issues/26
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Getting_Started_Note' ) ) {

    /**
     * Class WWP_Getting_Started_Note.
     *
     * @since 2.3.0
     */
    class WWP_Getting_Started_Note {

        /**
         * WC Admin Note unique name.
         *
         * @since 2.3.0
         */
        const NOTE_NAME = 'wc-admin-wwp-getting-started';

        /**
         * Content-data key marking that the note's current dismissal already synced the shared
         * `wwp_admin_notice_getting_started_show` option (either direction).
         *
         * @since 2.3.0
         */
        const SYNC_FLAG = 'wwp_synced_dismissal';

        /**
         * WWP_Getting_Started_Note constructor.
         *
         * @since 2.3.0
         * @access public
         */
        public function __construct() {

            // Create/reconcile on every admin page load. Not `plugins_loaded`: capabilities granted
            // on `init` must already be in effect, and the pass must not run for front-end, cron,
            // REST, or admin-ajax requests.
            add_action( 'admin_init', array( $this, 'maybe_sync_note' ), 10 );

            // Set flag to dismiss note.
            add_action( 'woocommerce_note_action_wwp-getting-started', array( $this, 'dismiss_on_click' ) );
        }

        /**
         * Create the note when it doesn't exist yet, or reconcile it with the shared option when
         * it does. In the steady state this is one `get_notes_with_name()` name lookup plus, when a
         * row exists, one note-object load to reconcile against — both small indexed reads on a tiny
         * table. Runs none at all outside `is_admin()` or when the option holds no meaningful value.
         *
         * @since 2.3.0
         * @access public
         */
        public function maybe_sync_note() {

            if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
                return;
            }

            // Every branch below can write the store-wide option or the note row, so gate the whole
            // pass on the capability, not only the create branch.
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            // If WC Admin is not active then don't proceed.
            if ( ! WWP_Helper_Functions::is_wc_admin_active() ) {
                return;
            }

            // WWPP removes WWP's classic Getting Started notice and shows its own combined notice
            // instead (see WWPP_Bootstrap::remove_wwp_getting_started_notice()). Retire any note
            // this class already created, rather than merely skipping creation here: a store that
            // upgrades from WWP alone to WWP+WWPP already has a live Inbox note, and a bare early
            // return would leave it stranded showing the call to action WWPP deliberately replaced.
            if ( WWP_Helper_Functions::is_wwpp_active() ) {
                try {
                    // One lookup first, so premium stores pay a SELECT per admin load (the same cost as
                    // the reconcile path) and never a DELETE when there is nothing to retire.
                    $data_store = \WC_Data_Store::load( 'admin-note' );
                    if ( ! empty( $data_store->get_notes_with_name( self::NOTE_NAME ) ) ) {
                        \Automattic\WooCommerce\Admin\Notes\Notes::delete_notes_with_name( self::NOTE_NAME );
                    }
                } catch ( Exception $e ) {
                    return;
                }
                return;
            }

            $option = get_option( 'wwp_admin_notice_getting_started_show' );

            // Only 'yes'/'no' are meaningful states; anything else (unset, or a stray value) means
            // there's nothing to reconcile against, so run no note query at all.
            if ( 'yes' !== $option && 'no' !== $option ) {
                return;
            }

            try {
                $data_store = \WC_Data_Store::load( 'admin-note' );
            } catch ( Exception $e ) {
                return;
            }

            $note_ids = $data_store->get_notes_with_name( self::NOTE_NAME );

            if ( empty( $note_ids ) ) {
                $this->maybe_add_note( $option );
                return;
            }

            $this->reconcile_note( $note_ids, $option );
        }

        /**
         * Create the note. Condition: option is 'yes' (WC Admin availability and the manage_woocommerce
         * capability are already checked by maybe_sync_note()).
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $option Current value of `wwp_admin_notice_getting_started_show`.
         */
        private function maybe_add_note( $option ) {

            if ( 'yes' !== $option ) {
                return;
            }

            try {

                $note_content = __(
                    'Thank you for choosing Wholesale Suite! The free WooCommerce Wholesale Prices plugin lets you set wholesale pricing for wholesale level customers. Would you like to find out how to drive it?',
                    'woocommerce-wholesale-prices'
                );

                $note = WWP_Helper_Functions::wc_admin_note_instance();
                $note->set_title( __( 'Get started with Wholesale Prices', 'woocommerce-wholesale-prices' ) );
                $note->set_content( $note_content );
                $note->set_content_data( (object) array() );
                $note->set_type( $note::E_WC_ADMIN_NOTE_INFORMATIONAL );
                $note->set_name( self::NOTE_NAME );
                $note->set_source( 'woocommerce-admin' );
                $note->add_action(
                    'wwp-getting-started',
                    __( 'Finish setup', 'woocommerce-wholesale-prices' ),
                    'admin.php?page=wholesale-suite',
                    $note::E_WC_ADMIN_NOTE_ACTIONED,
                    true
                );
                $note->add_action(
                    'wwp-getting-started-guide',
                    __( 'Read the Getting Started guide', 'woocommerce-wholesale-prices' ),
                    WWP_Helper_Functions::get_utm_url( 'kb/woocommerce-wholesale-prices-free-plugin-getting-started-guide', 'wwp', 'kb', 'wwpgettingstarted' ),
                    $note::E_WC_ADMIN_NOTE_UNACTIONED,
                    false
                );
                $note->save();

            } catch ( Exception $e ) {
                return;
            }
        }

        /**
         * Reconcile an existing note against the shared option (two-way sync), deduping down to a
         * single row first when more than one exists.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string[] $note_ids Ids of every note row with {@see self::NOTE_NAME}, ascending (numeric strings from `$wpdb->get_col()`).
         * @param string   $option   Current value of `wwp_admin_notice_getting_started_show`.
         */
        private function reconcile_note( $note_ids, $option ) {

            try {

                if ( count( $note_ids ) > 1 ) {
                    $note_ids = $this->dedupe_notes( $note_ids );
                }

                $note         = WWP_Helper_Functions::wc_admin_note_instance( current( $note_ids ) );
                $is_deleted   = (bool) $note->get_is_deleted();
                $actioned     = $note::E_WC_ADMIN_NOTE_ACTIONED === $note->get_status();
                $dismissed    = $is_deleted || $actioned;
                $content_data = $note->get_content_data();
                $synced       = is_object( $content_data ) && ! empty( $content_data->{self::SYNC_FLAG} );

                if ( 'no' === $option ) {

                    if ( ! $dismissed ) {
                        // Any writer (WWPP, WWLC, direct update_option) set the option to 'no' while
                        // the note is still live: action it and mark the dismissal synced.
                        $note->set_status( $note::E_WC_ADMIN_NOTE_ACTIONED );
                        $note->set_content_data( (object) array( self::SYNC_FLAG => true ) );
                        $note->save();
                    } elseif ( ! $synced ) {
                        // Option and note already agree (e.g. an Inbox dismissal and a sibling's 'no'
                        // write both landed before the next admin load): record the agreement so a
                        // later re-enable resurrects the note instead of the 'yes' branch reading the
                        // dismissal as new and flipping the option straight back to 'no'.
                        $note->set_content_data( (object) array( self::SYNC_FLAG => true ) );
                        $note->save();
                    }
                    return;
                }

                // $option is 'yes' from here.
                if ( $dismissed && ! $synced ) {
                    // The note was dismissed in the Inbox — actioned ("Finish setup") or soft-deleted
                    // (the "X", or WooCommerce's "Dismiss all") — without a synced dismissal on
                    // record: mirror that onto the legacy notice's option so both surfaces agree.
                    // Re-enabling the option later resurrects the note (branch below).
                    update_option( 'wwp_admin_notice_getting_started_show', 'no', 'no' );
                    $note->set_content_data( (object) array( self::SYNC_FLAG => true ) );
                    $note->save();
                } elseif ( $dismissed && $synced ) {
                    // The option was re-enabled after a synced dismissal (reinstall, sibling write):
                    // bring the note back.
                    $note->set_status( $note::E_WC_ADMIN_NOTE_UNACTIONED );
                    $note->set_is_deleted( false );
                    $note->set_content_data( (object) array() );
                    $note->save();
                }
            } catch ( Exception $e ) {
                return;
            }
        }

        /**
         * Keep the lowest-id note row and soft-delete every other row sharing the same name.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string[] $note_ids Ids of every note row with {@see self::NOTE_NAME}, ascending (numeric strings).
         * @return string[] The single id to keep, as a one-element array.
         */
        private function dedupe_notes( $note_ids ) {

            $note_ids = array_values( $note_ids );
            $keep_id  = array_shift( $note_ids );

            foreach ( $note_ids as $extra_id ) {
                $extra_note = WWP_Helper_Functions::wc_admin_note_instance( $extra_id );

                if ( $extra_note->get_is_deleted() ) {
                    continue; // Already soft-deleted on an earlier pass - nothing to write.
                }

                $extra_note->set_is_deleted( true );
                $extra_note->save();
            }

            return array( $keep_id );
        }

        /**
         * When "Finish setup" is clicked, dismiss the legacy notice through the option every
         * surface shares and record the dismissal as synced on the note, so a later re-enable of
         * the option resurrects the note instead of being reverted by reconcile_note(). WooCommerce
         * saves the note itself right after this hook returns, so no extra save() is needed here.
         * The secondary "Read the Getting Started guide" action has no hook here, so it stays
         * unactioned and never flips the option, matching the legacy notice (only its "X" dismisses).
         *
         * The hook is keyed on the action name, not the note name, so the note is checked before
         * anything is written.
         *
         * @since 2.3.0
         * @access public
         *
         * @param \Automattic\WooCommerce\Admin\Notes\Note|null $note The note whose action was triggered.
         * @throws \Automattic\WooCommerce\Internal\Admin\Notes\NoteActionForbiddenException When the
         *         current user lacks manage_woocommerce. Matches the pattern WooCommerce core's own
         *         note-action handlers use (see InstallJPAndWCSPlugins::install_jp_and_wcs_plugins()):
         *         the route-level permission check is intentionally coarser, so a per-handler capability
         *         gate throws rather than returning silently. NoteActions::trigger_note_action() catches
         *         this typed exception and maps it to a 403, and throwing aborts the dispatch before
         *         WooCommerce's own trigger_note_action() persists the actioned status - the note stays
         *         actionable in the Inbox rather than being silently marked done with no write.
         */
        public function dismiss_on_click( $note = null ) {

            if ( ! is_object( $note ) || ! method_exists( $note, 'get_name' ) || self::NOTE_NAME !== $note->get_name() ) {
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                // WooCommerce turns this exception into a 403 for the action request. The class lives in
                // WooCommerce's Internal namespace, so feature-detect it and simply do nothing without it.
                if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Notes\NoteActionForbiddenException' ) ) {
                    throw new \Automattic\WooCommerce\Internal\Admin\Notes\NoteActionForbiddenException(
                        esc_html__( 'You do not have permission to dismiss this notice.', 'woocommerce-wholesale-prices' )
                    );
                }
                return;
            }

            update_option( 'wwp_admin_notice_getting_started_show', 'no', 'no' );
            $note->set_content_data( (object) array( self::SYNC_FLAG => true ) );
        }
    }

    return new WWP_Getting_Started_Note();
}
