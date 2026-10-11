jQuery( document ).ready( function ( $ ) {

  	var $product_type                   = $('#product-type').val();

   	/**
   	 * Set the sale discount field and the wholesale sale price field to appropriate attribute on when the discount typ changed.
   	 * 
   	 * @since 2.1.6
   	 */
   	$( 'body' ).on( 'change', '.wholesale_discount_type', function() {
		var $wrap                  = $( this ).closest( 'div' ),
			$wholesale_sale_price  = $wrap.find( '.wholesale_sale_price' ),
			$sale_discount_field   = $wrap.find( '.wholesale_sale_discount' ).closest( '.form-field' ),
			selected_discount_type = $( this ).val();

		if ( selected_discount_type === 'percentage' ) {
			$sale_discount_field.show();
			$wholesale_sale_price.attr( 'readonly', true );
		} else {
			$sale_discount_field.hide();
			$wholesale_sale_price.attr( 'readonly', false );
		}
	} );

	/**
     * Listen to the change event on the product-type dropdown.
     * For variable product, the event used is 'woocommerce_variations_loaded'.
     * 
     * @since 2.1.6
     */
	 $( 'body' ).on( 'change', '#product-type', function() {
        $product_type = $( this ).val();

        if ( 'simple' === $product_type ) {
            process_simple_products();
        }

    } );

	/**
     * Show the schedule field and hide the shcedule link when user click the shedule link.
     * 
     * @since 2.1.6
     */
	$( '#woocommerce-product-data' ).on(
		'click',
		'.wholesale_sale_schedule',
		function () {
			var $wrap = $( this ).closest( 'div, table' );

			$( this ).hide();
			$wrap.find( '.cancel_wholesale_sale_schedule' ).show();
			$wrap.find( '.wholesale_sale_price_dates_fields' ).show();

			return false;
		}
	);

    /**
     * Hide the schedule field and show the shcedule link when user click the cancel shedule link.
     * 
     * @since 2.1.6
     */
    $( '#woocommerce-product-data' ).on(
		'click',
		'.cancel_wholesale_sale_schedule',
		function () {
			var $wrap = $( this ).closest( 'div, table' );

			$( this ).hide();
			$wrap.find( '.wholesale_sale_schedule' ).show();
			$wrap.find( '.wholesale_sale_price_dates_fields' ).hide();
			$wrap.find( '.wholesale_sale_price_dates_fields' ).find( 'input' ).val( '' );

			return false;
		}
	);

  	/**
     * Process Simple Products for wholesale percentage discount
     * 
     * This function will process the simple products, if the discount type is Percentage or Fixed price.
     * 
     * @since 2.1.6
     */
   	function process_simple_products() {
		$( '.wholesale_sale_price' ).each( function () {
			var $wrap                                 = $( this ).closest( 'div' ),
				$sale_discount_field                  = $wrap.find( '.wholesale_sale_discount' ).closest( '.form-field' ),
				$discount_type                        = $wrap.find( '.wholesale_discount_type' );

			if ( $discount_type !== null ) {
				if ( $discount_type.val() === 'percentage' ) {
					$( this ).attr('readonly', true);
					$sale_discount_field.show();
				} else {
					$( this ).attr('readonly', false);
					$sale_discount_field.hide();
				}
			}
		});
	}

	/**
     * Woocommerce event that trigger after successfully load variations via Ajax.
     * 
     * @since 2.1.6
     */
	 $( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function() {
        var $wrapper            = $( '#woocommerce-product-data' );
        var $variations_wrapper = ( '.woocommerce_variation' );

        // Hide/show appropriate wholesale sale price field for product variations.
        $( '.wholesale_sale_price', $variations_wrapper ).each( function () {
            var $wrap                = $( this ).closest( 'div' ),
                $sale_discount_field = $wrap.find( '.wholesale_sale_discount' ).closest( '.form-field' ),
                $discount_type       = $wrap.find( '.wholesale_discount_type' );

            if ( $discount_type !== null ) {
                if ( $discount_type.val() === 'percentage' ) {
                    $( this ).attr('readonly', true);
                    $sale_discount_field.show();
                } else {
                    $( this ).attr('readonly', false);
                    $sale_discount_field.hide();
                }
            }
        } );
    } );

	/**
	 * This function listens to the changes in the discount type dropdown and then calls the appropriate function to process
	 * the product variations or simple products
	 * 
	 * @since 2.1.6
	 */
	function init() {
		if ( 'simple' === $product_type ) {
			process_simple_products();
		}
	}

	// Initialize event(s).
	init();
});