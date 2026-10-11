const { registerCheckoutFilters } = window.wc.blocksCheckout

const isCartContext = (args) => args?.context === 'cart'
const isCheckoutContext = (args) => args?.context === 'summary'
const isWholesalePriced = (args) => args?.cartItem?.extensions?.rymera_wwp?.wwp_data?.wholesale_priced === 'yes'

// Adjust cart item price of the cart line items.
registerCheckoutFilters('rymera-wwp', {
    cartItemClass: (value, extensions, args) => {
        /***************************************************************************
         * Check context and if the product is wholesale priced.
         ***************************************************************************
        *
        * We will only adjust the cart item price if the context is 'cart' and the
        * product is wholesale priced.
        *
        * Note: for some reason, Javascript optional chaining (obj?.prop) does not
        * work inside here hence we separated the checks into another function.
        */
        if ((isCartContext(args) || isCheckoutContext(args)) && isWholesalePriced(args)) {
            value = value ? value + ' wwp-wholesale-priced' : 'wwp-wholesale-priced'
        }

        return value
    }
})