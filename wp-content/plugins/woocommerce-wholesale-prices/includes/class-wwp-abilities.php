<?php
/**
 * Wholesale Suite Abilities API provider.
 *
 * @package WooCommerceWholeSalePrices
 * @since   2.3.0
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WWP_Abilities' ) ) {

    /**
     * Sole provider of the Wholesale Suite Abilities API surface.
     *
     * WWP is the one plugin every other Wholesale Suite plugin hard-requires, so it owns the
     * shared infrastructure and every sibling consumes it:
     *
     * - availability detection ({@see wwp_abilities_available()}),
     * - the shared `wholesale-suite` ability category, registered exactly once, here,
     * - the `wholesale_suite_abilities_init` action siblings hook,
     * - the `WWP_ABILITIES_VERSION` contract constant,
     * - and WWP's own abilities.
     *
     * Siblings call WordPress core's `wp_register_ability()` directly rather than a WWP wrapper
     * method, so the shared contract surface stays at three symbols — one action, one constant,
     * one function — and no sibling takes a code-level dependency on a WWP signature.
     *
     * No copy of the Abilities API is bundled. It arrived in WordPress core in 6.9, and
     * WooCommerce 10.9+ vendors its own copy; every Wholesale Suite plugin requires WooCommerce,
     * so the API is present on any site where the suite can run. Where it genuinely is not, the
     * whole suite silently no-ops.
     *
     * @since 2.3.0
     */
    class WWP_Abilities {

        /**
         * Slug of the ability category shared by every Wholesale Suite plugin.
         *
         * @since 2.3.0
         * @access public
         * @var string
         */
        const CATEGORY = 'wholesale-suite';

        /**
         * Wholesale role assumed when a request does not name one.
         *
         * @since 2.3.0
         * @access public
         * @var string
         */
        const DEFAULT_ROLE = 'wholesale_customer';

        /**
         * Upper bound on customers considered when ordering by an order-derived field.
         *
         * `last_order` and `lifetime_spend` are not stored on the user, so ordering by them
         * means computing the value for every wholesale customer before the page can be sliced.
         * This bounds that work. When the bound is hit the response says so via
         * `pagination.truncated`, rather than silently returning a partial ordering an assistant
         * will present as authoritative.
         *
         * The window is the most recently registered customers, so on a store larger than this
         * bound a "top customers by spend" answer excludes the longest-standing accounts.
         *
         * Sized against the real cost rather than a round number: on the `last_order` path each
         * candidate costs one `WC_Customer` hydration plus one uncached order query
         * ({@see self::compute_customer_sort_key()}), so the bound is the number of order queries a
         * single REST request may issue. 500 of them plausibly exceeds `max_execution_time` on shared
         * hosting, which turns a slow answer into a 500; 250 keeps the worst case inside a typical
         * 30-second limit while still covering the great majority of wholesale stores in one page of
         * candidates. Note this bounds only the *sort* path. Every other path resolves
         * `last_order_date` for the whole page with a single aggregate query
         * ({@see self::prime_last_order_sort_cache()}), so `per_page` normally costs no order queries
         * at all. The exception is a store that hooks `woocommerce_customer_get_last_order`: priming
         * is skipped there, and every returned row pays one order query again — so `per_page` (max
         * 100), not this constant, is the bound on that path.
         *
         * @since 2.3.0
         * @access public
         * @var int
         */
        const MAX_SORTABLE_CUSTOMERS = 250;

        /**
         * Sort-key sentinel for a customer who has never ordered.
         *
         * Same width as a `DateTime::date( 'c' )` string and lexically below every real date, so it
         * orders last on `desc` and first on `asc` without needing a separator.
         *
         * @since 2.3.0
         * @access public
         * @var string
         */
        const NO_LAST_ORDER_KEY = '0000-00-00T00:00:00+00:00';

        /**
         * Property that holds the single main instance of WWP_Abilities.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Abilities
         */
        private static $_instance;

        /**
         * Wholesale roles model.
         *
         * @since 2.3.0
         * @access private
         * @var WWP_Wholesale_Roles
         */
        private $wwp_wholesale_roles;

        /**
         * Per-request memo of computed customer sort keys.
         *
         * `last_order` costs one order query per customer, and the same value is needed twice — once
         * to sort the candidate set, once to build each returned row.
         *
         * @since 2.3.0
         * @access private
         * @var array
         */
        private $sort_key_cache = array();

        /**
         * Memoised result of {@see self::should_skip_registration()}.
         *
         * Null until first evaluated. Without this the kill switch is read once per registration
         * callback, so a filter added between the two would leave the shared category registered
         * with no abilities behind it — a state siblings then register into.
         *
         * @since 2.3.0
         * @access private
         * @var bool|null
         */
        private $skip_registration;

        /**
         * WWP_Abilities constructor.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Abilities model.
         */
        public function __construct( $dependencies = array() ) {
            if ( isset( $dependencies['WWP_Wholesale_Roles'] ) ) {
                $this->wwp_wholesale_roles = $dependencies['WWP_Wholesale_Roles'];
            }
        }

        /**
         * Ensure that only one instance of WWP_Abilities is loaded or can be loaded (Singleton Pattern).
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $dependencies Array of instance objects of all dependencies of WWP_Abilities model.
         * @return WWP_Abilities
         */
        public static function instance( $dependencies = array() ) {
            if ( ! self::$_instance instanceof self ) {
                self::$_instance = new self( $dependencies );
            }

            return self::$_instance;
        }

        /**
         * Whether abilities registration is switched off.
         *
         * Because every sibling routes through this plugin's action, this doubles as a
         * suite-wide kill switch while each plugin keeps its own per-plugin switch.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool True when abilities must not be registered.
         */
        private function is_disabled() {
            if ( $this->is_disabled_by_constant() ) {
                return true;
            }

            /**
             * Filters whether Wholesale Suite abilities registration is disabled.
             *
             * Returning true suppresses WWP's own abilities, the shared category, and the
             * `wholesale_suite_abilities_init` action — and therefore every sibling plugin's
             * abilities as well.
             *
             * @since 2.3.0
             *
             * @param bool $disabled Whether abilities registration is disabled. Default false.
             */
            return (bool) apply_filters( 'wwp_disable_abilities', false );
        }

        /**
         * Whether the `WWP_DISABLE_ABILITIES` constant is switched on.
         *
         * Split out as a seam because a PHP constant cannot be undefined once set, so defining it
         * inside a test would suppress abilities for every test that followed in the same process.
         * Overriding this is how the constant branch — the documented wp-config / mu-plugin switch —
         * gets covered without poisoning the run.
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool True when the constant is defined and truthy.
         */
        protected function is_disabled_by_constant() {
            return defined( 'WWP_DISABLE_ABILITIES' ) && WWP_DISABLE_ABILITIES;
        }

        /**
         * Whether a usable Abilities API is present.
         *
         * Thin seam over {@see wwp_abilities_available()} so the "no Abilities API" degradation
         * path — one of the two paths that cannot be reached by any other means once the host
         * has the API — is reachable from the test suite.
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool True when abilities can be registered.
         */
        protected function is_available() {
            return wwp_abilities_available();
        }

        /**
         * Execute model.
         *
         * @since 2.3.0
         * @access public
         *
         * @return void
         */
        public function run() {
            /*
             * The hooks are added unconditionally and both switches are evaluated inside the
             * callbacks instead. run() executes while wp-settings.php is still including active
             * plugins, so a `wwp_disable_abilities` filter added anywhere a site owner would
             * normally add one — a theme's functions.php, a snippet plugin, any plugins_loaded
             * callback — does not exist yet at this point. Deciding here would silently ignore it.
             * The callbacks fire on init/rest_api_init, by which time every plugin and the theme
             * have loaded.
             */
            add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );

            // Priority 5 so WWP always precedes anything a sibling hooks at the default 10.
            add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ), 5 );
        }

        /**
         * Whether this request should register anything at all.
         *
         * Evaluated inside the registration callbacks rather than in {@see run()} — see the note
         * there for why the timing matters.
         *
         * @since 2.3.0
         * @access private
         *
         * @return bool True when registration must be skipped.
         */
        private function should_skip_registration() {
            if ( null === $this->skip_registration ) {
                $this->skip_registration = $this->is_disabled() || ! $this->is_available();
            }

            return $this->skip_registration;
        }

        /**
         * Register the ability category shared by every Wholesale Suite plugin.
         *
         * Guarded so that registering twice is a harmless no-op rather than a
         * `_doing_it_wrong()` notice. That keeps the provider test double sibling plugins use
         * in their own test suites safe to run unconditionally.
         *
         * @since 2.3.0
         * @access public
         *
         * @return void
         */
        public function register_category() {

            if ( $this->should_skip_registration() ) {
                return;
            }

            if ( wp_has_ability_category( self::CATEGORY ) ) {
                return;
            }

            wp_register_ability_category(
                self::CATEGORY,
                array(
                    'label'       => __( 'Wholesale Suite', 'woocommerce-wholesale-prices' ),
                    'description' => __( 'Abilities for managing wholesale pricing, customers, leads, order forms, quotes and payments.', 'woocommerce-wholesale-prices' ),
                )
            );
        }

        /**
         * Register WWP's abilities, then hand off to the rest of the suite.
         *
         * @since 2.3.0
         * @access public
         *
         * @return void
         */
        public function register_abilities() {

            if ( $this->should_skip_registration() ) {
                return;
            }

            foreach ( $this->get_ability_definitions() as $name => $args ) {
                $args['category'] = self::CATEGORY;

                wp_register_ability( self::CATEGORY . '/' . $name, $args );
            }

            /**
             * Fires after WWP has registered the shared `wholesale-suite` ability category and
             * its own abilities.
             *
             * Sibling Wholesale Suite plugins MUST register their abilities on this action
             * rather than `wp_abilities_api_init` directly. It guarantees the shared category
             * already exists — `wp_register_ability()` rejects an ability whose category is not
             * registered — and it never fires when WWP is absent, too old, or when no Abilities
             * API is available, which is what lets sibling registration degrade to a silent
             * no-op with no version checks.
             *
             * Siblings MUST call `wp_register_ability()` synchronously from their callback.
             * Core gates registration on `doing_action( 'wp_abilities_api_init' )`, which reads
             * the whole `$wp_current_filter` stack — so registering from this nested action is
             * permitted, but registration deferred to a later hook is not, and fails silently.
             *
             * @since 2.3.0
             */
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Suite-wide contract hook consumed by WWPP/WWOF/WWLC/WWQ/WWPAY; deliberately not wwp_-prefixed.
            do_action( 'wholesale_suite_abilities_init' );
        }

        /**
         * Permission check for reading a stored wholesale price.
         *
         * Wholesale prices are the plugin's whole access-control premise: they are shown only to
         * holders of a wholesale role. So `read` is not a sufficient gate — every Subscriber and
         * every WooCommerce customer holds it, and because the caller supplies `product_id` this
         * ability would otherwise let any registered user enumerate the store's entire wholesale
         * price book over REST. Shop staff may read any role's price; everyone else must hold a
         * wholesale role, and {@see self::resolve_role()} additionally confines them to their own.
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool
         */
        public function can_read_wholesale_price() {

            if ( current_user_can( 'manage_woocommerce' ) ) {
                return true;
            }

            return array() !== $this->get_own_wholesale_roles();
        }

        /**
         * The wholesale role slugs a user holds.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WP_User|null $user User to inspect. Defaults to the current user.
         * @return array List of wholesale role slugs; empty when the user holds none.
         */
        private function get_own_wholesale_roles( $user = null ) {

            if ( ! $this->wwp_wholesale_roles instanceof WWP_Wholesale_Roles ) {
                return array();
            }

            $roles = null === $user
                ? $this->wwp_wholesale_roles->getUserWholesaleRole()
                : $this->wwp_wholesale_roles->getUserWholesaleRole( $user );

            /*
             * The value passes through the public `wwp_user_wholesale_role` filter, so a third-party
             * callback can return a bare string. Indexing `[0]` on that would yield its first
             * character, so normalise to a list once, here, and never index the raw value elsewhere.
             */
            if ( empty( $roles ) ) {
                return array();
            }

            return is_array( $roles ) ? array_values( $roles ) : array( (string) $roles );
        }

        /**
         * Permission check for writing a wholesale price.
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool
         */
        public function can_manage_wholesale_price() {
            return current_user_can( 'manage_woocommerce' );
        }

        /**
         * Permission check for listing wholesale customers.
         *
         * This ability returns customer names, email addresses and spend history, so it is gated
         * on the capability WordPress uses for user administration rather than a shop capability.
         *
         * On a Multisite network install, core's `map_meta_cap()` maps `edit_users` to
         * `do_not_allow` for any user without the network-only `manage_network_users`
         * capability — by default, only super admins. Gating on `edit_users` there would deny
         * every subsite administrator and shop manager, even though they hold `manage_woocommerce`
         * and can legitimately use this ability. Substitute `manage_woocommerce` for `edit_users`
         * on a network install only; the single-site gate (and its PII-protection intent) is
         * unchanged.
         *
         * @since 2.3.0
         * @access public
         *
         * @return bool
         */
        public function can_list_wholesale_customers() {

            if ( $this->is_network_install() ) {
                return current_user_can( 'list_users' ) && current_user_can( 'manage_woocommerce' );
            }

            /*
             * `list_users` alone is the "see the user list" capability, not the "see their contact
             * details" one — core's own /wp/v2/users withholds `email` unless the requester passes
             * `edit_user`. This ability returns email addresses and lifetime spend in bulk, so it
             * requires the editing capability too; otherwise any role granted `list_users` without
             * `edit_users` gets a customer PII and revenue export over REST. WooCommerce's
             * shop_manager holds both, so the intended audience is unaffected.
             */
            return current_user_can( 'list_users' ) && current_user_can( 'edit_users' );
        }

        /**
         * Whether this install is a Multisite network install.
         *
         * Overridable seam so both branches of {@see self::can_list_wholesale_customers()} are
         * testable on a single-site test harness (mirrors the WWLC precedent's
         * `is_network_install()` seam, wwlc#618).
         *
         * @since 2.3.0
         * @access protected
         *
         * @return bool
         */
        protected function is_network_install() {
            return is_multisite();
        }

        /**
         * Resolve and validate a wholesale role slug from ability input.
         *
         * A caller without `manage_woocommerce` is confined to the wholesale role they hold: the
         * role defaults to their own rather than to {@see self::DEFAULT_ROLE}, and asking for
         * another role is refused. Without that, the permission check on the read ability would be
         * satisfiable by any wholesale customer while still disclosing every other role's prices.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $input Ability input.
         * @return string|WP_Error Role slug, or WP_Error when the role is not a wholesale role or
         *                         not one the caller may act on.
         */
        private function resolve_role( $input ) {

            if ( ! $this->wwp_wholesale_roles instanceof WWP_Wholesale_Roles ) {
                return new WP_Error(
                    'wwp_wholesale_roles_unavailable',
                    __( 'Wholesale roles are not available.', 'woocommerce-wholesale-prices' ),
                    array( 'status' => 500 )
                );
            }

            // Named for the set it holds, not the role: administrators satisfy this too.
            $is_staff  = current_user_can( 'manage_woocommerce' );
            $own_roles = $is_staff ? array() : $this->get_own_wholesale_roles();
            $requested = isset( $input['role'] ) && '' !== $input['role']
                ? sanitize_key( $input['role'] )
                : '';

            if ( '' === $requested ) {
                // The pricing engine itself uses index 0, so the default matches it.
                $role = $own_roles ? (string) $own_roles[0] : self::DEFAULT_ROLE;
            } else {
                $role = $requested;
            }

            /*
             * Confined against EVERY role the caller holds — a user can legitimately hold several,
             * and getUserWholesaleRole() returns them in $user->roles order, which is arbitrary.
             * This also fails closed for a caller holding none: in_array() against an empty list is
             * always false, so no separate no-roles branch is needed (one was added and removed —
             * it was unreachable, and its test passed with the code deleted).
             */
            if ( ! $is_staff && ! in_array( $role, $own_roles, true ) ) {
                return new WP_Error(
                    'wwp_forbidden_wholesale_role',
                    __( 'You cannot access another wholesale role\'s pricing.', 'woocommerce-wholesale-prices' ),
                    array( 'status' => 403 )
                );
            }

            $registered_check = $this->ensure_registered_wholesale_role( $role );
            if ( is_wp_error( $registered_check ) ) {
                return $registered_check;
            }

            return $role;
        }

        /**
         * Confirm a slug is a currently-registered wholesale role.
         *
         * Only the "does this role exist" existence check is shared between the callers — the
         * price abilities ({@see self::resolve_role()}) and the customer list
         * ({@see self::execute_list_wholesale_customers()}). Each keeps its own distinct
         * confinement/authorization logic around this check; this extracts just the duplicated
         * registry lookup and its identical `wwp_invalid_wholesale_role` (400) response.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $role Wholesale role slug to check.
         * @return true|WP_Error True when registered; a 400 WP_Error otherwise.
         */
        private function ensure_registered_wholesale_role( $role ) {

            $registered = $this->wwp_wholesale_roles instanceof WWP_Wholesale_Roles
                ? $this->wwp_wholesale_roles->getAllRegisteredWholesaleRoles()
                : array();

            if ( ! is_array( $registered ) || ! isset( $registered[ $role ] ) ) {
                return new WP_Error(
                    'wwp_invalid_wholesale_role',
                    sprintf(
                        /* translators: %s: wholesale role slug. */
                        __( '"%s" is not a registered wholesale role.', 'woocommerce-wholesale-prices' ),
                        $role
                    ),
                    array( 'status' => 400 )
                );
            }

            return true;
        }

        /**
         * Hydrate a product from ability input.
         *
         * @since 2.3.0
         * @access private
         *
         * @param array $input Ability input.
         * @return WC_Product|WP_Error
         */
        private function resolve_product( $input ) {

            $product_id = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0;
            $product    = $product_id ? wc_get_product( $product_id ) : false;

            /*
             * Object-level authorization, not just "is it a product". `wc_get_product()` happily
             * returns draft, pending, private and trashed products, and the read ability admits any
             * holder of a wholesale role — so without this a wholesale customer could walk product
             * IDs over REST and read the name, SKU and prices of unpublished products.
             *
             * Deliberately NOT `wc_rest_check_post_permissions( 'product', 'read', $id )`: that maps
             * to `read_private_products`, which a wholesale customer does not hold, so it would
             * refuse published products too and break the ability's whole purpose.
             *
             * The 404 is reused for "exists but you may not see it" on purpose — a distinct 403
             * would confirm the product's existence to a caller who should not know.
             */
            if ( ! $product instanceof WC_Product || ! $this->is_product_readable( $product ) ) {
                return new WP_Error(
                    'wwp_product_not_found',
                    sprintf(
                        /* translators: %d: product ID. */
                        __( 'No product or variation found with ID %d.', 'woocommerce-wholesale-prices' ),
                        $product_id
                    ),
                    array( 'status' => 404 )
                );
            }

            return $product;
        }

        /**
         * Whether the current user may see this product at all.
         *
         * Published products are readable by anyone who cleared the ability's permission callback.
         * Anything else — draft, pending, private, trashed, auto-draft — requires a capability that
         * implies seeing unpublished products.
         *
         * Gates on post status only. WWPP's per-role visibility is a separate axis, enforced by
         * {@see self::is_restricted_for_role()} — see the note there for why it is not folded in here.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WC_Product $product Product to test.
         * @return bool True when the product may be disclosed to the current user.
         */
        private function is_product_readable( $product ) {
            /*
             * A variation's own post_status is not what governs its visibility — a published
             * variation of a draft parent must stay hidden — so the parent is the subject.
             */
            $subject = $product;

            if ( $product->get_parent_id() ) {
                $parent = wc_get_product( $product->get_parent_id() );

                if ( $parent instanceof WC_Product ) {
                    $subject = $parent;
                }
            }

            if ( 'publish' === $subject->get_status() ) {
                return true;
            }

            /*
             * `read_private_products` is generated by WooCommerce from the product post type's
             * `capability_type` (class-wc-install.php:2371), so PHPCS's capability sniff does not
             * know it. Shop managers and administrators hold it; a wholesale customer does not.
             */
            return current_user_can( 'read_private_products' ) // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce-generated product capability; see note above.
                || current_user_can( 'edit_post', $subject->get_id() );
        }

        /**
         * Format a user's registration date for output, or null when it is unusable.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WP_User|false $user User to read.
         * @return string|null ISO-8601 date, or null when absent or unparseable.
         */
        private function format_registered_date( $user ) {

            if ( ! $user instanceof WP_User || empty( $user->user_registered ) ) {
                return null;
            }

            $timestamp = strtotime( $user->user_registered );

            /*
             * `> 0`, not a plain truthiness check. MySQL's zero date does NOT make strtotime() return
             * false — `0000-00-00 00:00:00` parses to a large NEGATIVE timestamp (year -1), which
             * gmdate() renders as `-001-11-30T00:00:00+00:00`. So the obvious `if ( ! $timestamp )`
             * lets the garbage through in a different costume. No WordPress user can have registered
             * before 1970, so any non-positive timestamp is unusable.
             */
            return $timestamp > 0 ? gmdate( 'c', $timestamp ) : null;
        }

        /**
         * Whether Wholesale Prices Premium hides this product from this wholesale role.
         *
         * Post status ({@see self::is_product_readable()}) is one visibility axis; WWPP's per-role
         * product visibility is another. WWPP enforces its filters on catalogue queries, the single
         * product page, and the cart/checkout layers — none of which this ability goes through, since
         * it reads the stored meta directly. Without this check a wholesale customer could pass any
         * published product id and receive their own role's price for a product the storefront hides
         * from that role.
         *
         * Feature-detected, never depended on. WWP is the free plugin and must run with WWPP absent,
         * older, or newer, so this reads the instance WWPP itself wired up and confirms the predicate
         * exists before calling it — `is_product_restricted_for_wholesale_role()` arrived in WWPP
         * 2.1.1, so an older premium install simply yields no restriction rather than a fatal. That is
         * what the suite's cross-plugin release-independence rule asks for: guard on the concrete
         * thing, and degrade to correct-but-reduced behaviour when it is missing. No version compare,
         * no load-order requirement, and nothing here breaks if WWPP is never installed.
         *
         * Deliberately not folded into is_product_readable(): that answers "may this caller see this
         * post at all", which staff bypass wholesale-role rules for entirely. This answers "is this
         * product merchandised to this role", which is a different question with a different owner.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WC_Product $product Product or variation being read.
         * @param string     $role    Wholesale role slug the price is being read for.
         * @return bool True when WWPP restricts this product for this role.
         */
        private function is_restricted_for_role( $product, $role ) {

            $premium = isset( $GLOBALS['wc_wholesale_prices_premium'] ) ? $GLOBALS['wc_wholesale_prices_premium'] : null;

            if ( ! is_object( $premium ) || ! isset( $premium->wwpp_product_visibility ) ) {
                return false;
            }

            $visibility = $premium->wwpp_product_visibility;

            if ( ! is_object( $visibility )
                || ! method_exists( $visibility, 'is_product_restricted_for_wholesale_role' ) ) {
                return false;
            }

            // WWPP takes a single role slug (or false for a guest), not a list.
            return (bool) $visibility->is_product_restricted_for_wholesale_role( $product->get_id(), $role );
        }

        /**
         * Normalise a stored wholesale price for output.
         *
         * The stored value is an empty string when no wholesale price is set, which must be
         * reported as null rather than 0 — a wholesale price of 0 is a legitimate value and
         * means something different from "not set".
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $price Stored price.
         * @return float|null
         */
        private function normalize_price( $price ) {
            return '' === $price || null === $price ? null : (float) $price;
        }

        /**
         * Execute callback for `wholesale-suite/get-wholesale-price`.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $input Ability input.
         * @return array|WP_Error
         */
        public function execute_get_wholesale_price( $input ) {

            $product = $this->resolve_product( $input );
            if ( is_wp_error( $product ) ) {
                return $product;
            }

            $role = $this->resolve_role( $input );
            if ( is_wp_error( $role ) ) {
                return $role;
            }

            /*
             * Staff bypass, matching is_product_readable(): a shop manager reads any role's price for
             * any product, because WWPP's visibility filters are merchandising rules aimed at
             * customers, not an access-control boundary for the people who configure them. For
             * everyone else the 404 is reused rather than a distinct code — the same reasoning as in
             * resolve_product(), so the response cannot be used to probe which products exist.
             *
             * This gate runs BEFORE the variable/grouped branch below on purpose. That branch's 400
             * payload lists the product's child IDs, so leaving it first would let a restricted caller
             * confirm a hidden variable product exists and enumerate its variations before ever
             * reaching this check — exactly the per-role boundary is_restricted_for_role() enforces.
             */
            if ( ! current_user_can( 'manage_woocommerce' ) && $this->is_restricted_for_role( $product, $role ) ) {
                return new WP_Error(
                    'wwp_product_not_found',
                    sprintf(
                        /* translators: %d: product ID. */
                        __( 'No product or variation found with ID %d.', 'woocommerce-wholesale-prices' ),
                        $product->get_id()
                    ),
                    array( 'status' => 404 )
                );
            }

            /*
             * A parent type carries no wholesale price of its own, and get_regular_price() is '' for
             * it, so without this the response is all-nulls — indistinguishable from a simple product
             * with no wholesale price set. An assistant asked "what's our wholesale price on this?"
             * would report "none set" for every variable product in the catalogue. The write ability
             * already gives a directive error for these types; the read path must match.
             */
            if ( $product->is_type( array( 'variable', 'variable-subscription', 'grouped' ) ) ) {
                return new WP_Error(
                    'wwp_wholesale_price_on_children',
                    sprintf(
                        /* translators: %s: product type. */
                        __( 'A "%s" product does not carry its own wholesale price. Look it up on each of its variations or child products instead.', 'woocommerce-wholesale-prices' ),
                        $product->get_type()
                    ),
                    array(
                        'status'   => 400,
                        'children' => $product->get_children(),
                    )
                );
            }

            $wholesale_price = WWP_Wholesale_Prices::get_product_raw_wholesale_price( $product, array( $role ) );

            /*
             * Deliberately NOT converted here. `get_regular_price()` runs through
             * `woocommerce_product_get_regular_price`, which the Aelia Currency Switcher hooks, so
             * it already returns the active currency — unlike `get_product_raw_wholesale_price()`,
             * which reads unfiltered meta and therefore has to convert by hand. Every other place
             * WWP puts these two values side by side does the same (see
             * class-wwp-wholesale-prices.php:586 and :1955). Converting again here would multiply
             * by the FX rate twice.
             */
            return array(
                'product_id'      => $product->get_id(),
                'name'            => $product->get_name(),
                'sku'             => (string) $product->get_sku(),
                'regular_price'   => $this->normalize_price( $product->get_regular_price() ),
                'wholesale_price' => $this->normalize_price( $wholesale_price ),
                'role'            => $role,
                'currency'        => get_woocommerce_currency(),
            );
        }

        /**
         * Execute callback for `wholesale-suite/set-wholesale-price`.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $input Ability input.
         * @return array|WP_Error
         */
        public function execute_set_wholesale_price( $input ) {

            $product = $this->resolve_product( $input );
            if ( is_wp_error( $product ) ) {
                return $product;
            }

            $role = $this->resolve_role( $input );
            if ( is_wp_error( $role ) ) {
                return $role;
            }

            $product_type = $product->get_type();

            /*
             * Deliberately just these two, per #911. WWP's editor also offers the field for
             * subscription types when WooCommerce Subscriptions is active, so widening to them is
             * defensible — but it is a scope decision for its own issue, not for this one, and it
             * needs Subscriptions installed to test. Keep the two lists below in step: a type that is
             * refused here must not be named as a destination by the error message.
             */
            $writable_types = array( 'simple', 'variation' );

            if ( ! in_array( $product_type, $writable_types, true ) ) {
                /*
                 * "Set it on the variations instead" is only true for a type whose children this
                 * ability will actually accept. `variable` children are `variation` and `grouped`
                 * children are simple, so both qualify; `variable-subscription` does not, because its
                 * children are `subscription_variation` and the allowlist above refuses those — the
                 * caller would be sent in a circle.
                 */
                $has_children = $product->is_type( array( 'variable', 'grouped' ) );

                return new WP_Error(
                    'wwp_unsupported_product_type',
                    $has_children
                        ? sprintf(
                            /* translators: %s: product type. */
                            __( 'A wholesale price cannot be set on a "%s" product directly. Set it on each of its variations or child products instead.', 'woocommerce-wholesale-prices' ),
                            $product_type
                        )
                        : sprintf(
                            /* translators: %s: product type. */
                            __( 'A wholesale price can only be set on a simple product or a product variation. This product is a "%s".', 'woocommerce-wholesale-prices' ),
                            $product_type
                        ),
                    array( 'status' => 400 )
                );
            }

            $price = array_key_exists( 'price', $input ) ? $input['price'] : null;

            if ( null !== $price ) {
                if ( ! is_numeric( $price ) ) {
                    return new WP_Error(
                        'wwp_invalid_wholesale_price',
                        __( 'The wholesale price must be a number, or null to clear it.', 'woocommerce-wholesale-prices' ),
                        array( 'status' => 400 )
                    );
                }

                if ( (float) $price < 0 ) {
                    return new WP_Error(
                        'wwp_invalid_wholesale_price',
                        __( 'The wholesale price cannot be negative.', 'woocommerce-wholesale-prices' ),
                        array( 'status' => 400 )
                    );
                }
            }

            $currency_conflict = $this->detect_currency_basis_conflict( $product );
            if ( is_wp_error( $currency_conflict ) ) {
                return $currency_conflict;
            }

            $previous_price = $this->normalize_price( $product->get_meta( $role . '_wholesale_price', true ) );

            $this->write_wholesale_price( $product, $role, $price, $product_type );

            /*
             * Re-read from the database rather than from $product. Saving fires
             * woocommerce_update_product, and handlers on it — WWPP's percentage sync, the
             * product-category fallback — can legitimately write a different value. Reporting the
             * in-memory object would echo back what was asked for and hide that, which is the one
             * thing an API response here must not do.
             */
            $saved  = wc_get_product( $product->get_id() );
            $stored = $saved instanceof WC_Product
                ? $saved->get_meta( $role . '_wholesale_price', true )
                : $product->get_meta( $role . '_wholesale_price', true );

            return array(
                'product_id'      => $product->get_id(),
                'role'            => $role,
                'wholesale_price' => $this->normalize_price( $stored ),
                'previous_price'  => $previous_price,
            );
        }

        /**
         * Refuse a write whose currency basis is ambiguous.
         *
         * This ability always writes `{role}_wholesale_price` — the BASE-currency key. The read
         * ability, by contrast, returns whatever
         * {@see WWP_Wholesale_Prices::get_product_raw_wholesale_price()} produces, which converts to
         * the ACTIVE currency when the Aelia Currency Switcher is running and the active currency is
         * not the product's base. Individually both are correct; composed they are not. An assistant
         * told to "raise wholesale prices by 10%" reads 120 EUR, multiplies, and writes 132 into a
         * USD field — silently wrong by the FX rate, with a 200 response.
         *
         * Failing closed rather than converting is deliberate. A conversion here is exactly the fix an
         * earlier review round got wrong, and there is no way to know whether the caller's number is
         * meant as base or active currency — so the ambiguity is reported instead of guessed. Stores
         * without Aelia never reach this, and stores with it are unaffected while displaying base
         * currency, which is the normal admin case.
         *
         * Uses the product's own id, matching what the read path passes to the same helper, so the two
         * agree about which product's base currency is in question.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WC_Product $product Product being written to.
         * @return WP_Error|null WP_Error when the basis is ambiguous, null when it is safe to write.
         */
        private function detect_currency_basis_conflict( $product ) {

            // No class_exists() guard — see the note in write_wholesale_price(); the helper is WWP's
            // own class, required unconditionally by the plugin bootstrap.
            if ( ! WWP_ACS_Integration_Helper::aelia_currency_switcher_active() ) {
                return null;
            }

            $active_currency = get_woocommerce_currency();
            $base_currency   = WWP_ACS_Integration_Helper::get_product_base_currency( $product->get_id() );

            if ( empty( $base_currency ) || $active_currency === $base_currency ) {
                return null;
            }

            return new WP_Error(
                'wwp_currency_basis_ambiguous',
                sprintf(
                    /* translators: 1: active currency code, 2: the product's base currency code. */
                    __( 'The wholesale price is stored against this product\'s base currency (%2$s), but the store is currently displaying %1$s. Reading and writing would use different currencies, so the write was refused. Switch the store to %2$s before setting the price.', 'woocommerce-wholesale-prices' ),
                    $active_currency,
                    $base_currency
                ),
                array(
                    'status'          => 409,
                    'active_currency' => $active_currency,
                    'base_currency'   => $base_currency,
                )
            );
        }

        /**
         * Write a wholesale price to a product.
         *
         * Must leave the same persisted end state as
         * `WWP_Admin_Custom_Fields_Simple_Product::_save_wholesale_price_fields()` (and, for
         * variations, `WWP_Admin_Custom_Fields_Variable_Product`). Those are `private` and read
         * straight from `$_POST`, so they cannot be reused, and they are deliberately left
         * untouched — a hot path with recent decimal-separator fixes (#955, #1002). Parity is
         * therefore enforced by test rather than by structure.
         *
         * How to check parity — do this, rather than trusting a list in a comment, because such a
         * list has to be maintained and silently rots:
         *
         * 1. Diff the meta keys written and deleted here against that method.
         * 2. Confirm the pre-save filter is fired with the same argument count (6 for simple,
         *    7 for variation) — a short list is a fatal for any callback using the documented
         *    signature, not a degraded call.
         * 3. Confirm `wwp_set_have_wholesale_price_meta_prod_cat_wholesale_discount` fires on the
         *    same side of `save()`; moving it inverts the end state when WWPP is active.
         * 4. Every OTHER action the editor fires is deliberately NOT replicated. In particular
         *    `wwp_after_save_variable_product_wholesale_price` must never fire from here: its only
         *    consumer (WWPP's WC Vendors integration, `WWPP_WC_Vendors::save_variations()`) reads
         *    `$_POST`, so firing it outside a form submit sees no posted price and writes
         *    `{role}_have_wholesale_price = 'no'`, disabling the price this method just wrote. The
         *    stored price itself survives, which is worse than losing it: the product looks priced in
         *    the editor while the storefront shows retail. "Restore parity" is not a licence to fire it.
         *
         * @since 2.3.0
         * @access private
         *
         * @param WC_Product $product      Product to write to.
         * @param string     $role         Wholesale role slug.
         * @param float|null $price        Price to store, or null to clear.
         * @param string     $product_type Product type, for the pre-save filter.
         * @return void
         */
        private function write_wholesale_price( $product, $role, $price, $product_type ) {
            /*
             * The project's designated price sanitizer, not wc_format_decimal() directly — it is what
             * both editor save paths call (#955, #1002 fixed decimal-separator handling inside it), so
             * sharing it is what keeps this path from drifting the next time a separator bug is fixed
             * there. It returns '' for null, so no separate clear branch is needed.
             */
            $wholesale_price = WWP_Helper_Functions::sanitize_price_input( $price );

            /*
             * These filters are pre-existing public contracts, and the argument list is part of the
             * contract: WWP's own editor fires the simple filter with 6 arguments and the variation
             * filter with 7, and WWPP fires the variation one with 7 too. A callback declared to
             * that documented signature receives an ArgumentCountError — a fatal on the write path —
             * if this fires with fewer, because WP_Hook passes every argument it is given whenever
             * the callback's accepted_args is greater than or equal to the count supplied.
             *
             * This path always writes the base-currency key, so $is_base_currency is true and there
             * is no per-currency code to pass.
             */
            // No class_exists() guard: WWP_ACS_Integration_Helper is WWP's own class, required
            // unconditionally by the plugin bootstrap. WWP_Wholesale_Prices::get_product_raw_wholesale_price()
            // calls the same helper without one.
            $aelia_active = WWP_ACS_Integration_Helper::aelia_currency_switcher_active();

            $is_variation = 'variation' === $product_type;

            if ( $is_variation ) {
                /** This filter is documented in includes/admin-custom-fields/products/class-wwp-admin-custom-fields-variable-product.php */
                $wholesale_price = apply_filters(
                    'wwp_before_save_variation_product_wholesale_price',
                    $wholesale_price,
                    $role,
                    $product->get_id(),
                    $product->get_parent_id(),
                    $aelia_active,
                    true,
                    null
                );
            } else {
                /** This filter is documented in includes/admin-custom-fields/products/class-wwp-admin-custom-fields-simple-product.php */
                $wholesale_price = apply_filters(
                    'wwp_before_save_' . $product_type . '_product_wholesale_price',
                    $wholesale_price,
                    $role,
                    $product->get_id(),
                    $aelia_active,
                    true,
                    null
                );
            }

            $wholesale_price = wc_clean( $wholesale_price );

            $product->update_meta_data( $role . '_wholesale_price', $wholesale_price );

            /*
             * The editor drops the percentage discount whenever an explicit price governs the role.
             * Leaving it behind lets WWPP's percentage sync — hooked on woocommerce_update_product
             * at priority 99 — recompute the price from the surviving percentage during the save
             * below and overwrite the value the caller just asked for, while this ability still
             * returns 200 with the requested figure.
             */
            $product->delete_meta_data( $role . '_wholesale_percentage_discount' );

            if ( $is_variation ) {
                /*
                 * A variation carries only its own price. The have-price flag, the min/max range and
                 * the category-discount fallback all live on the PARENT — the variable editor writes
                 * none of them on the variation itself — so they are handled by
                 * sync_variable_parent() below, after this save.
                 */
                $product->save();

                $this->sync_variable_parent( $product->get_parent_id() );

                return;
            }

            // Discounts set at product-category level no longer apply once an explicit price is
            // written, matching the product editor.
            $product->delete_meta_data( $role . '_have_wholesale_price_set_by_product_cat' );

            $has_wholesale_price = is_numeric( $wholesale_price ) && $wholesale_price > 0;

            $product->update_meta_data( $role . '_have_wholesale_price', $has_wholesale_price ? 'yes' : 'no' );

            /*
             * Ordering matches the editor: the action fires BEFORE save(), so that this save is what
             * persists the end state. Firing it after save() instead inverts the result — WWPP's
             * handler re-fetches and saves the product itself, so it would win, and clearing a price
             * would leave a product in a discounted category still flagged as wholesale-priced.
             */
            if ( ! $has_wholesale_price ) {
                do_action( 'wwp_set_have_wholesale_price_meta_prod_cat_wholesale_discount', $product->get_id(), $role );
            }

            $product->save();
        }

        /**
         * Rebuild a variable parent's wholesale meta from its variations.
         *
         * Writing a variation's price is only half the operation: the storefront reads the have-price
         * flag, the min/max range and the list of priced variations from the PARENT — the flag and the
         * priced-variation list in
         * `WWP_Wholesale_Prices_For_Non_Wholesale_Customers::wholesale_price_html_filter()`, the range
         * in the price-range filter query in `WWP_Wholesale_Prices`. Method names rather than line
         * numbers on purpose: the line numbers drifted and were corrected twice, so they were the
         * defect rather than the documentation. Without this, a price set through the ability is stored
         * but never displayed, and a price cleared through it leaves the parent advertising a range it
         * no longer has.
         *
         * Mirrors `WWP_Admin_Custom_Fields_Variable_Product::save_variable_product_wholesale_price()`
         * (`:650-682`), including firing the category-discount fallback with the PARENT id — the
         * variation id would make WWPP's handler look up `product_cat` terms on a variation, find
         * none, and flag the variation instead.
         *
         * Recomputed from all children rather than adjusted incrementally, so it is correct whichever
         * variation changed and self-heals a parent left stale by an earlier write.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int $parent_id Variable parent product ID.
         * @return void
         */
        private function sync_variable_parent( $parent_id ) {

            $parent = $parent_id ? wc_get_product( $parent_id ) : null;

            if ( ! $parent instanceof WC_Product ) {
                return;
            }

            $children = $parent->get_children();

            /*
             * A variation whose parent reports no children means the parent's `_children` meta is
             * stale. Syncing from an empty set would clear a range that is still correct, so leave the
             * parent untouched — it needs a product re-save to rebuild `_children` first. Both this and
             * the roles check below leave the variation's own price persisted and reported while the
             * parent stays unsynced; each requires pre-existing data inconsistency, so neither is
             * reachable on a healthy store.
             */
            if ( empty( $children ) ) {
                return;
            }

            $wholesale_roles = $this->wwp_wholesale_roles instanceof WWP_Wholesale_Roles
                ? $this->wwp_wholesale_roles->getAllRegisteredWholesaleRoles()
                : array();

            /*
             * No registered wholesale roles means there is nothing to recompute. Reachable only if a
             * third party filters `wwp_registered_wholesale_roles` down to nothing, in which case no
             * role's price is displayable anyway.
             */
            if ( empty( $wholesale_roles ) ) {
                return;
            }

            /*
             * One query for every child's meta. WooCommerce's data store reads product meta through
             * a direct $wpdb query, so nothing below has primed WP's meta cache — without this the
             * per-child/per-role get_post_meta() loop costs a query each (100 variations x 5 roles).
             */
            update_meta_cache( 'post', $children );

            /*
             * One pass over the children, building the per-role price lists and the per-role
             * priced-child lists together. This previously called
             * WWP_Helper_Functions::get_wholesale_prices_per_role_from_variations() for the prices and
             * then looped the children again for the ids, so every child's meta was read twice and the
             * helper additionally hydrated a full WC_Product per child. Because the resync runs on
             * every single-variation write, a 100-variation product cost 100 hydrations + 500 meta
             * reads per call — and pricing all 100 variations through the ability cost 10,000
             * hydrations. The editor pays it once per form submit, for every variation at once.
             *
             * Reads with get_post_meta() against the cache primed above. The helper read through
             * WC_Data::get_meta(), which applies the dynamic `woocommerce_product_get_{$meta_key}`
             * filter; no consumer of that filter for a `{role}_wholesale_price` key exists anywhere in
             * the suite (checked across wws/, ac/, wcv/ and wishlist/), and the priced-child list
             * already used get_post_meta(), so this makes the two reads consistent rather than
             * introducing a divergence. Values stay `(float)` and the iteration order stays
             * children-outer/roles-inner, matching the helper exactly.
             */
            $prices_per_role = array();
            $priced_children = array();

            foreach ( $children as $child_id ) {
                foreach ( array_keys( $wholesale_roles ) as $role_key ) {
                    $child_price = get_post_meta( $child_id, $role_key . '_wholesale_price', true );

                    if ( is_numeric( $child_price ) && $child_price > 0 ) {
                        $prices_per_role[ $role_key ][] = (float) $child_price;
                        $priced_children[ $role_key ][] = $child_id;
                    }
                }
            }

            /*
             * Clear the category-discount marker for every role up front, and directly, so that the
             * only `set_by_product_cat = 'yes'` left standing afterwards is one the fallback below
             * re-adds. A direct delete rather than $parent->delete_meta_data() on purpose: the parent
             * is re-read after the fallback runs (see below), which would discard a pending change on
             * the in-memory object. The editor clears this key the same way before firing the fallback
             * (WWP_Admin_Custom_Fields_Variable_Product::save_wholesale_price_fields()).
             */
            foreach ( array_keys( $wholesale_roles ) as $role_key ) {
                delete_post_meta( $parent_id, $role_key . '_have_wholesale_price_set_by_product_cat' );
            }

            /*
             * Fire the category-discount fallback FIRST, for every priced role, exactly as the editor
             * does — and BEFORE this method writes the parent's own have/min/max below.
             *
             * The handler on this action (WWPP) loads its OWN copy of the parent, writes
             * `{role}_have_wholesale_price`, and calls save() on that copy. Our $parent was loaded
             * before the handler ran, so it does not know about that write: if it then wrote the same
             * key and saved, WooCommerce would ADD a second postmeta row rather than update the
             * handler's, leaving two `{role}_have_wholesale_price` rows. get_post_meta() returns the
             * lower meta_id — the handler's stale 'no' — so the price would not display for the role.
             * That is issue #1020, and it is why the parent is re-read below before its own writes.
             *
             * Parent id, as the editor does — a variation has no product_cat terms.
             */
            $fallback_fired = false;

            foreach ( array_keys( $wholesale_roles ) as $role_key ) {
                if ( ! empty( $prices_per_role[ $role_key ] ) ) {
                    do_action( 'wwp_set_have_wholesale_price_meta_prod_cat_wholesale_discount', $parent_id, $role_key );
                    $fallback_fired = true;
                }
            }

            /*
             * Re-read the parent so the writes below UPDATE the rows the fallback handler just saved,
             * rather than adding duplicates (issue #1020). Only needed when the fallback ran; when it
             * did not, nothing has written the parent since it was loaded.
             */
            if ( $fallback_fired ) {
                $parent = wc_get_product( $parent_id );

                if ( ! $parent instanceof WC_Product ) {
                    return;
                }
            }

            foreach ( array_keys( $wholesale_roles ) as $role_key ) {

                $parent->delete_meta_data( $role_key . '_variations_with_wholesale_price' );

                if ( ! empty( $priced_children[ $role_key ] ) ) {
                    foreach ( $priced_children[ $role_key ] as $child_id ) {
                        $parent->add_meta_data( $role_key . '_variations_with_wholesale_price', $child_id );
                    }
                }

                if ( ! empty( $prices_per_role[ $role_key ] ) ) {
                    /*
                     * An explicit variation price governs the role, so it is wholesale-priced whatever
                     * the fallback decided. Assert 'yes' over the value the handler wrote — because the
                     * parent was re-read above this UPDATES the handler's row instead of duplicating it.
                     */
                    $parent->update_meta_data( $role_key . '_have_wholesale_price', 'yes' );
                    $parent->update_meta_data( $role_key . '_min_wholesale_price', min( $prices_per_role[ $role_key ] ) );
                    $parent->update_meta_data( $role_key . '_max_wholesale_price', max( $prices_per_role[ $role_key ] ) );
                } else {
                    $parent->update_meta_data( $role_key . '_have_wholesale_price', 'no' );
                    $parent->delete_meta_data( $role_key . '_min_wholesale_price' );
                    $parent->delete_meta_data( $role_key . '_max_wholesale_price' );
                }
            }

            $parent->save();
        }

        /**
         * Execute callback for `wholesale-suite/list-wholesale-customers`.
         *
         * @since 2.3.0
         * @access public
         *
         * @param array $input Ability input.
         * @return array|WP_Error
         */
        public function execute_list_wholesale_customers( $input ) {

            $page     = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
            $per_page = isset( $input['per_page'] ) ? min( 100, max( 1, absint( $input['per_page'] ) ) ) : 25;

            /*
             * The schema's enum is enforced by core for REST callers, but an execute callback is also
             * reachable directly (WP-CLI, another plugin, a test), where nothing has validated it. An
             * unknown value used to coerce silently to `registered`, answering 200 with an ordering the
             * caller did not ask for; say so instead.
             */
            $orderby       = isset( $input['orderby'] ) ? sanitize_key( $input['orderby'] ) : 'date_registered';
            $sortable_keys = array( 'date_registered', 'display_name', 'last_order', 'lifetime_spend' );

            if ( ! in_array( $orderby, $sortable_keys, true ) ) {
                return new WP_Error(
                    'wwp_invalid_orderby',
                    sprintf(
                        /* translators: 1: requested orderby value, 2: comma-separated list of accepted values. */
                        __( '"%1$s" is not a field this list can be ordered by. Accepted values: %2$s.', 'woocommerce-wholesale-prices' ),
                        $orderby,
                        implode( ', ', $sortable_keys )
                    ),
                    array( 'status' => 400 )
                );
            }

            $order  = isset( $input['order'] ) && 'asc' === strtolower( $input['order'] ) ? 'asc' : 'desc';
            $search = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

            $filtered_role = '';

            if ( isset( $input['role'] ) && '' !== $input['role'] ) {
                /*
                 * Validate that the role exists, but do NOT reuse resolve_role() here. That method
                 * confines a non-`manage_woocommerce` caller to the wholesale role they personally
                 * hold, which is correct for the price abilities and wrong for this one: its gate is
                 * `list_users` plus `edit_users` (`manage_woocommerce` on a network install — see
                 * {@see self::can_list_wholesale_customers()}), so a user-manager who may
                 * legitimately list EVERY wholesale role would be 403'd the moment they narrowed the
                 * request — and with an error about pricing, a surface this ability does not expose.
                 */
                $requested        = sanitize_key( $input['role'] );
                $registered_check = $this->ensure_registered_wholesale_role( $requested );

                if ( is_wp_error( $registered_check ) ) {
                    return $registered_check;
                }

                $roles         = array( $requested );
                $filtered_role = $requested;
            } else {
                $roles = $this->wwp_wholesale_roles instanceof WWP_Wholesale_Roles
                    ? array_keys( $this->wwp_wholesale_roles->getAllRegisteredWholesaleRoles() )
                    : array();
            }

            if ( empty( $roles ) ) {
                return array(
                    'customers'  => array(),
                    // Same shape as the main return, so the ability never emits two pagination
                    // schemas.
                    'pagination' => array(
                        'page'        => $page,
                        'per_page'    => $per_page,
                        'total'       => 0,
                        'total_pages' => 0,
                        'truncated'   => false,
                    ),
                );
            }

            $query_args = array(
                'role__in' => $roles,
                'fields'   => 'ID',
            );

            if ( '' !== $search ) {
                $query_args['search']         = '*' . $search . '*';
                $query_args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
            }

            $sort_in_php = in_array( $orderby, array( 'last_order', 'lifetime_spend' ), true );
            $truncated   = false;

            if ( $sort_in_php ) {
                // Neither field lives on the user record, so the whole set has to be measured
                // before it can be ordered. Bounded by MAX_SORTABLE_CUSTOMERS.
                $total_query = new WP_User_Query( array_merge( $query_args, array( 'number' => 1 ) ) );
                $total       = (int) $total_query->get_total();

                /*
                 * `static::`, not `self::` — late static binding is deliberate here so a subclass can
                 * shrink the window. The test suite overrides it to 2 to reach the truncation branch
                 * without seeding hundreds of users; `self::` would silently ignore the override.
                 */
                $query_args['number'] = static::MAX_SORTABLE_CUSTOMERS;
                // ID as secondary key: rows sharing a user_registered value (bulk imports, scripted
                // signups) otherwise have no guaranteed order across queries.
                $query_args['orderby'] = array(
                    'registered' => 'DESC',
                    'ID'         => 'DESC',
                );

                $truncated = $total > static::MAX_SORTABLE_CUSTOMERS;

                $user_ids = ( new WP_User_Query( $query_args ) )->get_results();

                /*
                 * One users query plus one usermeta query for the whole candidate set, instead of
                 * two per row from get_userdata() inside the loop.
                 */
                cache_users( $user_ids );

                /*
                 * Sorting needs only the one field being sorted on, so compute a cheap sort key for
                 * every candidate and build the full row (which costs an order query each) only for
                 * the slice actually returned.
                 */
                $keys = array();
                foreach ( $user_ids as $candidate_id ) {
                    /*
                     * The user ID is appended to make the key total: asort()/arsort() are not stable
                     * on PHP 7.4 — this plugin's declared minimum — and ties are the common case here
                     * (every customer who has never ordered keys identically), so without a
                     * tiebreaker two requests can order the tied block differently and the same
                     * customer appears on two pages while another is dropped.
                     *
                     * Every key from customer_sort_key() is FIXED WIDTH, which is what makes plain
                     * concatenation safe. An earlier version joined with '|' instead: chr(124) sorts
                     * above every digit, so a customer with no orders (empty key) became the LARGEST
                     * key and `orderby=last_order&order=desc` — "who bought most recently" — returned
                     * the never-ordered accounts first.
                     */
                    $keys[ (int) $candidate_id ] = $this->customer_sort_key( (int) $candidate_id, $orderby )
                        . sprintf( '%010d', (int) $candidate_id );
                }

                if ( 'desc' === $order ) {
                    arsort( $keys, SORT_NATURAL );
                } else {
                    asort( $keys, SORT_NATURAL );
                }

                $pageable_total = count( $keys );
                $page_ids       = array_slice( array_keys( $keys ), ( $page - 1 ) * $per_page, $per_page );

                $this->prime_last_order_sort_cache( $page_ids );

                $customers = array_map(
                    function ( $id ) use ( $filtered_role ) {
                        return $this->build_customer_row( $id, $filtered_role );
                    },
                    $page_ids
                );
            } else {
                $direction = 'asc' === $order ? 'ASC' : 'DESC';
                $primary   = 'display_name' === $orderby ? 'display_name' : 'registered';
                // Same determinism requirement as the PHP-sorted branch — this one pages in SQL.
                $query_args['orderby'] = array(
                    $primary => $direction,
                    'ID'     => $direction,
                );
                $query_args['number']  = $per_page;
                $query_args['paged']   = $page;

                $query          = new WP_User_Query( $query_args );
                $total          = (int) $query->get_total();
                $pageable_total = $total; // SQL paginates the whole set on this branch.
                $page_ids       = $query->get_results();

                cache_users( $page_ids );
                $this->prime_last_order_sort_cache( $page_ids );

                $customers = array_map(
                    function ( $id ) use ( $filtered_role ) {
                        return $this->build_customer_row( $id, $filtered_role );
                    },
                    $page_ids
                );
            }

            return array(
                'customers'  => array_values( $customers ),
                'pagination' => array(
                    'page'        => $page,
                    'per_page'    => $per_page,

                    /*
                     * When truncated, `total` must describe the set that is actually pageable, not
                     * the store-wide count — that would advertise pages returning an empty array
                     * (1,200 customers at 25/page claims 48 pages while only 20 have rows).
                     * `truncated` is what tells the caller the set is a subset, and its schema
                     * description states the window size so a caller can tell how much was left out.
                     */
                    'total'       => $pageable_total,
                    'total_pages' => (int) ceil( $pageable_total / $per_page ),
                    'truncated'   => $truncated,
                ),
            );
        }

        /**
         * Prime the `last_order` sort-key memo for a page of customers with one aggregate query.
         *
         * `build_customer_row()` unconditionally reads the `last_order` sort key to populate
         * `last_order_date`, but {@see customer_sort_key()}'s per-request memo is only warm for the
         * field the *outer* sort ran on. Left alone, every non-`last_order` sort (the default
         * `date_registered`, plus `display_name` and `lifetime_spend`) would fall through to
         * {@see compute_customer_sort_key()} once per row — up to `per_page` (100) uncached
         * `wc_get_customer_last_order()` order queries per request.
         *
         * This runs ONE HPOS-aware aggregate query for the whole page instead and writes the result
         * straight into the same memo `customer_sort_key()` reads, so `build_customer_row()`'s call
         * is always a cache hit for these IDs afterwards. IDs already warm (the `last_order` sort
         * path primed the full candidate set already) are skipped, so this is a no-op — zero extra
         * queries — on that path.
         *
         * `wc_get_customer_last_order()` (the per-customer lookup this replaces) does not pick the
         * order with the latest `date_created` — it picks the order with the HIGHEST ID for that
         * customer, then reports THAT order's date. Those two disagree whenever a customer's
         * highest-ID order does not also carry the latest date (a backdated admin order, a CSV
         * import). A plain `MAX(date_created)` would silently diverge from the per-customer value in
         * exactly that case, so each branch below first finds the MAX(id) per customer (matching the
         * per-customer lookup's own selection) and only then reads that specific order's date.
         *
         * This matches `wc_get_customer_last_order()`'s own SQL fallback exactly, but it is NOT a
         * full replacement for that function: it queries the orders tables directly and does not
         * consult the `wc_last_order` usermeta cache or apply WooCommerce's
         * `woocommerce_customer_get_last_order` filter. Rather than let that divergence leak to a
         * store that relies on the filter, this method skips priming entirely whenever anything is
         * hooked to it — every row then falls through {@see customer_sort_key()}'s cache miss to
         * {@see compute_customer_sort_key()}'s per-customer `wc_get_customer_last_order()` call,
         * which DOES apply the filter, so a hooked store always sees the same value this ability
         * would have reported before batching existed. The aggregate-query optimisation only takes
         * effect when nothing is listening to that filter — the common case.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int[] $user_ids Page of candidate user IDs.
         * @return void
         */
        private function prime_last_order_sort_cache( array $user_ids ) {

            if ( has_filter( 'woocommerce_customer_get_last_order' ) ) {
                return;
            }

            $needed = array();

            foreach ( $user_ids as $user_id ) {
                $user_id   = (int) $user_id;
                $cache_key = $this->sort_key_cache_key( 'last_order', $user_id );

                if ( ! isset( $this->sort_key_cache[ $cache_key ] ) ) {
                    $needed[] = $user_id;
                }
            }

            if ( empty( $needed ) ) {
                return;
            }

            global $wpdb;

            /*
             * Same status set wc_get_customer_last_order() honours (every registered order status,
             * not only the paid ones) so the batched value matches the per-customer lookup exactly.
             */
            $statuses            = array_keys( wc_get_order_statuses() );
            $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $id_placeholders     = implode( ',', array_fill( 0, count( $needed ), '%d' ) );
            $dates               = array();

            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- {$id_placeholders}/{$status_placeholders}/{$id_string_placeholders} are %d/%s placeholder lists built dynamically per row count, so the sniff's static placeholder count can't see them; they are bound via $wpdb->prepare()'s args below, never raw values.
            if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT sub.customer_id AS customer_id, o.date_created_gmt AS last_order_date
                        FROM (
                            SELECT customer_id, MAX(id) AS max_id
                            FROM {$wpdb->prefix}wc_orders
                            WHERE customer_id IN ({$id_placeholders})
                            AND status IN ({$status_placeholders})
                            GROUP BY customer_id
                        ) AS sub
                        INNER JOIN {$wpdb->prefix}wc_orders AS o ON o.id = sub.max_id",
                        array_merge( $needed, $statuses )
                    )
                );
            } else {
                // meta.meta_value is a varchar column: an unquoted integer list would make MySQL
                // coerce every _customer_user row in the table to compare it, defeating the index
                // this branch exists to use — so, unlike the HPOS branch's real bigint column, the
                // IDs here are bound as strings (%s), not %d.
                $id_string_placeholders = implode( ',', array_fill( 0, count( $needed ), '%s' ) );

                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT sub.customer_id AS customer_id, posts.post_date_gmt AS last_order_date
                        FROM (
                            SELECT meta.meta_value AS customer_id, MAX(posts.ID) AS max_id
                            FROM {$wpdb->posts} AS posts
                            INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
                            WHERE meta.meta_key = '_customer_user'
                            AND meta.meta_value IN ({$id_string_placeholders})
                            AND posts.post_type = 'shop_order'
                            AND posts.post_status IN ({$status_placeholders})
                            GROUP BY meta.meta_value
                        ) AS sub
                        INNER JOIN {$wpdb->posts} AS posts ON posts.ID = sub.max_id",
                        array_merge( array_map( 'strval', $needed ), $statuses )
                    )
                );
            }
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

            foreach ( (array) $rows as $row ) {
                $dates[ (int) $row->customer_id ] = $row->last_order_date;
            }

            foreach ( $needed as $user_id ) {
                $cache_key = $this->sort_key_cache_key( 'last_order', $user_id );

                /*
                 * `'0000-00-00 00:00:00'` is not empty() and DateTime doesn't throw on it (it parses
                 * to year -1), so it must be checked explicitly — same zero-date class
                 * build_customer_row()'s date_registered handling already guards against.
                 */
                if ( empty( $dates[ $user_id ] ) || '0000-00-00 00:00:00' === $dates[ $user_id ] ) {
                    $this->sort_key_cache[ $cache_key ] = self::NO_LAST_ORDER_KEY;
                    continue;
                }

                // Both source columns (`date_created_gmt`, `post_date_gmt`) are already UTC.
                $this->sort_key_cache[ $cache_key ] = $this->last_order_sort_key( $dates[ $user_id ] );
            }
        }

        /**
         * Build the per-request sort-key memo key for one candidate/field pair.
         *
         * The single source of truth for this format — {@see prime_last_order_sort_cache()} and
         * {@see customer_sort_key()} both write/read through it, so the two can never drift apart.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string $orderby Either `lifetime_spend` or `last_order`.
         * @param int    $user_id User ID.
         * @return string
         */
        private function sort_key_cache_key( $orderby, $user_id ) {
            return $orderby . ':' . (int) $user_id;
        }

        /**
         * Render a `last_order` sort key from a UTC datetime string.
         *
         * The single source of truth for this format — {@see prime_last_order_sort_cache()} (from a
         * raw `date_created_gmt`/`post_date_gmt` column value) and {@see compute_customer_sort_key()}
         * (from a `WC_DateTime`) both route through it, so the batched and per-customer sort keys can
         * never render differently and silently mis-order the `arsort()` in
         * {@see execute_list_wholesale_customers()}.
         *
         * Two properties matter here. The no-order sentinel is FIXED WIDTH and below every real date,
         * so the key stays sortable once the user ID is appended — an empty string is what made the
         * earlier '|'-joined key invert the ordering. And the date is rendered in UTC, not site time,
         * so two orders inside a DST transition window sort by instant, not local wall time — matching
         * the `date_registered` reported beside it.
         *
         * @since 2.3.0
         * @access private
         *
         * @param string|null $utc_datetime `Y-m-d H:i:s` (or any format {@see DateTime} accepts) in
         *                                  UTC, or an empty/zero-date value.
         * @return string
         */
        private function last_order_sort_key( $utc_datetime ) {

            if ( empty( $utc_datetime ) || '0000-00-00 00:00:00' === $utc_datetime ) {
                return self::NO_LAST_ORDER_KEY;
            }

            try {
                return ( new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) ) )->format( 'c' );
            } catch ( Exception $e ) {
                return self::NO_LAST_ORDER_KEY;
            }
        }

        /**
         * Cheap sort key for one candidate customer.
         *
         * Deliberately computes only the field being sorted on — the full row costs an extra order
         * query, and for a sort we need the key for every candidate but the row for only one page.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int    $user_id User ID.
         * @param string $orderby Either `lifetime_spend` or `last_order`.
         * @return string Sort key, zero-padded for `lifetime_spend` so string sorting stays numeric.
         */
        private function customer_sort_key( $user_id, $orderby ) {

            $cache_key = $this->sort_key_cache_key( $orderby, $user_id );

            if ( isset( $this->sort_key_cache[ $cache_key ] ) ) {
                return $this->sort_key_cache[ $cache_key ];
            }

            $this->sort_key_cache[ $cache_key ] = $this->compute_customer_sort_key( $user_id, $orderby );

            return $this->sort_key_cache[ $cache_key ];
        }

        /**
         * Compute a customer sort key, uncached.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int    $user_id User ID.
         * @param string $orderby Either `lifetime_spend` or `last_order`.
         * @return string
         */
        private function compute_customer_sort_key( $user_id, $orderby ) {

            if ( 'lifetime_spend' === $orderby ) {
                // Cached in user meta by WooCommerce after the first computation.
                return sprintf( '%020.2f', (float) wc_get_customer_total_spent( $user_id ) );
            }

            $last_order = wc_get_customer_last_order( $user_id );

            if ( ! $last_order instanceof WC_Order || ! $last_order->get_date_created() ) {
                return self::NO_LAST_ORDER_KEY;
            }

            return $this->last_order_sort_key(
                $last_order->get_date_created()->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
            );
        }

        /**
         * Build a single wholesale customer row.
         *
         * Fields are an explicit allowlist rather than a serialised user object, so widening
         * what an assistant can read about a customer is always a deliberate edit.
         *
         * @since 2.3.0
         * @access private
         *
         * @param int    $user_id        User ID.
         * @param string $preferred_role Wholesale role the request was filtered by, if any. Reported
         *                               as `wholesale_role` when the customer holds it.
         * @return array
         */
        private function build_customer_row( $user_id, $preferred_role = '' ) {

            $user_id = (int) $user_id;
            $user    = get_userdata( $user_id );

            $held_roles   = $user instanceof WP_User ? $this->get_own_wholesale_roles( $user ) : array();
            $primary_role = '' !== $preferred_role && in_array( $preferred_role, $held_roles, true )
                ? $preferred_role
                : (string) ( $held_roles[0] ?? '' );

            /*
             * Normally a memo hit: the `last_order` sort path computed this key while ranking, and
             * every other path had it primed for the whole page by prime_last_order_sort_cache(). On
             * a store that hooks `woocommerce_customer_get_last_order`, priming is skipped, so this
             * is a real per-customer lookup — deliberately, so the filter is honoured. Falling back
             * to null keeps the sentinel out of the response.
             */
            $last_order_key  = $this->customer_sort_key( $user_id, 'last_order' );
            $last_order_date = self::NO_LAST_ORDER_KEY === $last_order_key ? null : $last_order_key;

            return array(
                'id'              => $user_id,
                'display_name'    => $user instanceof WP_User ? $user->display_name : '',
                'email'           => $user instanceof WP_User ? $user->user_email : '',

                /*
                 * A customer can hold several wholesale roles. The full list is computed but not
                 * emitted; the single reported role prefers the one the caller filtered by when they
                 * hold it — otherwise a request filtered to `wholesale_gold` returns rows labelled
                 * `wholesale_customer`, contradicting the filter that selected them. Routed through
                 * the normaliser because passing a false $user would fatal on `$current_user->ID`
                 * inside getUserRoles().
                 */
                'wholesale_role'  => $primary_role,

                /*
                 * Null rather than the Unix epoch when the stored date is unusable. A legacy or
                 * imported row can carry `0000-00-00 00:00:00`, which strtotime() turns into false and
                 * gmdate() then renders as `1970-01-01T00:00:00+00:00` — a real-looking date an
                 * assistant would happily report as a registration date, and would sort as the oldest
                 * account in the store.
                 */
                'date_registered' => $this->format_registered_date( $user ),
                'last_order_date' => $last_order_date,
                'lifetime_spend'  => (float) wc_get_customer_total_spent( $user_id ),
                'currency'        => get_woocommerce_currency(),
            );
        }

        /**
         * WWP's ability definitions, keyed by name relative to the category.
         *
         * Returned as a map rather than registered by discrete methods so the set of names is
         * introspectable — the test suite asserts every declared name actually registered,
         * which is how a silent duplicate-name collision across the suite's shared namespace
         * gets caught in CI.
         *
         * @since 2.3.0
         * @access public
         *
         * @return array<string, array> Ability definitions keyed by unqualified ability name.
         */
        public function get_ability_definitions() {

            return array(
                'get-wholesale-price'      => array(
                    'label'               => __( 'Get wholesale price', 'woocommerce-wholesale-prices' ),
                    'description'         => __( 'Get the wholesale price stored on a product or variation for a wholesale role. This returns the price as it is saved on the product; it does not apply category, global, quantity or cart-subtotal discount rules. When Wholesale Prices Premium is active, products it hides from the requested role are reported as not found. Prices are returned in the store\'s active currency.', 'woocommerce-wholesale-prices' ),
                    'input_schema'        => array(
                        'type'                 => 'object',
                        'properties'           => array(
                            'product_id' => array(
                                'type'        => 'integer',
                                'description' => __( 'WooCommerce product or variation ID.', 'woocommerce-wholesale-prices' ),
                                'minimum'     => 1,
                            ),
                            'role'       => array(
                                'type'        => 'string',
                                'description' => __( 'Wholesale role slug. Defaults to wholesale_customer.', 'woocommerce-wholesale-prices' ),
                            ),
                        ),
                        'required'             => array( 'product_id' ),
                        'additionalProperties' => false,
                    ),
                    'output_schema'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'product_id'      => array( 'type' => 'integer' ),
                            'name'            => array( 'type' => 'string' ),
                            'sku'             => array( 'type' => 'string' ),
                            'regular_price'   => array( 'type' => array( 'number', 'null' ) ),
                            'wholesale_price' => array(
                                'type'        => array( 'number', 'null' ),
                                'description' => __( 'Null when no wholesale price is stored for the role. Note that a stored price of 0 is treated by the plugin as having no wholesale price, so the product falls back to its retail price.', 'woocommerce-wholesale-prices' ),
                            ),
                            'role'            => array( 'type' => 'string' ),
                            'currency'        => array( 'type' => 'string' ),
                        ),
                    ),
                    'permission_callback' => array( $this, 'can_read_wholesale_price' ),
                    'execute_callback'    => array( $this, 'execute_get_wholesale_price' ),
                    'meta'                => array(
                        'annotations'  => array(
                            'readonly'   => true,
                            'idempotent' => true,
                        ),
                        'show_in_rest' => true,
                    ),
                ),
                'set-wholesale-price'      => array(
                    'label'               => __( 'Set wholesale price', 'woocommerce-wholesale-prices' ),
                    'description'         => __( 'Set or clear the wholesale price stored on a simple product or a product variation for a wholesale role. Pass a null price to clear it and revert the product to its retail price. The price is stored against the store\'s base currency.', 'woocommerce-wholesale-prices' ),
                    'input_schema'        => array(
                        'type'                 => 'object',
                        'properties'           => array(
                            'product_id' => array(
                                'type'        => 'integer',
                                'description' => __( 'WooCommerce product or variation ID.', 'woocommerce-wholesale-prices' ),
                                'minimum'     => 1,
                            ),
                            'price'      => array(
                                'type'        => array( 'number', 'null' ),
                                'description' => __( 'Wholesale price. Pass null to clear it and revert to the retail price. A price of 0 is treated the same as no wholesale price, matching the product editor, so use null when the intent is to clear it.', 'woocommerce-wholesale-prices' ),
                                'minimum'     => 0,
                            ),
                            'role'       => array(
                                'type'        => 'string',
                                'description' => __( 'Wholesale role slug. Defaults to wholesale_customer.', 'woocommerce-wholesale-prices' ),
                            ),
                        ),
                        'required'             => array( 'product_id', 'price' ),
                        'additionalProperties' => false,
                    ),
                    'output_schema'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'product_id'      => array( 'type' => 'integer' ),
                            'role'            => array( 'type' => 'string' ),
                            'wholesale_price' => array( 'type' => array( 'number', 'null' ) ),
                            'previous_price'  => array( 'type' => array( 'number', 'null' ) ),
                        ),
                    ),
                    'permission_callback' => array( $this, 'can_manage_wholesale_price' ),
                    'execute_callback'    => array( $this, 'execute_set_wholesale_price' ),

                    /*
                     * `idempotent` is deliberately omitted even though repeating the same call
                     * does leave the same end state. Core derives the required HTTP method from
                     * these annotations: `readonly` means GET, `destructive` *together with*
                     * `idempotent` means DELETE, and anything else means POST
                     * (class-wp-rest-abilities-v1-run-controller.php:111-126). Declaring all
                     * three would therefore make setting a price a DELETE request, which is the
                     * wrong verb for an update. Keeping `destructive` preserves the confirmation
                     * prompt MCP clients show, since this overwrites an existing price and
                     * clears it outright when passed null.
                     */
                    'meta'                => array(
                        'annotations'  => array(
                            'readonly'    => false,
                            'destructive' => true,
                        ),
                        'show_in_rest' => true,
                    ),
                ),
                'list-wholesale-customers' => array(
                    'label'               => __( 'List wholesale customers', 'woocommerce-wholesale-prices' ),
                    'description'         => __( 'List users who hold a wholesale role, with each customer\'s last order date and lifetime spend.', 'woocommerce-wholesale-prices' ),
                    'input_schema'        => array(
                        'type'                 => 'object',

                        /*
                         * Every property is optional, so "list my wholesale customers" is a call
                         * with no input at all. Core does not sanitize ability input, only validates
                         * it, and it applies only this root-level default — without it, a
                         * no-argument call fails validation with ability_invalid_input because
                         * rest_is_object( null ) is false.
                         */
                        'default'              => array(),
                        'properties'           => array(
                            'page'     => array(
                                'type'    => 'integer',
                                'default' => 1,
                                'minimum' => 1,
                            ),
                            'per_page' => array(
                                'type'    => 'integer',
                                'default' => 25,
                                'minimum' => 1,
                                'maximum' => 100,
                            ),
                            'orderby'  => array(
                                'type'    => 'string',
                                'enum'    => array( 'date_registered', 'last_order', 'lifetime_spend', 'display_name' ),
                                'default' => 'date_registered',
                            ),
                            'order'    => array(
                                'type'    => 'string',
                                'enum'    => array( 'asc', 'desc' ),
                                'default' => 'desc',
                            ),
                            'search'   => array(
                                'type'        => 'string',
                                'description' => __( 'Optional name or email substring to filter by.', 'woocommerce-wholesale-prices' ),
                            ),
                            'role'     => array(
                                'type'        => 'string',
                                'description' => __( 'Wholesale role slug. Defaults to every registered wholesale role.', 'woocommerce-wholesale-prices' ),
                            ),
                        ),
                        'additionalProperties' => false,
                    ),
                    'output_schema'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'customers'  => array(
                                'type'  => 'array',
                                'items' => array(
                                    'type'       => 'object',
                                    'properties' => array(
                                        'id'              => array( 'type' => 'integer' ),
                                        'display_name'    => array( 'type' => 'string' ),
                                        'email'           => array( 'type' => 'string' ),
                                        'wholesale_role'  => array(
                                            'type'        => 'string',
                                            'description' => __( 'The wholesale role this row is reported under. A customer can hold more than one; when the request filtered by a role and the customer holds it, that role is the one reported.', 'woocommerce-wholesale-prices' ),
                                        ),
                                        'date_registered' => array( 'type' => array( 'string', 'null' ) ),
                                        'last_order_date' => array( 'type' => array( 'string', 'null' ) ),
                                        'lifetime_spend'  => array(
                                            'type'        => 'number',
                                            'description' => __( 'Sum of this customer\'s paid order totals as recorded on each order. WooCommerce stores no per-order exchange rate, so on a multi-currency store this adds the order currencies together without converting and is only directly comparable when the store uses one currency.', 'woocommerce-wholesale-prices' ),
                                        ),
                                        'currency'        => array(
                                            'type'        => 'string',
                                            'description' => __( 'The store\'s active currency. Note this does not necessarily describe lifetime_spend on a multi-currency store — see that field.', 'woocommerce-wholesale-prices' ),
                                        ),
                                    ),
                                ),
                            ),
                            'pagination' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'page'        => array( 'type' => 'integer' ),
                                    'per_page'    => array( 'type' => 'integer' ),
                                    'total'       => array(
                                        'type'        => 'integer',
                                        'description' => __( 'Number of customers that can be paged through. When truncated this is the size of the sortable window, not the store-wide count.', 'woocommerce-wholesale-prices' ),
                                    ),
                                    'total_pages' => array( 'type' => 'integer' ),
                                    'truncated'   => array(
                                        'type'        => 'boolean',
                                        'description' => __( 'True when ordering by last_order or lifetime_spend and the wholesale customer count exceeded the sortable limit. Results are then drawn from the most recently registered customers only, so a "top customers" answer excludes longer-standing accounts and should not be presented as store-wide.', 'woocommerce-wholesale-prices' ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                    'permission_callback' => array( $this, 'can_list_wholesale_customers' ),
                    'execute_callback'    => array( $this, 'execute_list_wholesale_customers' ),
                    'meta'                => array(
                        'annotations'  => array(
                            'readonly'   => true,
                            'idempotent' => true,
                        ),
                        'show_in_rest' => true,
                    ),
                ),
            );
        }
    }
}
