jQuery( function ( $ ) {
	$.blockUI({message: '<p> Loading Payment Modal...</p>'});
	let payment_made = false;
	const redirectPost = function (location, args) {
		let form = "";
		$.each(args, function (key, value) {
			// value = value.split('"').join('\"')
			form += '<input type="hidden" name="' + key + '" value="' + value + '">';
		});
		$('<form action="' + location + '" method="POST">' + form + "</form>")
			.appendTo($(document.body))
			.submit();
	};

	/**
	 * Append a query parameter to a URL that may already carry a query string.
	 *
	 * The gateway return URL always carries wc-api, order_id and the order key,
	 * so appending with "?" here would fold those into the previous parameter's
	 * value and the callback would reject the request.
	 */
	const withParam = function (url, key, value) {
		const separator = url.indexOf('?') === -1 ? '?' : '&';
		return url + separator + key + '=' + encodeURIComponent(value);
	};

	const processData = () => {
		return {
			public_key: flw_payment_args.public_key,
			tx_ref: flw_payment_args.tx_ref,
			amount: flw_payment_args.amount,
			currency: flw_payment_args.currency,
			payment_options: flw_payment_args.payment_options,
			redirect_url: flw_payment_args.redirect_url,
			onclose: function () {
				$.unblockUI();
				if (payment_made) {
					$.blockUI({message: '<p> confirming transaction ...</p>'});
					redirectPost(withParam(flw_payment_args.redirect_url, 'tx_ref', flw_payment_args.tx_ref), {});
				} else {
					$.blockUI({message: '<p> Canceling Payment ...</p>'});
					window.location.href = flw_payment_args.cancel_url;
				}
			},
			callback: function (response) {
				let tr = response.tx_ref;
				if ( 'successful' === response.status ) {
					payment_made = true;
					$.blockUI({message: '<p> confirming transaction ...</p>'});
					redirectPost(withParam(flw_payment_args.redirect_url, 'tx_ref', tr), response);
				}
				this.close(); // close modal
			},
			meta: {
				consumer_id: flw_payment_args.consumer_id,
			},
			customer: {
				email: flw_payment_args.email,
				phone_number: flw_payment_args.phone_number,
				name: flw_payment_args.first_name + " " + flw_payment_args.last_name,
			},
			customizations: {
				title: flw_payment_args.title,
				description: flw_payment_args.description,
				logo: flw_payment_args.logo,
			},
		}
	}
	let payload = processData();
	let x = window.FlutterwaveCheckout(payload);
} );
