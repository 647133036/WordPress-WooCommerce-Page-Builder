/**
 * Percentage Wholesale Pricing on Product Level
 *
 * @since 2.1.0
 * @since 2.2.8 Stop reverting user-entered fixed wholesale prices when the regular price is changed (#817).
 */

jQuery(document).ready(function ($) {
    var $options = wwp_percentage_wholesale_options,
        $wholesale_roles = Object.keys(JSON.parse(JSON.stringify($options.wholesale_roles))),
        $product_type = $('#product-type').val(),
        $is_subscriptions_active = $options.is_wc_subscriptions_active,
        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

    /**
     *  we will insert an entry to woocommerce_admin local variable, so we can take advantage of the error wc_error_tip of woocommerce.
     * 
     * @since 2.1.1
     */
    Object.assign(woocommerce_admin, {
        'i18n_discount_greater_than_100_percent_error': $options.i18n_discount_greater_than_100_percent_error,
        'i18n_discount_less_than_0_percent_error': $options.i18n_discount_less_than_0_percent_error
    });

    /**
     * Normalize a value's decimal separator to a period for calculation.
     *
     * @since 2.2.7
     * @param {*}      value        The input value.
     * @param {string}  fallback     Optional fallback if value is null/empty. Defaults to returning value as-is.
     * @returns {string|*} The normalized value or fallback.
     */
    function normalizeDecimalSeparator(value, fallback) {
        if (value == null || value === '') {
            return typeof fallback !== 'undefined' ? fallback : value;
        }

        if ($options.decimal_sep !== '.') {
            return value.toString().replace($options.decimal_sep, '.');
        }

        return value;
    }

    /**
     * Convert a period decimal separator back to the localized separator.
     *
     * @since 2.2.7
     * @param {*} value The input value.
     * @returns {string|*} The localized value.
     */
    function localizeDecimalSeparator(value) {
        if ($options.decimal_sep !== '.') {
            return value.toString().replace('.', $options.decimal_sep);
        }

        return value;
    }

    /**
     * @since 2.2.1
     */
    $("body").on("click", ".woocommerce_variation",
        function (e) {
            if ($is_subscriptions_active === 'yes' && $product_type === 'variable-subscription') {
                if (!$(this).hasClass('variation-subscription-open')) {
                    $(this).addClass('variation-subscription-open');
                    const variation_parent = $(this);
                    const selector = $(this).find('.wc_input_subscription_price');
                    const variationSubscriptionPrice = selector.attr("name").split("_").pop();
                    const variation_loop_index_id = variationSubscriptionPrice.replace(/^\D+|\D+$/g, "");

                    $wholesale_roles.forEach((role) => {
                        const discount_type = $('#' + role + '_wholesale_discount_type\\[' + variation_loop_index_id + '\\]');
                        var percentage_input_field_container = $('.form-field.' + role + '_wholesale_percentage_discount\\[' + variation_loop_index_id + '\\]_field'),
                            el_wholesale_price_field = $('#' + role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]');

                        // Check if WC Subscriptions is active.
                        if ($is_subscriptions_active === 'yes') {
                            var el_wholesale_signup_fee_field_container = $('.form-field.' + role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]_field'),
                                el_wholesale_signup_fee_discount_field_container = $('.form-field.' + role + '_wholesale_signup_fee_discount\\[' + variation_loop_index_id + '\\]_field'),
                                el_wholesale_signup_fee = $('#' + role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]');

                            if ($product_type === 'variable-subscription') {
                                el_wholesale_signup_fee_field_container.show();

                                if (discount_type.val() === 'percentage') {
                                    el_wholesale_signup_fee_discount_field_container.show();
                                    el_wholesale_signup_fee.attr('readonly', true);
                                } else {
                                    el_wholesale_signup_fee_discount_field_container.hide();
                                    el_wholesale_signup_fee.attr('readonly', false);
                                }
                            }
                        }

                        if (variation_loop_index_id !== null) {

                            if (el_wholesale_price_field !== null) {

                                el_wholesale_price_field.val(removeTrailingZeros(el_wholesale_price_field.val()));
                            }


                            if (discount_type.val() === 'fixed') {

                                percentage_input_field_container.hide();
                                el_wholesale_price_field.attr('readonly', false);

                            } else {

                                percentage_input_field_container.show();
                                el_wholesale_price_field.attr('readonly', true);
                            }

                        }
                    });
                } else {
                    if ($(this).hasClass('closed')) {
                        $(this).removeClass('variation-subscription-open');
                    }
                }
            }
        }
    );

    /**
     * This block of code runs only on variable product with variations, this will check if we have changes in the variations regular price.
     * 
     * @since 2.1.0
     * @since 2.1.5 Change the conditional logic when getting the index of the variation to avoid fecthing wrong price field
     * @since 2.1.6 Change the selector from '.wc_input_price' to '#_regular_price' to avoid selecting other than regular price field.
     */
    $('body').on('keyup', '#_regular_price', function (e) {
        var regular_price = $(this).val(),
            id = $(this).attr('id'),
            variation_loop_index_id = '';

        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        if (id.match('^variable_regular_price') && $variable_product_variations.length > 0) {
            variation_loop_index_id = id.match(/\d/g).join('');
        }

        if ($(this).prop('class').indexOf('wholesale_price') < 0 && $(this).prop('class').indexOf('wholesale_discount') < 0 && (id !== '_sale_price' && id !== 'variable_sale_price' + variation_loop_index_id)) {

            // Get the percentage wholesale price
            getPercentageWholesalePriceByRegularPrice(variation_loop_index_id, regular_price);

        } else {

            if ($(this).prop('class').indexOf('wholesale_discount') < 0) {
                $(this).attr('data-fixed_price', regular_price);
            }

        }

    });

    /**
     * This block of code runs for subscription product type.
     * 
     * @since 2.2.1 WC Subscriptions compatibility
     */
    $('body').on('keyup', '#_subscription_price', function (e) {
        var regular_price = $(this).val(),
            id = $(this).attr('id');
        variation_loop_index_id = '';

        // Check if WC Subscriptions is active.
        if ($is_subscriptions_active === 'yes') {
            $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

            if ((id.match('^variable_regular_price') || id.match('^variable_sale_price')) && $variable_product_variations.length > 0) {
                variation_loop_index_id = id.match(/\d/g).join('');
            }

            if ($(this).prop('class').indexOf('wholesale_price') < 0 && $(this).prop('class').indexOf('wholesale_discount') < 0 && (id !== '_sale_price' && id !== 'variable_sale_price' + variation_loop_index_id)) {

                getPercentageWholesalePriceByRegularPrice(variation_loop_index_id, regular_price);

            } else {

                if ($(this).prop('class').indexOf('wholesale_discount') < 0) {
                    $(this).attr('data-fixed_price', regular_price);
                }

            }
        }
    });

    /**
     * This block of code runs for subscription product type.
     * 
     * @since 2.2.1 WC Subscriptions compatibility
     */
    $('body').on('keyup', '#_subscription_sign_up_fee', function (e) {
        var regular_price = $(this).val(),
            id = $(this).attr('id');
        variation_loop_index_id = '';

        // Check if WC Subscriptions is active.
        if ($is_subscriptions_active === 'yes') {
            $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

            if ((id.match('^variable_regular_price') || id.match('^variable_sale_price')) && $variable_product_variations.length > 0) {
                variation_loop_index_id = id.match(/\d/g).join('');
            }

            if ($(this).prop('class').indexOf('wholesale_price') < 0 && $(this).prop('class').indexOf('wholesale_discount') < 0 && (id !== '_sale_price' && id !== 'variable_sale_price' + variation_loop_index_id)) {

                getPercentageWholesaleSignupFeeByRegularPrice(variation_loop_index_id, regular_price);

            } else {

                if ($(this).prop('class').indexOf('wholesale_discount') < 0) {
                    $(this).attr('data-fixed_price', regular_price);
                }

            }
        }
    });

    /**
     * This block of code runs for subscription product type.
     * 
     * @since 2.2.1 WC Subscriptions compatibility
     */
    $('body').on('keyup', '.wholesale_signup_fee_discount', function (e) {
        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        const $discount_element = $(this);

        var role = $(this).data('wholesale_role'),
            variation_loop_index_id = $variable_product_variations.length > 0 ? $(this).data('loop_id') : '';

        processVariationWholesaleSignupFee(
            $discount_element,
            variation_loop_index_id,
            role
        );
    });

    /**
     * Calculate the percentage wholesale price every time we change the value of the discount input field and if the
     * discount input field is not empty. 
     * 
     * @since 2.1.0
     * @since 2.1.1 
     * - Added validation of discount value (percent), the percent or discount value should not be greater than  100 and should not be less than zero.
     * - Added wc_error_tip if the discount input field is greater than 100, and if the discount input field is less than 0
     * @since 2.2.0
     * - Refactored variation discount coulcation to a separate function
     */
    $('body').on('keyup', '.wholesale_discount', function (e) {

        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        const $discount_element = $(this);

        var role = $(this).data('wholesale_role'),
            variation_loop_index_id = $variable_product_variations.length > 0 ? $(this).data('loop_id') : '';

        processVariationWholesalePrice(
            $discount_element,
            variation_loop_index_id,
            role
        );

    });

    /**
     * Handle the keyup event on the variation regular price fields to calculate the wholesale price every time the regular price is changed.
     *
     * @since 2.2.0
     */
    $("body").on(
        "keyup",
        ".woocommerce_variation .wc_input_price",
        function (e) {
            var variationIndex = '';

            // Check if WC subscription is active.
            if ($(this).hasClass('wc_input_subscription_intial_price')) {
                const variationSubscriptionPrice = $(this).attr("name").split("_").pop();
                variationIndex = variationSubscriptionPrice.replace(/^\D+|\D+$/g, "");
            } else {
                variationIndex = $(this).attr("id").split("_").pop();
            }

            $wholesale_roles.forEach((role) => {
                const $discount_element = $(
                    `#${role}_wholesale_percentage_discount\\[${variationIndex}\\]`
                );

                processVariationWholesalePrice(
                    $discount_element,
                    variationIndex,
                    role
                );

                // For signup fee.
                if ($(this).hasClass('wc_input_subscription_intial_price')) {
                    const $discount_fee_element = $(
                        `#${role}_wholesale_signup_fee_discount\\[${variationIndex}\\]`
                    );
                    processVariationWholesaleSignupFee(
                        $discount_fee_element,
                        variationIndex,
                        role
                    );
                }
            });
        }
    );

    /**
     * Calculate the percentage wholesale sale price every time we change the value of the discount input field and if the
     * discount input field is not empty. 
     *
     * @since 2.2.1 WC Subscriptions compatibility
     */
    $('body').on('change', '.wholesale_sale_discount', function (e) {

        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        var discount = $(this).val(),
            role = $(this).data('wholesale_role'),
            variation_loop_index_id = $variable_product_variations.length > 0 ? $(this).data('loop_id') : '';

        var wholesale_price_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_price',
            price = getProductRegularPrice(wholesale_price_field_id),
            wholesale_sale_price_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_sale_price\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_sale_price',
            el_wholesale_sale_price_field = $('#' + wholesale_sale_price_field_id);

        discount = normalizeDecimalSeparator(discount);

        if (parseFloat(discount) > 100) {

            $(this).val('');
            el_wholesale_sale_price_field.val('');

        } else if (parseFloat(discount) == 100) {

            el_wholesale_sale_price_field.val(0);

        } else if (parseFloat(discount) < 0) {

            $(this).val('');
            el_wholesale_sale_price_field.val('');

        } else if (parseFloat(discount) > 0) {

            var discounted_price = calculateDiscountedPrice(price, discount);
            discounted_price = removeTrailingZeros(discounted_price);

            el_wholesale_sale_price_field.val(discounted_price);

        } else {
            el_wholesale_sale_price_field.val('');
        }

    });

    /**
     * Calculate the percentage wholesale price every time the value is change and if you loss focus in the discount field input or if encounters an error on decimal separator or if the discount input field is greater than 100 or if the discount input field is less than 0.
     *
     * @since 2.1.1
     * @since 2.2.1 WC Subscriptions compatibility
     */
    $('body').on('change', '.wholesale_discount', function (e) {
        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        var discount = $(this).val(),
            role = $(this).data('wholesale_role'),
            variation_loop_index_id = $variable_product_variations.length > 0 ? $(this).data('loop_id') : '',
            regular_price_id = $variable_product_variations.length > 0 ? 'variable_regular_price_' + variation_loop_index_id : '_regular_price';

        var price = getProductRegularPrice(regular_price_id),
            wholesale_price_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_price',
            el_wholesale_price_field = $('#' + wholesale_price_field_id);

        discount = normalizeDecimalSeparator(discount);

        if (parseFloat(discount) > 100) {

            $(this).val('');
            el_wholesale_price_field.val('');

        } else if (parseFloat(discount) == 100) {

            el_wholesale_price_field.val(0);

        } else if (parseFloat(discount) < 0) {

            $(this).val('');
            el_wholesale_price_field.val('');

        } else if (parseFloat(discount) > 0) {

            var discounted_price = calculateDiscountedPrice(price, discount);
            discounted_price = removeTrailingZeros(discounted_price);

            el_wholesale_price_field.val(discounted_price);

        } else {
            el_wholesale_price_field.val('');
        }
    });

    /**
     * Calculate the percentage wholesale signup fee every time the value is change and if you loss focus in the discount field input or if encounters an error on decimal separator or if the discount input field is greater than 100 or if the discount input field is less than 0.
     *
     * @since 2.2.1
     */
    $('body').on('change', '.wholesale_signup_fee_discount', function (e) {
        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        var discount = $(this).val(),
            role = $(this).data('wholesale_role'),
            variation_loop_index_id = $variable_product_variations.length > 0 ? $(this).data('loop_id') : '';

        const subscription_price_attr =
            $variable_product_variations.length > 0
                ? "variable_subscription_sign_up_fee[" + variation_loop_index_id + "]"
                : "_subscription_sign_up_fee";
        const price = $('input[name="' + subscription_price_attr + '"]').val();

        var wholesale_price_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_signup_fee',
            el_wholesale_price_field = $('#' + wholesale_price_field_id);

        discount = normalizeDecimalSeparator(discount);

        if (parseFloat(discount) > 100) {

            $(this).val('');
            el_wholesale_price_field.val('');

        } else if (parseFloat(discount) == 100) {

            el_wholesale_price_field.val(0);

        } else if (parseFloat(discount) < 0) {

            $(this).val('');
            el_wholesale_price_field.val('');

        } else if (parseFloat(discount) > 0) {

            var discounted_price = calculateDiscountedPrice(price, discount);
            discounted_price = removeTrailingZeros(discounted_price);

            el_wholesale_price_field.val(discounted_price);

        } else {
            el_wholesale_price_field.val('');
        }
    });

    /**
     * This block of code is similar to wc_input_price for regular price text field on keyup, this will only be triggered when there is a change and losses it's focus in the regular price text field, where there is an error for the decimal separator or thousand separator. It will auto re-calcuate the wholesale price if the discount type is percentage.
     * 
     * @since 2.1.0
     * @since 2.1.5 Change the conditional logic when getting the index of the variation to avoid fecthing wrong price field.
     * @since 2.1.6 Change the selector from '.wc_input_price' to '#_regular_price' to avoid selecting other than regular price field.
     */
    $('body').on('change', '#_regular_price', function (e) {
        var regular_price = $(this).val(),
            id = $(this).attr('id');
        variation_loop_index_id = '';

        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');

        if ((id.match('^variable_regular_price') || id.match('^variable_sale_price')) && $variable_product_variations.length > 0) {
            variation_loop_index_id = id.match(/\d/g).join('');
        }

        if ($(this).prop('class').indexOf('wholesale_price') < 0 && $(this).prop('class').indexOf('wholesale_discount') < 0 && (id !== '_sale_price' && id !== 'variable_sale_price' + variation_loop_index_id)) {

            getPercentageWholesalePriceByRegularPrice(variation_loop_index_id, regular_price);

        } else {

            if ($(this).prop('class').indexOf('wholesale_discount') < 0) {
                $(this).attr('data-fixed_price', regular_price);
            }

        }
    });

    /**
     * Calculate the percentage wholesale price if the selected type is percentage and only if the discount input field
     * is not empty. If the selected type is fixed then the previous value of the wholesale price will be used then
     * vise-versa with the discount input field.
     * 
     * @since 2.1
     */
    $('body').on('change', '.wholesale_discount_type', function (e) {
        $variable_product_variations = ($product_type === 'simple') ? { length: 0 } : $('#variable_product_options_inner .woocommerce_variations');


        var selected_discount_type = $(this).val(),
            role = $(this).data('wholesale_role'),
            variation_loop_index_id = $variable_product_variations.length > 0 ? $(this).data('loop_id') : '',
            discount_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_percentage_discount\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_percentage_discount',
            wholesale_price_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_price',
            class_discount_field = $variable_product_variations.length > 0 ? '.form-field.' + role + '_wholesale_percentage_discount\\[' + variation_loop_index_id + '\\]_field' : '.form-field.' + role + '_wholesale_percentage_discount_field',
            form_wholesale_price_field = $variable_product_variations.length > 0 ? '.form-field.' + role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]_field' : '.form-field.' + role + '_wholesale_price_field',
            wholesale_price_data_map_key = $variable_product_variations.length > 0 ? role + '_' + variation_loop_index_id : role,
            el_discount_field = $('#' + discount_field_id),
            el_wholesale_price_field = $('#' + wholesale_price_field_id);

        // Check if WC Subscriptions is active.
        if ($is_subscriptions_active === 'yes') {
            var wholesale_signup_fee_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_signup_fee',
                el_wholesale_signup_fee_field = $('#' + wholesale_signup_fee_field_id),
                signup_discount_field_id = $variable_product_variations.length > 0 ? role + '_wholesale_signup_fee_discount\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_signup_fee_discount',
                el_signup_discount_field = $('#' + signup_discount_field_id),
                wholesale_signup_fee_field_container_id = $variable_product_variations.length > 0 ? '.form-field.' + role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]_field' : '.form-field.' + role + '_wholesale_signup_fee_field',
                el_wholesale_signup_fee_field_container = $(wholesale_signup_fee_field_container_id),
                wholesale_signup_fee_discount_field_container_id = $variable_product_variations.length > 0 ? '.form-field.' + role + '_wholesale_signup_fee_discount\\[' + variation_loop_index_id + '\\]_field' : '.form-field.' + role + '_wholesale_signup_fee_discount_field',
                el_wholesale_signup_fee_discount_field_container = $(wholesale_signup_fee_discount_field_container_id);

            if ($product_type === 'subscription' || $product_type === 'variable-subscription') {
                el_wholesale_signup_fee_field_container.show();

                if (selected_discount_type === 'percentage') {
                    el_wholesale_signup_fee_field.attr('readonly', true);
                    el_wholesale_signup_fee_discount_field_container.show();
                } else {
                    el_wholesale_signup_fee_field.attr('readonly', false);
                    el_wholesale_signup_fee_discount_field_container.hide();
                }
            } else {
                el_wholesale_signup_fee_field_container.hide();
                el_wholesale_signup_fee_discount_field_container.hide();
            }
        }

        if (selected_discount_type === 'percentage') {
            var regular_price_id = $variable_product_variations.length > 0 ? 'variable_regular_price_' + variation_loop_index_id : '_regular_price',
                price = getProductRegularPrice(regular_price_id),
                discount = el_discount_field.val();

            // show discount input field
            $(class_discount_field).show();

            // disable wholesale price input field
            el_wholesale_price_field.attr('readonly', true);

            // wholesale price field tooltip
            reinitializeTooltip(form_wholesale_price_field, el_wholesale_price_field.attr('data-field_desc_percentage'));

            if (discount != "" && discount > 0) {
                var discounted_price = calculateDiscountedPrice(price, discount);
                discounted_price = removeTrailingZeros(discounted_price);

                el_wholesale_price_field.val(discounted_price);
            } else {

                el_wholesale_price_field.attr('data-fixed_price', el_wholesale_price_field.val());
                el_wholesale_price_field.val(null);
            }

        } else {

            // hide discount input field
            $(class_discount_field).hide();

            // enable wholesale price input field
            el_wholesale_price_field.attr('readonly', false);

            // wholesale price field tooltip
            reinitializeTooltip(form_wholesale_price_field, el_wholesale_price_field.attr('data-field_desc_fixed'));

            if (!!el_wholesale_price_field.attr('data-fixed_price')) {
                el_wholesale_price_field.val(el_wholesale_price_field.attr('data-fixed_price'));
            } else if (!!el_wholesale_price_field.attr('data-fixed_price') == false && el_discount_field.val() != "") {
                el_wholesale_price_field.val(null);
            } else if (!!el_wholesale_price_field.attr('data-fixed_price') == false && el_discount_field.val() == "") {
                el_wholesale_price_field.val(null);
            }

        }
    });

    /**
     * Listen to the change event on the product-type dropdown.
     * 
     * This event will only monitor if the selection is variable product, we need to do this in order to precess new product variables created.
     * 
     * @since 2.1.0
     * 
     */
    $('body').on('change', '#product-type', function (e) {
        $product_type = $(this).val();

        if ($product_type === 'variable') {
            processProductVariations();
        } else {
            processSimpleProducts();
        }

    });


    /**
     * Re-Initialize tooltip
     * 
     * This code will re-initalize wc_help_tip this is a tooltip that woocommerce use, you can override the content of the tooltip base on your requirements.
     * This function will only run, if you will include the "jquery-tiptip" in your enqueue scripts.
     * 
     * @since 2.1.0
     * @param {*} element This is the parent element of the tooltip you wish to override
     * @param {*} message This contains a custom message
     */
    function reinitializeTooltip(element, message) {
        $(element + " span.woocommerce-help-tip").tipTip({ content: message });
    };

    /**
     * Process variable Products for wholesale percentage discount
     * 
     * This function will process the variable products with variations, if the discount type is Percentage or Fixed price.
     * 
     * In this code block we use meta-characters (!"#$%&'()*+,./:;<=>?@[\]^``{|}~) in the element ID or class name, ours we use square brackets [], if we are going to use JQUERY to access our elements. It must be escaped with two backslashes \\. For eample id="foo[bar]", can use the selector $("#foo\\[bar\\]")
     * 
     * @since 2.1.0
     */
    function processProductVariations() {
        var target = $('#variable_product_options')[0],
            MutationObserver = window.MutationObserver ||
                window.WebKitMutationObserver ||
                window.MozMutationObserver,
            config = { childList: true, characterData: false, subtree: true };

        var observer = new MutationObserver(function (mutations) {

            var discount_type = $('.wholesale_discount_type');

            discount_type.each(function (i) {
                if (i < discount_type.length) {
                    var role = $(this).attr('data-wholesale_role'),
                        variation_loop_index_id = $(this).attr('data-loop_id'),
                        percentage_input_field_container = $('.form-field.' + role + '_wholesale_percentage_discount\\[' + variation_loop_index_id + '\\]_field'),
                        el_wholesale_price_field = $('#' + role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]');

                    // Check if WC Subscriptions is active.
                    if ($is_subscriptions_active === 'yes') {
                        var wholesale_signup_fee_field_id = role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]',
                            el_wholesale_signup_fee_field = $('#' + wholesale_signup_fee_field_id),
                            signup_discount_field_id = role + '_wholesale_signup_fee_discount\\[' + variation_loop_index_id + '\\]',
                            el_signup_discount_field = $('#' + signup_discount_field_id),
                            el_wholesale_signup_fee_field_container = $('.form-field.' + role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]_field'),
                            el_wholesale_signup_fee_discount_field_container = $('.form-field.' + role + '_wholesale_signup_fee_discount\\[' + variation_loop_index_id + '\\]_field');

                        if ($product_type === 'variable-subscription') {
                            el_wholesale_signup_fee_field_container.show();

                            if ($(this).val() === 'percentage') {
                                el_wholesale_signup_fee_discount_field_container.show();
                            } else {
                                el_wholesale_signup_fee_discount_field_container.hide();
                            }
                        } else {
                            el_wholesale_signup_fee_field_container.hide();
                            el_wholesale_signup_fee_discount_field_container.hide();
                        }
                    }

                    if (variation_loop_index_id !== null) {

                        if (el_wholesale_price_field !== null) {

                            el_wholesale_price_field.val(removeTrailingZeros(el_wholesale_price_field.val()));
                        }


                        if ($(this).val() === 'fixed') {

                            percentage_input_field_container.hide();
                            el_wholesale_price_field.attr('readonly', false);

                        } else {

                            percentage_input_field_container.show();
                            el_wholesale_price_field.attr('readonly', true);
                        }

                    }
                }
            });

        });

        if (target) observer.observe(target, config);
    }

    /**
     * Process Simple Products for wholesale percentage discount
     * 
     * This function will process the simple products, if the discount type is Percentage or Fixed price.
     * 
     * @since 2.1.0
     */
    function processSimpleProducts() {
        var product_type = $('#product-type').val();

        $wholesale_roles.forEach((role) => {
            var select_discount_type_id = role + '_wholesale_discount_type',
                wholesale_price_field_id = role + '_wholesale_price',
                el_selected_discount_type = $('#' + select_discount_type_id),
                el_wholesale_price_field = $('#' + wholesale_price_field_id),
                el_discount_field = $('#' + role + '_wholesale_percentage_discount'),
                percentage_input_field_container = $('.form-field.' + role + '_wholesale_percentage_discount_field');

            // Check if WC Subscriptions is active.
            if ($is_subscriptions_active === 'yes') {
                var wholesale_signup_fee_field_id = role + '_wholesale_signup_fee',
                    el_wholesale_signup_fee_field = $('#' + wholesale_signup_fee_field_id),
                    signup_discount_field_id = role + '_wholesale_signup_fee_discount',
                    el_signup_discount_field = $('#' + signup_discount_field_id),
                    el_wholesale_signup_fee_field_container = $('.' + role + '_wholesale_signup_fee_field'),
                    el_wholesale_signup_fee_discount_field_container = $('.' + role + '_wholesale_signup_fee_discount_field');

                if ($product_type === 'subscription' || $product_type === 'variable-subscription') {
                    el_wholesale_signup_fee_field_container.show();

                    if (el_selected_discount_type.val() === 'percentage') {
                        el_wholesale_signup_fee_discount_field_container.show();
                    } else {
                        el_wholesale_signup_fee_discount_field_container.hide();
                    }
                } else {
                    el_wholesale_signup_fee_field_container.hide();
                    el_wholesale_signup_fee_discount_field_container.hide();
                }
            }

            if (el_selected_discount_type !== null) {
                if ((el_selected_discount_type.val() === 'percentage' && el_discount_field.val() < 100) || (el_selected_discount_type.val() === 'fixed')) {

                    el_wholesale_price_field.text(removeTrailingZeros(el_wholesale_price_field.val()));
                }

                // Check if WC Subscriptions is active.
                if ($is_subscriptions_active === 'yes' && ($product_type === 'subscription' || $product_type === 'variable-subscription')) {
                    if ((el_selected_discount_type.val() === 'percentage' && el_signup_discount_field.val() < 100) || (el_selected_discount_type.val() === 'fixed')) {
                        el_wholesale_signup_fee_field.text(removeTrailingZeros(el_wholesale_signup_fee_field.val()));
                    }
                }

                if (el_selected_discount_type.val() === 'percentage') {
                    el_wholesale_price_field.attr('readonly', true);
                    percentage_input_field_container.show();

                    // Check if WC Subscriptions is active.
                    if ($is_subscriptions_active === 'yes' && ($product_type === 'subscription' || $product_type === 'variable-subscription')) {
                        el_wholesale_signup_fee_field.attr('readonly', true);
                    }
                } else {
                    el_wholesale_price_field.attr('readonly', false);
                    percentage_input_field_container.hide();

                    // Check if WC Subscriptions is active.
                    if ($is_subscriptions_active === 'yes' && ($product_type === 'subscription' || $product_type === 'variable-subscription')) {
                        el_wholesale_signup_fee_field.attr('readonly', false);
                    }
                }
            }
        });
    }

    /**
     * Get Percentage Wholesale Price By Regular Price
     *
     * Recalculates the wholesale price from the regular price when the wholesale
     * discount type is "percentage". When the discount type is "fixed" (or unset)
     * the wholesale price is user-controlled and must not be overwritten.
     *
     * @since 2.1.0
     * @since 2.2.8 Early-return when discount type is not "percentage" so user-entered fixed wholesale prices are not reverted on regular-price keyup (#817).
     *
     * @param {integer} variation_loop_index_id  This is the index id of variations in a variable product, if we have a total of 4(Four variations) its loop index will be like this "0,1,2,3" like an array index.
     * @param {float} regular_price This is the products regular price, we will base here our computation for the percentage discount.
     */
    function getPercentageWholesalePriceByRegularPrice(variation_loop_index_id, regular_price) {

        $wholesale_roles.forEach(function (role) {

            var select_discount_type_id = $variable_product_variations.length > 0 ?
                role + '_wholesale_discount_type\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_discount_type',
                discount_field_id = $variable_product_variations.length > 0 ?
                    role + '_wholesale_percentage_discount\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_percentage_discount',
                wholesale_field_id = $variable_product_variations.length > 0 ?
                    role + '_wholesale_prices\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_price',

                el_wholesale_price_field = $('#' + wholesale_field_id),
                el_discount_field = $('#' + discount_field_id),
                el_discount_type = $('#' + select_discount_type_id),
                selected_options = el_discount_type ? el_discount_type.val() : null;

            if (selected_options !== 'percentage') {
                return;
            }

            var raw_discount = el_discount_field.val();
            var discount = normalizeDecimalSeparator(raw_discount, '');

            if (regular_price !== "" && raw_discount !== "") {
                var discounted_price = calculateDiscountedPrice(regular_price, discount);

                if (discount < 100) {
                    el_wholesale_price_field.val(removeTrailingZeros(discounted_price));
                } else {
                    el_wholesale_price_field.val(0);
                }
            } else {
                el_wholesale_price_field.val('');
            }

        });
    }

    /**
     * Get Percentage Wholesale Signup Fee By Subscription Sign-up fee
     *
     * Recalculates the wholesale signup fee from the subscription sign-up fee when
     * the wholesale discount type is "percentage". When the discount type is "fixed"
     * (or unset) the wholesale signup fee is user-controlled and must not be
     * overwritten.
     *
     * @since 2.2.1
     * @since 2.2.8 Early-return when discount type is not "percentage" so user-entered fixed wholesale signup fees are not reverted on subscription sign-up fee keyup (#817).
     *
     * @param {integer} variation_loop_index_id  This is the index id of variations in a variable product, if we have a total of 4(Four variations) its loop index will be like this "0,1,2,3" like an array index.
     * @param {float} regular_price This is the products regular price, we will base here our computation for the percentage discount.
     */
    function getPercentageWholesaleSignupFeeByRegularPrice(variation_loop_index_id, regular_price) {

        $wholesale_roles.forEach(function (role) {

            var select_discount_type_id = $variable_product_variations.length > 0 ?
                role + '_wholesale_discount_type\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_discount_type',
                discount_field_id = $variable_product_variations.length > 0 ?
                    role + '_wholesale_signup_fee_discount\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_signup_fee_discount',
                wholesale_field_id = $variable_product_variations.length > 0 ?
                    role + '_wholesale_signup_fee\\[' + variation_loop_index_id + '\\]' : role + '_wholesale_signup_fee',

                el_wholesale_price_field = $('#' + wholesale_field_id),
                el_discount_field = $('#' + discount_field_id),
                el_discount_type = $('#' + select_discount_type_id),
                selected_options = el_discount_type ? el_discount_type.val() : null;

            if (selected_options !== 'percentage') {
                return;
            }

            var raw_discount = el_discount_field.val();
            var discount = normalizeDecimalSeparator(raw_discount, '');

            if (regular_price !== "" && raw_discount !== "") {
                var discounted_price = calculateDiscountedPrice(regular_price, discount);

                if (discount < 100) {
                    el_wholesale_price_field.val(removeTrailingZeros(discounted_price));
                } else {
                    el_wholesale_price_field.val(0);
                }
            } else {
                el_wholesale_price_field.val('');
            }

        });
    }

    /**
     * Remove all trailing zeros after decimal
     * 
     * - Remove all trailing zero's after decimal
     * - If the decimal separator is comma(",") we convert it to dot(".") separator, if we dont do this it will spit out a wrong value. (e.g: parseFloat(22,20) will output "22" instead of "22.2").
     * 
     * @since 2.1.0
     * @param float price - The price of the product/item
     * @returns 
     */
    function removeTrailingZeros(price) {

        try {

            if (price != null && price !== "") {
                // First we convert if has decimal separator of ',' to '.'
                price = normalizeDecimalSeparator(price);

                // we remove all trailing zeros using parseFloat() function
                price = parseFloat(price);

                // we check if the decimal separator is comma (','), we change it back to the decimal separator that is set in wordpress settings.
                if (!isNaN(price)) {
                    price = localizeDecimalSeparator(price);
                } else {
                    price = '';
                }
            }

            return price;

        } catch (e) {
            console.error(e.message);
        }

    }

    /**
     * Get Product Regular Price
     * 
     * @since 2.1.0
     * @param Number id     - ID of the element in which where going to extract the price.
     * @returns {currency}  - Regular price
     */
    function getProductRegularPrice(id) {
        var el_product_reg_price = $('#' + id);
        return el_product_reg_price.val();
    }

    /**
     * Calculate Percentage
     * 
     * This will calculate the percentage to be use to deduct to the regular price.
     * 
     * @since 2.1.0
     * @param float percent  - The percent value to be use to get the computation of the percentage value from the current price
     * @param float total    - The regular price to be used to calculate the percentage value
     * @returns {float} percentage
     */
    function calculatePercentage(percent, total) {

        if (percent == null || percent === '' || total == null || total === '') {
            return 0;
        }

        // If the decimal separator is not a period "." replace it with a period "." so we can calculate the percentage
        percent = normalizeDecimalSeparator(percent);

        var percentage = (percent / 100) * total;

        return percentage;
    }

    /**
     * Calculate Percentage Wholesale Price
     * 
     * @since 2.1.0
     * @param Float price    - Price to calculate
     * @param Float percent  - Percentage to calculate
     * @returns {currency}   - The calculated discounted price
     */
    function calculateDiscountedPrice(price, percent) {

        if (price == null || price === '' || percent == null || percent === '') {
            return null;
        }

        try {
            price = normalizeDecimalSeparator(price);
        } catch (e) {
            return null;
        }

        var wholesale_price = price - calculatePercentage(percent, price);

        /**
         * This will check if the wholesale_price value is less than or equal to 0 or wholesale_price is NaN(Not a Number) if true, it will make the wholesale_price to null. Otherwise it will format the wholesale_price base on the parameters given using the currency.js
         */
        wholesale_price = wholesale_price <= 0 || isNaN(wholesale_price) ? null : currency(wholesale_price, { precision: $options.calculation_decimal_places, decimal: $options.decimal_sep, separator: '', pattern: '#' }).format();

        // We return null if the wholesale_price is null, otherwise return wholesale_price raw value
        return (wholesale_price === null) ? null : wholesale_price;
    }

    /**
     * This function listens to the changes in the discount type dropdown and then calls the appropriate function to process
     * the product variations or simple products
     * 
     * @since 2.1
     * 
     */
    function listenToDiscountTypeDropDownChanges() {

        if ($variable_product_variations.length > 0) {
            processProductVariations();
        } else {
            processSimpleProducts();
        }

    }

    /**
     * Process the variation wholesale price based on the regular price and discount percentage
     *
     * @param DOMElemnt $discount_element The product discount element.
     * @param String variation_loop_index_id The variation loop index id.
     * @param String role The wholesale customer role.
     */
    function processVariationWholesalePrice(
        $discount_element,
        variation_loop_index_id,
        role
    ) {
        $variable_product_variations =
            $product_type === "simple"
                ? { length: 0 }
                : $("#variable_product_options_inner .woocommerce_variations");

        var discount = $discount_element.val(),
            regular_price_id =
                $variable_product_variations.length > 0
                    ? "variable_regular_price_" + variation_loop_index_id
                    : "_regular_price",
            price = getProductRegularPrice(regular_price_id);

        if ($is_subscriptions_active === 'yes' && ($product_type === 'subscription' || $product_type === 'variable-subscription')) {
            const subscription_price_attr =
                $variable_product_variations.length > 0
                    ? "variable_subscription_price[" + variation_loop_index_id + "]"
                    : "_subscription_price";
            price = $('input[name="' + subscription_price_attr + '"]').val();
        }

        var wholesale_price_field_id =
            $variable_product_variations.length > 0
                ? role + "_wholesale_prices\\[" + variation_loop_index_id + "\\]"
                : role + "_wholesale_price",
            el_wholesale_price_field = $("#" + wholesale_price_field_id),
            wholesale_sale_price_field_id =
                $variable_product_variations.length > 0
                    ? role + "_wholesale_sale_price\\[" + variation_loop_index_id + "\\]"
                    : role + "_wholesale_sale_price",
            el_wholesale_sale_price_field = $("#" + wholesale_sale_price_field_id),
            wholesale_sale_discount_field_id =
                $variable_product_variations.length > 0
                    ? role + "_wholesale_sale_discount\\[" + variation_loop_index_id + "\\]"
                    : role + "_wholesale_sale_discount",
            el_wholesale_sale_discount_field = $("#" + wholesale_sale_discount_field_id),
            discount_field_id =
                $variable_product_variations.length > 0
                    ? role + "_wholesale_discount_type\\[" + variation_loop_index_id + "\\]"
                    : role + "_wholesale_discount_type",
            discount_type = $("#" + discount_field_id).val();

        discount = normalizeDecimalSeparator(discount);

        if (parseFloat(discount) > 100) {
            $(document.body).triggerHandler("wc_add_error_tip", [
                $discount_element,
                "i18n_discount_greater_than_100_percent_error",
            ]);
        } else if (parseFloat(discount) == 100) {
            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_greater_than_100_percent_error",
            ]);

            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);

            el_wholesale_price_field.val(0);
        } else if (parseFloat(discount) < 0) {
            $(document.body).triggerHandler("wc_add_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);
        } else if (parseFloat(discount) > 0) {
            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_greater_than_100_percent_error",
            ]);

            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);

            var discounted_price = calculateDiscountedPrice(price, discount);
            discounted_price = removeTrailingZeros(discounted_price);

            el_wholesale_price_field.val(discounted_price);

            const sale_discount = el_wholesale_sale_discount_field.val();
            if (parseFloat(sale_discount) > 0) {
                var wholesale_price = calculateDiscountedPrice(price, discount);
                wholesale_price = removeTrailingZeros(wholesale_price);

                var discounted_sale_price = calculateDiscountedPrice(wholesale_price, sale_discount);
                discounted_sale_price = removeTrailingZeros(discounted_sale_price);

                el_wholesale_sale_price_field.val(discounted_sale_price);
            }
        } else {
            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);

            if (discount_type === "percentage") {
                el_wholesale_price_field.val("");
            }
        }
    }

    /**
     * Process the variation wholesale singup fee based on the regular price and discount percentage
     *
     * @param DOMElemnt $discount_element The product discount element.
     * @param String variation_loop_index_id The variation loop index id.
     * @param String role The wholesale customer role.
     *
     * @since 2.2.1
     */
    function processVariationWholesaleSignupFee(
        $discount_element,
        variation_loop_index_id,
        role
    ) {
        $variable_product_variations =
            $product_type === "subscription"
                ? { length: 0 }
                : $("#variable_product_options_inner .woocommerce_variations");

        var discount = $discount_element.val(),
            regular_price_id = "_subscription_sign_up_fee",
            price = getProductRegularPrice(regular_price_id);

        if ($is_subscriptions_active === 'yes' && ($product_type === 'subscription' || $product_type === 'variable-subscription')) {
            const subscription_price_attr =
                $variable_product_variations.length > 0
                    ? "variable_subscription_sign_up_fee[" + variation_loop_index_id + "]"
                    : "_subscription_sign_up_fee";
            price = $('input[name="' + subscription_price_attr + '"]').val();
        }

        var wholesale_price_field_id =
            $variable_product_variations.length > 0
                ? role + "_wholesale_signup_fee\\[" + variation_loop_index_id + "\\]"
                : role + "_wholesale_signup_fee",
            el_wholesale_price_field = $("#" + wholesale_price_field_id),
            discount_field_id =
                $variable_product_variations.length > 0
                    ? role + "_wholesale_discount_type\\[" + variation_loop_index_id + "\\]"
                    : role + "_wholesale_discount_type",
            discount_type = $("#" + discount_field_id).val();

        discount = normalizeDecimalSeparator(discount);

        if (parseFloat(discount) > 100) {
            $(document.body).triggerHandler("wc_add_error_tip", [
                $discount_element,
                "i18n_discount_greater_than_100_percent_error",
            ]);
        } else if (parseFloat(discount) == 100) {
            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_greater_than_100_percent_error",
            ]);

            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);

            el_wholesale_price_field.val(0);
        } else if (parseFloat(discount) < 0) {
            $(document.body).triggerHandler("wc_add_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);
        } else if (parseFloat(discount) > 0) {
            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_greater_than_100_percent_error",
            ]);

            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);

            var discounted_price = calculateDiscountedPrice(price, discount);
            discounted_price = removeTrailingZeros(discounted_price);

            el_wholesale_price_field.val(discounted_price);
        } else {
            $(document.body).triggerHandler("wc_remove_error_tip", [
                $discount_element,
                "i18n_discount_less_than_0_percent_error",
            ]);

            if (discount_type === "percentage") {
                el_wholesale_price_field.val("");
            }
        }
    }


    /**=================================================================================================================
     * Events
     =================================================================================================================*/
    listenToDiscountTypeDropDownChanges();

});