jQuery(document).ready(function ($) {

    /*
     |--------------------------------------------------------------------------
     | Global scripts for wholesale price free version
     |--------------------------------------------------------------------------
     */
    // Add target attribute to upgrade link
    $('.wwp-upgrade-link').attr('target', '_blank');

    // Add Wholesale payments notice.
    if (wwp_wholesale_main_object.is_wpay_active === '') {
        jQuery('.wc_payment_gateways_wrapper table tbody tr:last-child td:last-child').append('<span>&nbsp;' + wwp_wholesale_main_object.i18n_get_wholesale_payments + '</span>');
    }

});