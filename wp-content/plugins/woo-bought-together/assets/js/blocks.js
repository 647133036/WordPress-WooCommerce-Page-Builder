const woobtCheckoutFilters = window.wc.blocksCheckout.registerCheckoutFilters;

const woobtCartItemClass = (defaultValue, extensions, args) => {
    const isCartContext = args?.context === 'cart';

    if (!isCartContext) {
        return defaultValue;
    }

    if (args?.cartItem?.woobt_main) {
        defaultValue += ' woobt-main';
    }

    if (args?.cartItem?.woobt_linked) {
        defaultValue += ' woobt-linked';
    }

    return defaultValue;
};

woobtCheckoutFilters('woobt-blocks', {
    cartItemClass: woobtCartItemClass,
});

// https://github.com/woocommerce/woocommerce-blocks/blob/trunk/docs/third-party-developers/extensibility/checkout-block/available-filters/cart-line-items.md