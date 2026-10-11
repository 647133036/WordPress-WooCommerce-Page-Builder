<?php
/**
 * Wholesale Suite Abilities API observability listener.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Abilities_Audit' ) ) {

    /**
     * Observes every `wholesale-suite/*` ability invocation and writes one audit log entry per
     * invocation, via WordPress core's WP 7.1 Abilities API invocation hooks.
     *
     * Purely observational: every filter this class taps returns its input unmodified, so it can
     * never change an ability's return value or the input an execute callback sees. It is scoped
     * to the `wholesale-suite/` name prefix — abilities registered by every other provider (core,
     * WooCommerce, third parties) pass straight through untouched.
     *
     * Feature-detected rather than version-gated on WWP's own requirements: the invocation hooks
     * this class taps (`wp_pre_execute_ability`, `wp_ability_invoked`, `wp_ability_validate_input`,
     * `wp_ability_permission_result`, `wp_ability_execute_result`, `wp_ability_validate_output`,
     * `wp_after_execute_ability`) arrived in WordPress core 7.1, and WooCommerce's own vendored
     * copy of the Abilities API (bundled for WP < 6.9 support) does not carry them. Where they are
     * absent, this class attaches nothing and the whole suite runs exactly as it did before.
     *
     * @since 2.3.0
     */
    class WWP_Abilities_Audit {

        /**
         * Ability-name prefix this listener audits. Every other ability is a pass-through no-op.
         *
         * Coupled to {@see WWP_Abilities::CATEGORY} by a Pest assertion rather than by referencing
         * it directly here — this file is required before `class-wwp-abilities.php` is guaranteed
         * loaded in every code path, so a class-constant expression here would be fragile to the
         * require order rather than to the value itself.
         *
         * @since 2.3.0
         * @access public
         * @var string
         */
        const SCOPE_PREFIX = 'wholesale-suite/';

        /**
         * Ceiling on `$invocation_stack` — the oldest pending invocation is drained (logged with
         * an `unknown` outcome) once this many accumulate, so a long-lived process (WP-CLI, Action
         * Scheduler) looping a leak path (see {@see self::flush_pending()}) many times before
         * `shutdown` fires is bounded, not unbounded (self-review finding: unbounded invocation
         * stack).
         *
         * @since 2.3.0
         * @access public
         * @var int
         */
        const MAX_PENDING = 100;

        /**
         * Ceiling on the number of top-level keys {@see self::summarize_input()} iterates before
         * truncating.
         *
         * `finalize()`'s synthesize branch (see its own docblock) is reachable pre-authorization,
         * by an anonymous caller, so an unbounded input array would let that caller force an
         * unbounded per-request iteration/allocation cost here and an unbounded write into the
         * WooCommerce log file, on every request, regardless of whether the resulting entry is
         * ultimately written or suppressed by {@see self::apply_denial_dedupe()} (self-review
         * round 9 follow-up finding: MEDIUM).
         *
         * @since 2.3.0
         * @access public
         * @var int
         */
        const MAX_SUMMARY_KEYS = 50;

        /**
         * Property that holds the single main instance of WWP_Abilities_Audit.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Abilities_Audit
         */
        private static $_instance;

        /**
         * Pending invocations, most recent last.
         *
         * Pushed on `wp_ability_invoked`, popped by whichever hook first closes out that
         * invocation (a validation/permission/execute failure, or a genuine success). A stack
         * rather than a single slot because an ability's own `execute_callback` can invoke another
         * ability before returning, so more than one invocation may be open at once.
         *
         * @since 2.3.0
         * @access private
         * @var array
         */
        private $invocation_stack = array();

        /**
         * Core's own, untouched `wp_pre_execute_ability` default value for each open invocation,
         * matched by ability name.
         *
         * Populated by {@see self::on_capture_pre_execute_sentinel()}, attached at `PHP_INT_MIN` so
         * it is the FIRST `wp_pre_execute_ability` callback to run and therefore always sees core's
         * own per-invocation `WP_Filter_Sentinel` before any other callback has had a chance to
         * replace it. {@see self::on_pre_execute_ability()} (attached at `PHP_INT_MAX`, so it runs
         * LAST) compares its own `$pre` against the matching entry here by strict identity — the
         * same test `WP_Ability::execute()` itself uses — rather than `instanceof
         * WP_Filter_Sentinel`. `WP_Filter_Sentinel`'s own class docblock documents that "returning a
         * freshly constructed WP_Filter_Sentinel is treated as a replacement, not as pass-through",
         * so a third-party short-circuit that itself returns a NEW `WP_Filter_Sentinel` instance is
         * still `instanceof WP_Filter_Sentinel` and would otherwise be indistinguishable from "no one
         * touched this" — silently leaving the pending entry unresolved until the `shutdown` sweep
         * instead of closing it immediately, defeating the very guard this class exists to provide
         * (self-review round 3 finding: MEDIUM).
         *
         * A stack for the same reason {@see self::$invocation_stack} is: a nested invocation opens
         * and closes its own capture entirely inside an outer one.
         *
         * @since 2.3.0
         * @access private
         * @var array
         */
        private $pre_execute_sentinel_stack = array();

        /**
         * Fingerprints of synthesised entries already logged this request, keyed by
         * `<ability_name>|<error_code>|<input hash>`.
         *
         * Core's REST run controller (`WP_REST_Abilities_V1_Run_Controller`) validates input
         * from two call sites before `WP_Ability::execute()` ever runs — once from its
         * `sanitize_callback` and again from its own `check_ability_permissions()` — so
         * `wp_ability_validate_input` fires twice with an identical failing result and no
         * pending entry either time. Without this guard {@see self::finalize()} would
         * synthesise and log two entries for one REST request (self-review finding 1).
         *
         * @since 2.3.0
         * @access private
         * @var array<string,true>
         */
        private $synthesized = array();

        /**
         * Ability names whose invocation has already been popped off `$invocation_stack` and
         * logged once by {@see self::finalize()} within the current dedupe window, keyed by name.
         *
         * `finalize()` can be reached more than once for the same genuinely-invoked ability: a
         * third-party filter registered at the same `PHP_INT_MAX` priority as this class's own
         * observers, but registered LATER (WordPress runs same-priority callbacks in registration
         * order), can recover a failure into a success on `wp_ability_execute_result` AFTER this
         * class has already popped the pending entry and logged an `error` outcome for it. The
         * ability's own execution then completes normally and `wp_after_execute_ability` fires,
         * calling `finalize( $name, null )` again — with the real pending entry already gone,
         * `pop_pending()` returns null, and without this guard `finalize()` would take that as the
         * REST-early-denial synthesize case and log a second, contradictory `success` entry for
         * the same invocation (self-review finding: MEDIUM, exactly-one-entry violated). Checked
         * and set only around a REAL pop (never around a synthesized entry, so the synthesize
         * path's own fingerprint dedup for the genuinely-never-pending REST case is unaffected).
         *
         * @since 2.3.0
         * @access private
         * @var array<string,true>
         */
        private $closed_pending = array();

        /**
         * Memoised result of {@see self::is_disabled()}.
         *
         * Null until first evaluated. Mirrors {@see WWP_Abilities::$skip_registration} — without
         * this the kill switch is re-evaluated (re-running `apply_filters()`) on every single
         * callback invocation for the life of the request instead of once.
         *
         * @since 2.3.0
         * @access private
         * @var bool|null
         */
        private $disabled;

        /**
         * Request-local running count of denials suppressed by {@see self::apply_denial_dedupe()},
         * keyed by the same (actor, ability, error code) transient key.
         *
         * Lets the suppress path report a running count to `wwp_abilities_audit_denial_suppressed`
         * without rewriting the transient on every repeat — that rewrite was a `wp_options` upsert
         * per denied request on a host with no persistent object cache, and it reset the TTL on
         * each hit so a continuously-hammered key never expired (review finding: MEDIUM, CWE-400
         * write pressure). The count is therefore best-effort per request rather than cumulative
         * across the whole window; the per-(actor, ability, error) write-bound the dedupe exists to
         * enforce is unaffected.
         *
         * @since 2.3.0
         * @access private
         * @var array<string,int>
         */
        private $suppressed_counts = array();

        /**
         * Memoised, shape-guarded result of {@see self::identifier_keys()}.
         *
         * Null until first built. Without this the allowlist is rebuilt and
         * `wwp_abilities_audit_identifier_keys` re-dispatched on every {@see self::summarize_input()}
         * call (up to twice per invocation), and a filter returning a non-array would be
         * dereferenced unguarded in `summarize_input()` (review finding: LOW, security — asymmetric
         * with the `wwp_abilities_audit_entry` hardening). Mirrors {@see self::$disabled}.
         *
         * @since 2.3.0
         * @access private
         * @var array<string,string>|null
         */
        private $identifier_keys;

        /**
         * WWP_Abilities_Audit constructor.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Abilities_Audit model.
         */
        public function __construct( $dependencies = array() ) {
            // Nothing to see here yet.
        }

        /**
         * Ensure that only one instance of WWP_Abilities_Audit is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Abilities_Audit model.
         * @return WWP_Abilities_Audit
         */
        public static function instance( $dependencies = array() ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Whether the WP 7.1 Abilities API invocation hooks this class relies on actually exist.
         *
         * `class_exists( 'WP_Ability' )` alone is not enough: WooCommerce 10.9+ vendors its own
         * copy of the Abilities API for sites below WP 6.9, and that copy predates the WP 7.1
         * invocation hooks this class taps. So a version check confirms the *host* is new enough,
         * and a `ReflectionClass` file-path check confirms the `WP_Ability` class actually in play
         * is core's own copy (living under `wp-includes`) rather than WooCommerce's vendored one.
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool True when the invocation hooks are available to attach to.
         */
        protected function is_hooks_available() {

            $wp_version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';

            // Strip a pre-release suffix (e.g. "7.1-beta2", "7.1-alpha-60123-src") before
            // comparing — version_compare() otherwise treats a pre-release build as OLDER than
            // the stable version it precedes, which would report the hooks unavailable on the
            // exact builds most likely to be testing them.
            $wp_version = preg_replace( '/-.*$/', '', $wp_version );

            if ( ! version_compare( $wp_version, '7.1', '>=' ) ) {
                return false;
            }

            if ( ! class_exists( 'WP_Ability' ) ) {
                return false;
            }

            try {
                $reflection = new ReflectionClass( 'WP_Ability' );
            } catch ( ReflectionException $e ) {
                return false;
            }

            $file = $reflection->getFileName();

            if ( false === $file ) {
                return false;
            }

            return false !== strpos( str_replace( '\\', '/', $file ), '/wp-includes/' );
        }

        /**
         * Whether the `WWP_DISABLE_ABILITIES_AUDIT` constant is switched on.
         *
         * Split out as a seam because a PHP constant cannot be undefined once set, so defining it
         * inside a test would suppress the audit log for every test that followed in the same
         * process. Overriding this is how the constant branch — the documented wp-config /
         * mu-plugin switch — gets covered without poisoning the run.
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool True when the constant is defined and truthy.
         */
        protected function is_disabled_by_constant() {
            return defined( 'WWP_DISABLE_ABILITIES_AUDIT' ) && WWP_DISABLE_ABILITIES_AUDIT;
        }

        /**
         * Whether a usable WooCommerce logger is available.
         *
         * Thin seam over `function_exists( 'wc_get_logger' )` so the "no logger available"
         * degradation path is reachable from the test suite without needing WooCommerce itself
         * absent.
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool True when entries may be routed to `wc_get_logger()`.
         */
        protected function is_logger_available() {
            return function_exists( 'wc_get_logger' );
        }

        /**
         * Whether the current request is a long-lived system context — WP-CLI or Action
         * Scheduler/cron — rather than an ordinary anonymous HTTP request.
         *
         * No longer consulted by {@see self::finalize()}'s synthesize branch, which used to tell a
         * WP-CLI/cron invocation at `user_id` `0` apart from a throwaway anonymous REST denial:
         * {@see self::apply_denial_dedupe()} now bounds every error-outcome entry per (user,
         * ability) regardless of authentication state, so that distinction is no longer needed to
         * close the unbounded-write surface (self-review round 9 finding: HIGH). Kept as a seam —
         * both a genuine WP-CLI/cron context and an ordinary request remain independently reachable
         * from the test suite without needing a real WP-CLI process or a scheduled cron run, should
         * a future caller need the distinction again.
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool True when running under WP-CLI or a cron/Action Scheduler request.
         */
        protected function is_system_context() {
            return ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron();
        }

        /**
         * Whether the audit log is switched off.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool True when nothing should be logged.
         */
        private function is_disabled() {

            if ( null === $this->disabled ) {

                /**
                 * Filters whether the Wholesale Suite Abilities audit log is disabled.
                 *
                 * This filter is evaluated once in {@see self::run()} during plugin load, before any
                 * hook is attached — so a callback added later (e.g. from a theme `functions.php` or
                 * on `plugins_loaded`) is too late and is silently ignored. It must be registered
                 * from an mu-plugin, or a plugin that loads before this one, to take effect. See
                 * {@see self::run()} for why the "no hooks attached when disabled" requirement forces
                 * this pre-attach evaluation.
                 *
                 * @since 2.3.0
                 *
                 * @param bool $disabled Whether the audit log is disabled. Default false.
                 */
                $this->disabled = $this->is_disabled_by_constant()
                    || (bool) apply_filters( 'wwp_disable_abilities_audit', false );
            }

            return $this->disabled;
        }

        /**
         * Attach this listener to the WP 7.1 Abilities API invocation hooks.
         *
         * Issue #1030's own acceptance criterion requires that, with the audit switch off, "no
         * entries are recorded and no hooks are attached" — so both the `WWP_DISABLE_ABILITIES_AUDIT`
         * constant and the `wwp_disable_abilities_audit` filter are evaluated once, right here,
         * before any hook is attached (self-review round 9 finding: MEDIUM). This intentionally
         * does NOT mirror {@see WWP_Abilities::run()}, which defers its own equivalent switches to
         * its registration callbacks so a filter added later in the plugin-loading sequence is
         * still honoured: that tradeoff is not available here, because "no hooks attached" can only
         * be true if the decision is made before `add_action()`/`add_filter()` ever run. A
         * `wwp_disable_abilities_audit` filter must therefore be registered early enough to exist by
         * the time this plugin's bootstrap calls `run()` (an mu-plugin, or a plugin that loads
         * before this one) — a callback added afterwards has no effect, since the hooks this class
         * would have attached are already decided one way or the other.
         *
         * @since 2.3.0
         * @access public
         *
         * @return void
         */
        public function run() {

            if ( $this->is_disabled() ) {
                return;
            }

            if ( ! $this->is_hooks_available() ) {
                return;
            }

            // PHP_INT_MIN: this callback must see $pre BEFORE any other `wp_pre_execute_ability`
            // filter has had a chance to replace it, so it captures core's own, untouched
            // per-invocation sentinel for on_pre_execute_ability() to compare against by identity.
            add_filter( 'wp_pre_execute_ability', array( $this, 'on_capture_pre_execute_sentinel' ), PHP_INT_MIN, 4 );
            // PHP_INT_MAX: this callback must see $pre AFTER every other `wp_pre_execute_ability`
            // filter has had its turn, so it can tell a genuine short-circuit (identity against
            // core's per-invocation sentinel is broken) from "nothing decided yet".
            add_filter( 'wp_pre_execute_ability', array( $this, 'on_pre_execute_ability' ), PHP_INT_MAX, 4 );
            add_action( 'wp_ability_invoked', array( $this, 'on_ability_invoked' ), 10, 3 );
            // PHP_INT_MAX: these four are observer callbacks reading a decision, not deciding one —
            // core documents them as override points (e.g. `wp_ability_execute_result` recovering
            // a failure into a success, `wp_ability_permission_result` granting temporary access), so
            // a lower priority would record an intermediate value a later-priority filter overturns,
            // producing a second, contradictory log entry for the same invocation (self-review
            // finding: hook priority).
            add_filter( 'wp_ability_validate_input', array( $this, 'on_validate_input' ), PHP_INT_MAX, 3 );
            add_filter( 'wp_ability_permission_result', array( $this, 'on_permission_result' ), PHP_INT_MAX, 4 );
            add_filter( 'wp_ability_execute_result', array( $this, 'on_execute_result' ), PHP_INT_MAX, 4 );
            add_filter( 'wp_ability_validate_output', array( $this, 'on_validate_output' ), PHP_INT_MAX, 3 );
            add_action( 'wp_after_execute_ability', array( $this, 'on_after_execute_ability' ), 10, 4 );
            add_action( 'shutdown', array( $this, 'flush_pending' ) );
        }

        /**
         * Whether this ability name is in scope, and the audit log is switched on.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $ability_name Ability name.
         * @return bool
         */
        private function should_audit( $ability_name ) {

            if ( 0 !== strpos( (string) $ability_name, self::SCOPE_PREFIX ) ) {
                return false;
            }

            return ! $this->is_disabled();
        }

        /**
         * Fires on the `wp_pre_execute_ability` filter, BEFORE any other `wp_pre_execute_ability`
         * callback has had a chance to touch it (this callback is attached at `PHP_INT_MIN`).
         *
         * Captures core's own, untouched per-invocation `WP_Filter_Sentinel` for
         * {@see self::on_pre_execute_ability()} to compare against by identity, so that comparison
         * is never fooled by a later callback that returns a freshly constructed
         * `WP_Filter_Sentinel` of its own as a short-circuit value (a pattern the class's own
         * docblock documents as valid — see {@see self::$pre_execute_sentinel_stack}).
         *
         * Always returns `$pre` completely unchanged.
         *
         * @since 2.3.0
         * @access public
         *
         * @param mixed      $pre          Core's per-invocation sentinel, untouched at this priority.
         * @param string     $ability_name Ability name.
         * @param mixed      $input        Raw input passed to `execute()`.
         * @param WP_Ability $ability      The ability instance.
         * @return mixed Unmodified.
         */
        public function on_capture_pre_execute_sentinel( $pre, $ability_name, $input, $ability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $input and $ability declared to match the `wp_pre_execute_ability` filter's documented signature; intentionally unused.

            if ( $this->should_audit( $ability_name ) ) {
                $this->pre_execute_sentinel_stack[] = array(
                    'ability_name' => (string) $ability_name,
                    'sentinel'     => $pre,
                );
            }

            return $pre;
        }

        /**
         * Fires on the `wp_pre_execute_ability` filter, AFTER `wp_ability_invoked` has already
         * opened a pending entry and after every other `wp_pre_execute_ability` filter has had a
         * chance to short-circuit (this callback is attached at `PHP_INT_MAX`).
         *
         * `$pre` has short-circuited when it is no longer identical to the untouched sentinel
         * {@see self::on_capture_pre_execute_sentinel()} captured for this invocation — the same
         * identity test `WP_Ability::execute()` itself uses. `instanceof WP_Filter_Sentinel` alone
         * is NOT sufficient: a short-circuiting callback may itself return a freshly constructed
         * `WP_Filter_Sentinel` (an instance of the class, but a different object) as its own
         * "distinguishable no-value" result — `WP_Filter_Sentinel`'s own class docblock documents
         * this as a valid replacement, not a pass-through. A short-circuit here bypasses every hook
         * this class taps afterwards, so without this check the pending entry
         * {@see self::on_ability_invoked()} opened would otherwise sit unresolved until
         * {@see self::flush_pending()} sweeps it at `shutdown` — real exposure in a long-lived
         * process (WP-CLI, Action Scheduler).
         *
         * Always returns `$pre` completely unchanged — returning anything else here (even a copy)
         * would short-circuit every `wholesale-suite/*` ability itself.
         *
         * @since 2.3.0
         * @access public
         *
         * @param mixed      $pre          Pre-computed result, or core's per-invocation sentinel to continue.
         * @param string     $ability_name Ability name.
         * @param mixed      $input        Raw input passed to `execute()`.
         * @param WP_Ability $ability      The ability instance.
         * @return mixed Unmodified.
         */
        public function on_pre_execute_ability( $pre, $ability_name, $input, $ability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $input and $ability declared to match the `wp_pre_execute_ability` filter's documented signature; intentionally unused.

            if ( ! $this->should_audit( $ability_name ) ) {
                return $pre;
            }

            $captured = $this->pop_pre_execute_sentinel( $ability_name );

            // No captured sentinel means on_capture_pre_execute_sentinel() never ran for this
            // invocation (e.g. run() was never called on this instance) — fall back to the
            // instanceof check rather than treating every invocation as short-circuited.
            $short_circuited = null !== $captured
                ? $pre !== $captured
                : ! $pre instanceof WP_Filter_Sentinel;

            if ( $short_circuited ) {
                $this->finalize_short_circuited( $ability_name );
            }

            return $pre;
        }

        /**
         * Fires on `wp_ability_invoked`. Opens a pending entry for this invocation.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string     $ability_name Ability name.
         * @param mixed      $input        Raw input passed to `execute()`.
         * @param WP_Ability $ability      The ability instance.
         * @return void
         */
        public function on_ability_invoked( $ability_name, $input, $ability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Declared to match the `wp_ability_invoked` action's documented signature.

            if ( ! $this->should_audit( $ability_name ) ) {
                return;
            }

            $this->invocation_stack[] = array(
                'ability_name'  => (string) $ability_name,
                'user_id'       => get_current_user_id(),
                'timestamp'     => time(),
                'input_summary' => $this->summarize_input( $input ),
            );

            // Bound the stack for a long-lived process (WP-CLI, Action Scheduler) that can invoke
            // many abilities before `shutdown` ever fires: this method only ever pushes one entry,
            // so a single drain of the oldest pending entry (logged with an `unknown` outcome, same
            // as flush_pending()'s own sweep) is enough to restore the cap.
            if ( count( $this->invocation_stack ) > self::MAX_PENDING ) {
                $this->log_unknown_entry( array_shift( $this->invocation_stack ) );
            }

            // A genuine invocation opens a new dedupe window — a batch client legitimately
            // re-invoking this ability with the same bad input still gets one entry per
            // invocation, not zero after the first.
            $this->synthesized = array();
            unset( $this->closed_pending[ (string) $ability_name ] );
        }

        /**
         * Fires on the `wp_ability_validate_input` filter. Closes out the invocation on failure.
         *
         * Always returns `$is_valid` unmodified — this is an observer, never a participant in the
         * validation decision.
         *
         * @since 2.3.0
         * @access public
         *
         * @param true|WP_Error|false $is_valid     Validation result so far.
         * @param mixed               $input        Input being validated.
         * @param string              $ability_name Ability name.
         * @return true|WP_Error|false Unmodified.
         */
        public function on_validate_input( $is_valid, $input, $ability_name ) {

            if ( ! $this->should_audit( $ability_name ) ) {
                return $is_valid;
            }

            if ( false === $is_valid || ( is_wp_error( $is_valid ) && $is_valid->has_errors() ) ) {
                $error = is_wp_error( $is_valid )
                    ? $is_valid
                    : new WP_Error( 'ability_invalid_input', __( 'Invalid input.', 'woocommerce-wholesale-prices' ) );

                $this->finalize( $ability_name, $error, $input );
            }

            return $is_valid;
        }

        /**
         * Fires on the `wp_ability_permission_result` filter. Closes out the invocation on denial.
         *
         * The single most common failure mode: `WP_Ability::execute()` returns early on permission
         * denial without ever firing `wp_before_execute_ability` / `wp_after_execute_ability`, so
         * this filter is the only hook that carries it.
         *
         * Always returns `$permission` unmodified.
         *
         * @since 2.3.0
         * @access public
         *
         * @param bool|WP_Error $permission   Permission result so far.
         * @param string        $ability_name Ability name.
         * @param mixed         $input        Input for the permission check.
         * @param WP_Ability    $ability      The ability instance.
         * @return bool|WP_Error Unmodified.
         */
        public function on_permission_result( $permission, $ability_name, $input, $ability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $ability declared to match the `wp_ability_permission_result` filter's documented signature.

            if ( ! $this->should_audit( $ability_name ) ) {
                return $permission;
            }

            if ( true !== $permission ) {
                // No `status` data attached here: pinning 403 bypassed finalize()'s own
                // rest_authorization_required_code() inference below, so a logged-out denial
                // (HTTP 401) was recorded as 403 — disagreeing with the actual REST response
                // (self-review finding 2). Let finalize() infer the status core would report.
                $error = is_wp_error( $permission )
                    ? $permission
                    : new WP_Error(
                        'ability_invalid_permissions',
                        __( 'Ability does not have necessary permission.', 'woocommerce-wholesale-prices' )
                    );

                $this->finalize( $ability_name, $error, $input );
            }

            return $permission;
        }

        /**
         * Fires on the `wp_ability_execute_result` filter. Closes out the invocation on failure.
         *
         * Always returns `$result` unmodified.
         *
         * @since 2.3.0
         * @access public
         *
         * @param mixed      $result       Result of the execute callback so far.
         * @param string     $ability_name Ability name.
         * @param mixed      $input        Input passed to the execute callback.
         * @param WP_Ability $ability      The ability instance.
         * @return mixed Unmodified.
         */
        public function on_execute_result( $result, $ability_name, $input, $ability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- $ability declared to match the `wp_ability_execute_result` filter's documented signature.

            if ( ! $this->should_audit( $ability_name ) ) {
                return $result;
            }

            if ( is_wp_error( $result ) ) {
                $this->finalize( $ability_name, $result, $input );
            }

            return $result;
        }

        /**
         * Fires on the `wp_ability_validate_output` filter. Closes out the invocation on failure.
         *
         * Always returns `$is_valid` unmodified.
         *
         * @since 2.3.0
         * @access public
         *
         * @param true|WP_Error|false $is_valid     Validation result so far.
         * @param mixed               $output       Output being validated.
         * @param string              $ability_name Ability name.
         * @return true|WP_Error|false Unmodified.
         */
        public function on_validate_output( $is_valid, $output, $ability_name ) {

            if ( ! $this->should_audit( $ability_name ) ) {
                return $is_valid;
            }

            if ( false === $is_valid || ( is_wp_error( $is_valid ) && $is_valid->has_errors() ) ) {
                $error = is_wp_error( $is_valid )
                    ? $is_valid
                    : new WP_Error( 'ability_invalid_output', __( 'Invalid output.', 'woocommerce-wholesale-prices' ) );

                $this->finalize( $ability_name, $error );
            }

            return $is_valid;
        }

        /**
         * Fires on `wp_after_execute_ability`. This only fires once validation, permissions and
         * execution have all passed, so it always means a genuine success.
         *
         * @since 2.3.0
         * @access public
         *
         * @param string     $ability_name Ability name.
         * @param mixed      $input        Input passed to the ability.
         * @param mixed      $result       Result of the ability execution.
         * @param WP_Ability $ability      The ability instance.
         * @return void
         */
        public function on_after_execute_ability( $ability_name, $input, $result, $ability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Declared to match the `wp_after_execute_ability` action's documented signature.

            if ( ! $this->should_audit( $ability_name ) ) {
                return;
            }

            $this->finalize( $ability_name, null );
        }

        /**
         * Pop the pending entry matching this ability name off the stack.
         *
         * Searched from the top down rather than assuming the top entry always matches: two
         * different `wholesale-suite/*` abilities can be open at once (one invoking the other from
         * inside its execute callback), and whichever one's terminal hook fires must be matched by
         * name, not by position. If nothing matches, this is defensively a no-op rather than
         * logging an entry attributed to the wrong invocation.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $ability_name Ability name to match.
         * @return array|null The pending entry, or null when none matched.
         */
        private function pop_pending( $ability_name ) {
            return $this->pop_matching_stack_entry( $this->invocation_stack, $ability_name );
        }

        /**
         * Pop the top-down first stack entry matching this ability name, splicing it out.
         *
         * Shared by {@see self::pop_pending()} and {@see self::pop_pre_execute_sentinel()}, which
         * searched their respective stacks with the identical top-down, match-by-name, splice-out
         * algorithm — kept in one place so a fix to the matching rule cannot miss one of them
         * (review finding: LOW, duplicated stack-search-and-splice). Searched from the top down
         * rather than assuming the top entry matches: two `wholesale-suite/*` abilities can be open
         * at once (one invoking the other from inside its execute callback), so a nested invocation
         * must be matched by name, not by position. If nothing matches, this is a no-op.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $stack        Stack of entries, each carrying an `ability_name` key. Passed
         *                             by reference and mutated: the matched entry is spliced out.
         * @param string $ability_name Ability name to match.
         * @return array|null The matched stack entry, or null when none matched.
         */
        private function pop_matching_stack_entry( array &$stack, $ability_name ) {

            for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
                if ( $stack[ $i ]['ability_name'] === (string) $ability_name ) {
                    $entry = $stack[ $i ];
                    array_splice( $stack, $i, 1 );

                    return $entry;
                }
            }

            return null;
        }

        /**
         * Pop the captured `wp_pre_execute_ability` sentinel matching this ability name off the
         * stack.
         *
         * Searched from the top down for the same reason {@see self::pop_pending()} is: a nested
         * invocation must be matched by name, not by position.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $ability_name Ability name to match.
         * @return mixed|null The captured sentinel value, or null when none matched.
         */
        private function pop_pre_execute_sentinel( $ability_name ) {

            $entry = $this->pop_matching_stack_entry( $this->pre_execute_sentinel_stack, $ability_name );

            return null === $entry ? null : $entry['sentinel'];
        }

        /**
         * Close out and log a pending invocation, exactly once.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string        $ability_name Ability name.
         * @param WP_Error|null $error        The failure, or null for a genuine success.
         * @param mixed         $input        Input for this invocation, used only to synthesise a
         *                                    pending entry when {@see self::pop_pending()} finds
         *                                    none (see below).
         * @return void
         */
        private function finalize( $ability_name, $error, $input = null ) {

            $pending = $this->pop_pending( $ability_name );

            if ( null !== $pending ) {
                $this->closed_pending[ (string) $ability_name ] = true;
            } elseif ( isset( $this->closed_pending[ (string) $ability_name ] ) ) {
                // A REAL pending entry for this invocation was already popped and logged earlier
                // in this same frame — a later-registered, same-priority filter recovering the
                // outcome after that (see self::$closed_pending) must not reopen it as a second,
                // synthesized entry.
                return;
            }

            if ( null === $pending ) {
                /*
                 * Core's REST run controller (WP_REST_Abilities_V1_Run_Controller) validates input
                 * and checks permissions itself, returning 400/403 BEFORE WP_Ability::execute() —
                 * and therefore before wp_ability_invoked — ever runs. Without this, the single most
                 * common failure mode (permission denial) is invisible to the audit log on exactly
                 * the path that matters most. Synthesise the entry from this hook's own arguments
                 * instead of dropping it.
                 *
                 * That controller also validates input from TWO call sites before execute() runs
                 * (its sanitize_callback, then its own check_ability_permissions()), so this branch
                 * can be reached twice for one REST request with an identical failing result. Log
                 * the synthesised entry once per distinct (ability, error code, input) fingerprint
                 * within this invocation's dedupe window rather than once per hook fire.
                 *
                 * This branch alone — never called via wp_ability_invoked, only synthesised from a
                 * REST-controller-level denial — was the unbounded guest-write surface: WordPress
                 * core's REST dispatch runs input validation (and therefore this branch) BEFORE the
                 * route's permission_callback, so any authenticated user — subscriber level is
                 * enough — could drive unbounded writes here before authorization is ever checked,
                 * and an anonymous caller could do the same for free (self-review round 9 finding:
                 * HIGH, CWE-400). Rounds 6 and 7 each tried to gate this branch on the caller's
                 * authentication state instead — a per-user cap or a request-rate throttle — and
                 * each attempt left a gap the other's mechanism didn't cover. {@see
                 * self::record_entry()} now bounds every error-outcome entry (this branch's, and a
                 * genuinely-invoked ability's) to one write per (actor, ability, error code) per
                 * window, where an unauthenticated caller is its own actor bucket keyed by a hashed
                 * IP address rather than a single shared bucket for every guest (self-review round
                 * 9 follow-up finding: HIGH — a shared guest bucket let one throwaway denial go on
                 * to silence every OTHER anonymous caller's denial against that ability for the
                 * rest of the window) — so this branch no longer needs its own guest/system-context
                 * gate.
                 */
                $error_code    = $error instanceof WP_Error ? $error->get_error_code() : 'success';
                $input_summary = $this->summarize_input( $input );
                $fingerprint   = $ability_name . '|' . $error_code . '|' . md5( (string) wp_json_encode( $input_summary ) );

                if ( isset( $this->synthesized[ $fingerprint ] ) ) {
                    return;
                }

                $this->synthesized[ $fingerprint ] = true;

                $pending = array(
                    'ability_name'  => (string) $ability_name,
                    'user_id'       => get_current_user_id(),
                    'timestamp'     => time(),
                    'input_summary' => $input_summary,
                );
            }

            $entry = array(
                'ability_name'  => $pending['ability_name'],
                'user_id'       => $pending['user_id'],
                'timestamp'     => $pending['timestamp'],
                'input_summary' => $pending['input_summary'],
            );

            if ( $error instanceof WP_Error ) {
                $entry['outcome']    = 'error';
                $entry['error_code'] = $error->get_error_code();

                $data = $error->get_error_data();

                if ( is_array( $data ) && isset( $data['status'] ) ) {
                    $entry['http_status'] = (int) $data['status'];
                } else {
                    /*
                     * Core attaches no `status` to ability_invalid_input / ability_invalid_output /
                     * ability_missing_input_schema, and none to ability_invalid_permissions either
                     * — the REST run controller stamps the actual HTTP status at its own boundary
                     * instead of on the WP_Error. Infer the same status core's REST controller would
                     * report rather than defaulting every one of these to a misleading 500.
                     */
                    $client_error_codes = array( 'ability_invalid_input', 'ability_invalid_output', 'ability_missing_input_schema' );

                    if ( in_array( $entry['error_code'], $client_error_codes, true ) ) {
                        $entry['http_status'] = 400;
                    } elseif ( 'ability_invalid_permissions' === $entry['error_code'] ) {
                        $entry['http_status'] = function_exists( 'rest_authorization_required_code' )
                            ? rest_authorization_required_code()
                            : 401;
                    }
                    // Otherwise leave http_status unset rather than asserting a wrong default.
                }
            } else {
                $entry['outcome'] = 'success';
            }

            $this->filter_and_record( $entry, $ability_name );
        }

        /**
         * Close out a pending invocation that `wp_pre_execute_ability` short-circuited, with an
         * `unknown` outcome — core gives no outcome for a short-circuited invocation.
         *
         * Called from {@see self::on_pre_execute_ability()} (attached at `PHP_INT_MAX`) rather than
         * left for {@see self::flush_pending()} to sweep at `shutdown`, so the pending entry is
         * resolved immediately instead of sitting in `$invocation_stack` for the rest of the
         * request — real exposure in a long-lived process (WP-CLI, Action Scheduler) invoking the
         * same short-circuited ability many times before shutdown ever fires.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $ability_name Ability name.
         * @return void
         */
        private function finalize_short_circuited( $ability_name ) {

            $pending = $this->pop_pending( $ability_name );

            if ( null === $pending ) {
                return;
            }

            $this->log_unknown_entry( $pending );
        }

        /**
         * Build, filter, and log an `unknown`-outcome entry for one pending invocation — shared by
         * {@see self::finalize_short_circuited()}, {@see self::flush_pending()}, and the
         * `MAX_PENDING` drain in {@see self::on_ability_invoked()}, all three of which log a pending
         * entry whose true outcome was never observed.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $pending A pending entry popped or shifted off `$invocation_stack`.
         * @return void
         */
        private function log_unknown_entry( array $pending ) {

            $entry = array(
                'ability_name'  => $pending['ability_name'],
                'user_id'       => $pending['user_id'],
                'timestamp'     => $pending['timestamp'],
                'input_summary' => $pending['input_summary'],
                'outcome'       => 'unknown',
            );

            $this->filter_and_record( $entry, $pending['ability_name'] );
        }

        /**
         * Apply the `wwp_abilities_audit_entry` filter to a built entry, then either record it or —
         * if a filter left it the wrong shape — report and drop it.
         *
         * The shared tail of {@see self::finalize()} (invoked/success and every error branch) and
         * {@see self::log_unknown_entry()} (short-circuit, the `shutdown` sweep, and the
         * `MAX_PENDING` drain), both of which filtered, validated, and recorded with identical code
         * (review finding: LOW, duplicated filter/validate/record block).
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $entry        The built entry, before the `wwp_abilities_audit_entry` filter.
         * @param string $ability_name Ability name the entry describes.
         * @return void
         */
        private function filter_and_record( array $entry, $ability_name ) {

            /**
             * Filters a Wholesale Suite abilities audit log entry before it is logged.
             *
             * @since 2.3.0
             *
             * @param array  $entry        The audit log entry.
             * @param string $ability_name Ability name the entry describes.
             */
            $entry = apply_filters( 'wwp_abilities_audit_entry', $entry, $ability_name );

            if ( ! $this->is_valid_entry_shape( $entry ) ) {
                $this->report_invalid_entry( $entry, $ability_name );
                return;
            }

            $this->record_entry( $this->normalize_entry_shape( $entry ), $ability_name );
        }

        /**
         * Record a filtered, shape-valid entry: log it and notify listeners.
         *
         * The single choke point every entry-producing path funnels through — {@see self::finalize()}
         * (invoked/success, permission denial, invalid input, execute result, validate output, all
         * by way of the `$error`/success branch it builds `$entry` from) and
         * {@see self::log_unknown_entry()} (short-circuit, the `shutdown` sweep, and the
         * `MAX_PENDING` drain) both end here rather than calling {@see self::log()} directly.
         *
         * Every path reaching this point represents a real invocation — one that either fired
         * `wp_ability_invoked` (captured on `$invocation_stack` by {@see self::on_ability_invoked()})
         * or was synthesised from a REST-controller-level denial — so no authentication decision
         * belongs here (see {@see self::finalize()}'s synthesize branch for why one is no longer
         * needed at all). Deciding it here, on the filtered `$entry` rather than the invocation
         * itself, previously meant a genuine WP-CLI/cron invocation at `user_id` `0` was silently
         * dropped, and a `wwp_abilities_audit_entry` consumer that renamed or removed `user_id`
         * could disable the whole log (self-review finding, round 8).
         *
         * Every `error`-outcome entry is deduped per (actor, ability, error code) by {@see
         * self::apply_denial_dedupe()} before it is written — an `unknown`- or `success`-outcome
         * entry never is, so a genuine success is always recorded even right after a suppressed
         * denial.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array  $entry        The filtered, shape-valid, type-normalised audit log entry.
         * @param string $ability_name Ability name the entry describes.
         * @return void
         */
        private function record_entry( $entry, $ability_name ) {

            if ( 'error' === $entry['outcome'] ) {
                $entry = $this->apply_denial_dedupe( $entry );

                if ( null === $entry ) {
                    return;
                }
            }

            $this->log( $entry );

            /**
             * Fires after a Wholesale Suite abilities audit log entry has been logged.
             *
             * @since 2.3.0
             *
             * @param array  $entry        The audit log entry that was logged.
             * @param string $ability_name Ability name the entry describes.
             */
            do_action( 'wwp_abilities_audit_logged', $entry, $ability_name );
        }

        /**
         * Suppress a repeat error-outcome entry for the same (actor, ability, error code) within a
         * rolling window, so a denied caller — authenticated or not — cannot drive unbounded
         * audit-log writes (self-review round 9 finding: HIGH, CWE-400). Bounds error writes to one
         * per (actor, ability, error code) per window, rather than one per request.
         *
         * An authenticated actor is bucketed by user id; an unauthenticated one is bucketed by a
         * hashed `REMOTE_ADDR` rather than sharing a single `user_id = 0` bucket with every other
         * guest — a shared guest bucket meant one throwaway anonymous denial silenced every OTHER
         * anonymous caller's denial against that ability for the rest of the window, which is
         * exactly the visibility this class exists to provide (self-review round 9 follow-up
         * finding: HIGH). The error code is part of the key too, so a permission denial does not
         * suppress a later, genuinely different input-validation failure for the same actor and
         * ability.
         *
         * The rule: read the transient keyed by this (actor, ability, error code) triple. If it is
         * already set, this is a repeat within the window — bump a request-local running count
         * (never rewriting the transient, so the suppress path stays read-only: no `wp_options`
         * upsert per denied request on a host with no persistent object cache, and the window's TTL
         * never slides forward under continuous load — review finding: MEDIUM, CWE-400 write
         * pressure), fire `wwp_abilities_audit_denial_suppressed` with that count so a site can still
         * observe the volume even though the log itself does not record it, and suppress the write.
         * If it is absent, this is the first denial in a fresh window — set the transient once and
         * let the entry through, stamped with the window length so a consumer of
         * `wwp_abilities_audit_logged` can tell a written entry may stand in for repeats that
         * followed it silently.
         *
         * Deliberately a dedupe keyed on identity, not a counter of request rate: rounds 6 and 7
         * each tried throttling the synthesize branch by request rate instead, and each attempt
         * left a gap the other's mechanism didn't cover (see {@see self::finalize()}'s synthesize
         * branch for the detail). Do not reintroduce a rate-based throttle here.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $entry An error-outcome audit log entry.
         * @return array|null The entry to write (with `suppressed_repeats_window` added) on the
         *                     first denial in a window, or null when this denial must be suppressed.
         */
        private function apply_denial_dedupe( array $entry ) {

            /**
             * Filters the rolling window, in seconds, within which a repeat error-outcome entry
             * for the same (actor, ability, error code) is suppressed.
             *
             * @since 2.3.0
             *
             * @param int   $window Window length in seconds. Return 0 (or any non-positive value)
             *                      to disable the dedupe entirely — every error-outcome entry is
             *                      then logged as it occurs. Default `HOUR_IN_SECONDS`.
             * @param array $entry  The error-outcome entry about to be deduped.
             */
            $window = (int) apply_filters( 'wwp_abilities_audit_denial_dedupe_window', HOUR_IN_SECONDS, $entry );

            if ( $window <= 0 ) {
                return $entry;
            }

            $user_id = isset( $entry['user_id'] ) ? (int) $entry['user_id'] : 0;
            $actor   = $user_id > 0
                ? 'u' . $user_id
                // Hashed, and only ever used as half of a cache key — never logged, stored
                // verbatim, or otherwise output. The per-(actor, ability, error) write-bound this
                // key enforces assumes REMOTE_ADDR is the TCP peer and not client-controllable, the
                // conventional WordPress choice. On a site that repopulates it from a client header
                // (e.g. X-Forwarded-For via an upstream proxy or mu-plugin) a caller could vary that
                // header to mint distinct actor buckets, each good for one first-denial log write
                // per window.
                : 'ip' . substr( md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' ), 0, 12 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- read-only, hashed immediately, never output or persisted verbatim.

            $error_code = isset( $entry['error_code'] ) ? (string) $entry['error_code'] : '';
            $key        = 'wwp_aud_deny_' . $actor . '_' . md5( $entry['ability_name'] . '|' . $error_code );
            $state      = get_transient( $key );

            if ( false !== $state ) {
                // Read-only suppress path: bump a request-local count, seeded from the transient's
                // stored count on the first repeat seen this request, WITHOUT rewriting the
                // transient. Rewriting it here was a `wp_options` upsert per denied request on a
                // non-object-cache host, and reset the TTL on every hit so a hammered key never
                // expired (review finding: MEDIUM, CWE-400).
                $stored = ( is_array( $state ) && isset( $state['count'] ) ) ? (int) $state['count'] : 0;
                $count  = ( $this->suppressed_counts[ $key ] ?? $stored ) + 1;

                $this->suppressed_counts[ $key ] = $count;

                /**
                 * Fires when a repeat error-outcome entry is suppressed by the denial dedupe, so a
                 * site can still observe the volume the log itself does not record (self-review
                 * finding: MEDIUM — the suppressed count was write-only).
                 *
                 * @since 2.3.0
                 *
                 * @param array $entry            The entry that was suppressed.
                 * @param int   $suppressed_count How many denials (including this one) have been
                 *                                suppressed for this (actor, ability, error code) so
                 *                                far in this request, seeded from the count stored
                 *                                when the window opened. Best-effort per request
                 *                                rather than cumulative across the whole window,
                 *                                since the suppress path no longer rewrites the
                 *                                transient.
                 */
                do_action( 'wwp_abilities_audit_denial_suppressed', $entry, $count );

                return null;
            }

            set_transient( $key, array( 'count' => 1 ), $window );

            $entry['suppressed_repeats_window'] = $window;

            return $entry;
        }

        /**
         * Whether a `wwp_abilities_audit_entry`-filtered entry still has the two keys {@see
         * self::log()} interpolates directly — the only ones a wrong type can fatal on.
         *
         * `wwp_abilities_audit_entry` runs from inside `wp_after_execute_ability` — after the
         * ability's own write side effect has already happened — so a misbehaving third-party
         * filter returning a non-array, a missing key, or a non-string `ability_name`/`outcome`
         * (both interpolated by {@see self::log()}'s own `sprintf()` call) must not be allowed to
         * fatal the request at this point (self-review finding: unguarded filter dereference,
         * CWE-476-adjacent).
         *
         * Every OTHER key (`error_code`, `user_id`, `timestamp`, `http_status`, `input_summary`)
         * is safe in any shape once it reaches {@see self::log()} — it only ever lands inside the
         * `context` array WooCommerce's logger serializes, never interpolated into a string — so a
         * wrong type there is a type-CONTRACT violation on the filter, not a fatal risk. Round 9
         * first tried enforcing that contract here too, rejecting the whole entry on a mismatch;
         * that silently dropped a real invocation over something as ordinary as a numeric-string
         * `user_id` (self-review round 9 follow-up finding: MEDIUM, over-strict optional-key
         * rejection). {@see self::normalize_entry_shape()} coerces those keys after this check
         * passes, so a coercible mismatch still logs.
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed $entry The filtered return value.
         * @return bool
         */
        private function is_valid_entry_shape( $entry ) {

            if ( ! is_array( $entry ) || ! isset( $entry['ability_name'], $entry['outcome'] ) ) {
                return false;
            }

            if ( ! is_string( $entry['ability_name'] ) || '' === $entry['ability_name'] ) {
                return false;
            }

            if ( ! is_string( $entry['outcome'] ) || '' === $entry['outcome'] ) {
                return false;
            }

            return true;
        }

        /**
         * Coerce a shape-valid entry's optional keys to their documented type, defaulting a key
         * that cannot be coerced rather than dropping the whole entry.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $entry A shape-valid entry (see {@see self::is_valid_entry_shape()}).
         * @return array The same entry with every present optional key coerced to its documented
         *                type.
         */
        private function normalize_entry_shape( array $entry ) {

            if ( isset( $entry['error_code'] ) && ! is_string( $entry['error_code'] ) ) {
                $entry['error_code'] = is_scalar( $entry['error_code'] ) ? (string) $entry['error_code'] : '';
            }

            if ( isset( $entry['user_id'] ) && ! is_int( $entry['user_id'] ) ) {
                $entry['user_id'] = is_numeric( $entry['user_id'] ) ? (int) $entry['user_id'] : 0;
            }

            if ( isset( $entry['timestamp'] ) && ! is_int( $entry['timestamp'] ) ) {
                $entry['timestamp'] = is_numeric( $entry['timestamp'] ) ? (int) $entry['timestamp'] : time();
            }

            if ( isset( $entry['http_status'] ) && ! is_int( $entry['http_status'] ) ) {
                if ( is_numeric( $entry['http_status'] ) ) {
                    $entry['http_status'] = (int) $entry['http_status'];
                } else {
                    unset( $entry['http_status'] );
                }
            }

            if ( isset( $entry['input_summary'] ) && ! is_array( $entry['input_summary'] ) ) {
                $entry['input_summary'] = array( 'type' => gettype( $entry['input_summary'] ) );
            }

            return $entry;
        }

        /**
         * Notify listeners that a filtered audit log entry failed its shape/type check and was
         * dropped, without ever throwing or fataling on the ability's own request.
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed  $entry        The invalid value returned by the `wwp_abilities_audit_entry`
         *                             filter.
         * @param string $ability_name Ability name the entry would have described.
         * @return void
         */
        private function report_invalid_entry( $entry, $ability_name ) {

            /**
             * Fires when a `wwp_abilities_audit_entry`-filtered entry fails its shape/type check
             * and is dropped instead of logged.
             *
             * @since 2.3.0
             *
             * @param mixed  $entry        The invalid entry, as returned by the
             *                             `wwp_abilities_audit_entry` filter.
             * @param string $ability_name Ability name the entry would have described.
             */
            do_action( 'wwp_abilities_audit_invalid_entry', $entry, $ability_name );
        }

        /**
         * Log and clear every invocation still pending at `shutdown` — a last-resort sweep, not the
         * primary defence against a leaked pending entry.
         *
         * A `wp_pre_execute_ability` short-circuit is now resolved immediately by
         * {@see self::on_pre_execute_ability()} / {@see self::finalize_short_circuited()}, so this
         * sweep exists for the remaining `WP_Ability::execute()` early-return paths that bypass
         * every hook this class taps after `wp_ability_invoked` has already pushed a pending entry:
         * a `wp_ability_normalize_input` filter returning a `WP_Error`, the
         * `ability_missing_input_schema` WP_Error (fires before `wp_ability_validate_input`), and
         * `ability_invalid_permission_callback` (fires before `wp_ability_permission_result`). Each
         * of those is bounded **per request** by `MAX_PENDING` rather than by request lifetime — a
         * long-lived process (WP-CLI, Action Scheduler) that hits one of these paths many times
         * before `shutdown` fires is drained incrementally by {@see self::on_ability_invoked()}'s
         * own cap, not left to grow until this sweep finally runs. The true outcome for these is
         * genuinely unknown from here, not a success and not the specific error core returned, so
         * they are logged as such rather than guessed.
         *
         * @since 2.3.0
         * @access public
         *
         * @return void
         */
        public function flush_pending() {

            foreach ( $this->invocation_stack as $pending ) {
                $this->log_unknown_entry( $pending );
            }

            $this->invocation_stack = array();
        }

        /**
         * Route a finalized entry to the WooCommerce logger.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $entry Audit log entry.
         * @return void
         */
        private function log( $entry ) {

            if ( ! $this->is_logger_available() ) {
                return;
            }

            $message = sprintf( '[%1$s] %2$s', $entry['ability_name'], $entry['outcome'] );
            $context = array(
                'source' => 'wwp-abilities-audit',
                'entry'  => $entry,
            );

            wc_get_logger()->log( $this->log_level( $entry ), $message, $context );
        }

        /**
         * The PSR-3 level a finalized entry should log at.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $entry Audit log entry.
         * @return string A `WC_Log_Levels` constant.
         */
        private function log_level( $entry ) {

            if ( 'success' === $entry['outcome'] ) {
                return WC_Log_Levels::INFO;
            }

            return WC_Log_Levels::ERROR;
        }

        /**
         * Summarise ability input for logging.
         *
         * A per-field denylist match is a guess about which keys are sensitive, and two rounds of
         * this class's own review each found that guess wrong in a different direction — too broad
         * (round 1: substring matching hid load-bearing identifiers like `role_key`) and too narrow
         * (rounds 2 and 5: word-part/collapsed-string matching still missed real multi-word/
         * camelCase spellings such as `stripe_api_key`). An allowlist cannot fail the same way: a
         * key is either an exact match for a known resource identifier — logged, cast to its
         * declared shape — or it is not, and stays shape-only (type, plus a string's length or an
         * array's count, never the value itself). There is nothing to guess, because nothing
         * outside the allowlist is ever a candidate for logging.
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed $input Raw ability input.
         * @return mixed Summary — an allowlisted key's value is cast and included; every other
         *               value is shape-only. Truncated to {@see self::MAX_SUMMARY_KEYS} top-level
         *               keys, with `_truncated` set, when the input has more.
         */
        private function summarize_input( $input ) {

            if ( is_array( $input ) ) {

                $identifier_keys = $this->identifier_keys();
                $summary         = array();
                $truncated       = count( $input ) > self::MAX_SUMMARY_KEYS;

                if ( $truncated ) {
                    $input = array_slice( $input, 0, self::MAX_SUMMARY_KEYS, true );
                }

                foreach ( $input as $key => $value ) {

                    $cast = $identifier_keys[ $key ] ?? null;

                    if ( null !== $cast && is_scalar( $value ) ) {
                        $summary[ $key ] = 'int' === $cast ? absint( $value ) : sanitize_key( (string) $value );
                        continue;
                    }

                    $summary[ $key ] = $this->describe_shape( $value );
                }

                if ( $truncated ) {
                    $summary['_truncated'] = true;
                }

                return $summary;
            }

            // A top-level (keyless) input can never match a key in the allowlist below — the
            // allowlist matches on key name, and a scalar top-level input has none — so it is
            // always shape-only.
            return $this->describe_shape( $input );
        }

        /**
         * The exact-match allowlist of ability input keys whose value is safe to log verbatim,
         * each mapped to how it must be cast before logging.
         *
         * Every key here is a resource identifier drawn from this suite's own registered ability
         * input schemas (WWP, WWPP, WWLC, WPAY, WWOF) — never a free-text field. Matching is exact
         * and case-sensitive, never a substring or word-part match, so a look-alike key
         * (`product_id_token`, `api_key`) never matches.
         *
         * @since 2.3.0
         * @access private
         *
         * @return array<string, string> Map of exact input key to cast: `'int'` (via `absint()`)
         *                                or `'slug'` (via `sanitize_key()`).
         */
        private function identifier_keys() {

            if ( null === $this->identifier_keys ) {

                /**
                 * Filters the exact-match allowlist of ability input keys safe to log verbatim.
                 *
                 * @since 2.3.0
                 *
                 * @param array<string, string> $identifier_keys Map of exact input key to cast
                 *                                                (`'int'` or `'slug'`).
                 */
                $keys = apply_filters(
                    'wwp_abilities_audit_identifier_keys',
                    array(
                        'product_id'    => 'int',    // WWP get/set-wholesale-price; WWPP.
                        'user_id'       => 'int',    // WWPP.
                        'customer'      => 'int',    // WPAY list-invoices (a user ID despite the name).
                        'order_id'      => 'int',    // WPAY.
                        'lead_id'       => 'int',    // WWLC.
                        'form_id'       => 'int',    // WWOF.
                        'duplicated_id' => 'int',    // WWOF duplicate-form.
                        'source_id'     => 'int',    // WWOF duplicate-form.
                        'field_id'      => 'slug',   // WWLC form-field identifier.
                        'role'          => 'slug',   // WWP, WWPP wholesale role slug.
                        'role_key'      => 'slug',   // WWPP role create/update.
                    )
                );

                // Shape guard: a filter returning a non-array must not be dereferenced by key in
                // summarize_input() (review finding: LOW, security — asymmetric with the
                // wwp_abilities_audit_entry hardening). Fall back to no allowlist (shape-only).
                $this->identifier_keys = is_array( $keys ) ? $keys : array();
            }

            return $this->identifier_keys;
        }

        /**
         * Describe a single value's shape for logging, without ever recording the value itself.
         *
         * @since 2.3.0
         * @access private
         *
         * @param mixed $value Value to describe.
         * @return array Shape descriptor: always a `type`, plus `length` for a string or `count`
         *               for an array.
         */
        private function describe_shape( $value ) {

            if ( is_string( $value ) ) {
                return array(
                    'type'   => 'string',
                    'length' => mb_strlen( $value ),
                );
            }

            if ( is_array( $value ) ) {
                return array(
                    'type'  => 'array',
                    'count' => count( $value ),
                );
            }

            if ( null === $value ) {
                return array( 'type' => 'null' );
            }

            return array(
                'type' => is_object( $value ) ? get_class( $value ) : gettype( $value ),
            );
        }
    }
}
