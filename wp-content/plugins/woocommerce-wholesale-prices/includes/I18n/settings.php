<?php
/**
 * Vite App i18n file. WPML doesn't support gettext parsing of .js files, so we need to create a .php file that returns
 * an array of translations.
 *
 * @since   2.1.9
 * @package RymeraWebCo\WWP
 */

defined( 'ABSPATH' ) || exit;

return array(
    'redirectLoading'                    => __( 'You are redirecting to a page. Please wait...', 'woocommerce-wholesale-prices' ),
    'sendRequestTitleSuccess'            => __( 'Success', 'woocommerce-wholesale-prices' ),
    'sendRequestTitleFailed'             => __( 'Failed', 'woocommerce-wholesale-prices' ),
    'addOnTitle'                         => __( 'Wholesale Suite Bundle', 'woocommerce-wholesale-prices' ),
    'addOnSubTitle'                      => __( '3x wholesale plugins', 'woocommerce-wholesale-prices' ),
    'addOnButtonText'                    => __( 'Wholeasale Prices Premium Add-on', 'woocommerce-wholesale-prices' ),
    'advanceTaxTitle'                    => __( 'Upgrade To Wholesale Suite For Advanced Tax Display', 'woocommerce-wholesale-prices' ),
    'advanceTaxText'                     => __( 'Wholesale Suite is the #1 best rated wholesale solution for WooCommerce. Prices Premium (one of the three plugins) features in-depth tax display controls.', 'woocommerce-wholesale-prices' ),
    'advanceTaxButtonText'               => __( 'See Features & Pricing', 'woocommerce-wholesale-prices' ),
    'definePricesGlobalTitle'            => __( 'Define Prices By Percentage Globally Or On Categories', 'woocommerce-wholesale-prices' ),
    'definePricesGlobalText'             => __( 'In WooCommerce Wholesale Prices Premium you can set your wholesale prices by a percentage on a category or site-wide general level. This can save heaps of time instead of setting wholesale pricing on individual products. Read more about it below.', 'woocommerce-wholesale-prices' ),
    'freeGuideTitle'                     => __( 'FREE GUIDE: How To Setup Wholesale On Your WooCommerce Store', 'woocommerce-wholesale-prices' ),
    'freeGuideStepTitle'                 => __( 'A Step-By-Step Guide For Adding Wholesale Ordering To Your Store', 'woocommerce-wholesale-prices' ),
    /* translators: %s = download guide text */
    'freeGuideStepDescription'           => __( 'If you\'ve ever wanted to grow a store to 6, 7 or 8 - figures and beyond %s now. Want to increase your product\'s sales and reach, all while making amazing connections with other retailers ? Download this guide for free now.', 'woocommerce-wholesale-prices' ),
    'freeGuideStepDownloadText'          => __( 'download this guide', 'woocommerce-wholesale-prices' ),
    'freeGuideTextOne'                   => __( 'Learn exactly how to price your products ready for wholesale', 'woocommerce-wholesale-prices' ),
    'freeGuideTextTwo'                   => __( 'The free way to setup wholesale pricing for customers in WooCommerce', 'woocommerce-wholesale-prices' ),
    'freeGuideTextThree'                 => __( 'Why you need an efficient ordering process', 'woocommerce-wholesale-prices' ),
    'freeGuideTextFour'                  => __( 'How to find your ideal wholesale customers & recruit them', 'woocommerce-wholesale-prices' ),
    'freeGuideStepButtonText'            => __( 'Get FREE Training Guide', 'woocommerce-wholesale-prices' ),
    'leadCaptureRecommendedTitle'        => __( 'Recommended Plugin', 'woocommerce-wholesale-prices' ),
    'leadCaptureRecommendedButtonText'   => __( 'WooCommerce Wholesale Lead Capture', 'woocommerce-wholesale-prices' ),
    'leadCaptureRecommendedRegisterText' => __( 'Lead Capture adds an additional \'register text\' link to the wholesale prices box on the front end to help you capture even more wholesale leads.', 'woocommerce-wholesale-prices' ),
    'leadCaptureRecommendedBonusText'    => __( 'Bonus', 'woocommerce-wholesale-prices' ),
    /* translators: %s = download guide text */
    'leadCaptureRecommendedDescription'  => __( 'Wholesale Prices lite users get %s, automatically applied at checkout.', 'woocommerce-wholesale-prices' ),
    'leadCaptureRecommendedPercentText'  => __( '50% off regular price', 'woocommerce-wholesale-prices' ),
    'generalUnlockTitle'                 => __( 'Get Wholesale Suite and unlock all the wholesale features', 'woocommerce-wholesale-prices' ),
    'generalUnlockThankyou'              => __( 'Thanks for being a loyal Wholesale Prices by Wholesale Suite user. Upgrade to unlock all of the extra wholesale features that makes Wholesale Suite consistently rated the best WooCommerce wholesale plugin.', 'woocommerce-wholesale-prices' ),
    /* translators: %s = five star image */
    'generalUnlockFiveStar'              => __( 'We know that you will truly love Wholesale Suite. It has 442+ five star ratings (%s) and is active on over 25,000+ stores.', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumTitle'          => __( 'Wholesale Prices Premium', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListOne'        => __( '+ Global & category level wholesale pricing', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListTwo'        => __( '+ "Wholesale Only" products', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListThree'      => __( '+ Hide wholesale products from retail customers', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListFour'       => __( '+ Multiple levels of wholesale user roles', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListFive'       => __( '+ Manage wholesale pricing over multiple user tiers', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListSix'        => __( '+ Shipping mapping', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListSeven'      => __( '+ Payment gateway mapping', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListEight'      => __( '+ Tax exemptions & fine grained tax display control', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListNine'       => __( '+ Order minimum quantities & subtotals', 'woocommerce-wholesale-prices' ),
    'generalUnlockPremiumListTen'        => __( '+ 100\'s of other premium pricing related features', 'woocommerce-wholesale-prices' ),
    'generalUnlockWofTitle'              => __( 'Wholesale Order Form', 'woocommerce-wholesale-prices' ),
    'generalUnlockUpgradeListOne'        => __( '+ Automatically recruit & register wholesale customers', 'woocommerce-wholesale-prices' ),
    'generalUnlockUpgradeListTwo'        => __( '+ Save huge amounts of admin time & recruit on autopilot', 'woocommerce-wholesale-prices' ),
    'generalUnlockUpgradeListThree'      => __( '+ Full registration form builder', 'woocommerce-wholesale-prices' ),
    'generalUnlockUpgradeListFour'       => __( '+ Custom fields capability to capture all required information', 'woocommerce-wholesale-prices' ),
    'generalUnlockUpgradeListFive'       => __( '+ Full automated mode OR manual approvals mode', 'woocommerce-wholesale-prices' ),
    'generalUnlockWlcTitle'              => __( 'Wholesale Lead Capture', 'woocommerce-wholesale-prices' ),
    'generalUnlockUpgradeButtonText'     => __( 'Get Wholesale Suite today & unlock these powerful features + more', 'woocommerce-wholesale-prices' ),
    'pricesDisplayTitle'                 => __( 'Change How Variable Product Prices Are Displayed', 'woocommerce-wholesale-prices' ),
    'pricesDisplayText'                  => __( 'Changing how your variable product prices are displayed can reduce the amount of computational work WooCommerce does on load, making your site faster. Access this optimization option and more in the WooCommerce Wholesale Prices Premium plugin.', 'woocommerce-wholesale-prices' ),
    'suffixOverrideTitle'                => __( 'Upgrade To Wholesale Suite For Suffix Overrides', 'woocommerce-wholesale-prices' ),
    'suffixOverrideTextOne'              => __( 'Wholesale Suite is the #1 best rated wholesale solution for WooCommerce. Prices Premium (one of three plugins) features advanced price suffix controls.', 'woocommerce-wholesale-prices' ),
    'suffixOverrideTextTwo'              => __( 'This can help in complex tax situations where prices suffixes should be different for wholesale customers.', 'woocommerce-wholesale-prices' ),
    'taxExemptionTitle'                  => __( 'Upgrade To Wholesale Suite For Tax Exemption', 'woocommerce-wholesale-prices' ),
    'taxExemptionTextOne'                => __( 'Wholesale Suite is the #1 best rated wholesale solution for WooCommerce.', 'woocommerce-wholesale-prices' ),
    'taxExemptionTextTwo'                => __( 'Prices Premium (one of the three plugins) features in-depth tax exemption controls including being able to turn on/off tax exemption just for specific wholesale roles.', 'woocommerce-wholesale-prices' ),
    'submitText'                         => __( 'Save Changes', 'woocommerce-wholesale-prices' ),
    'confirmText'                        => __( 'Are you sure want to clear the cache?', 'woocommerce-wholesale-prices' ),
    'viewPageText'                       => __( 'View Page', 'woocommerce-wholesale-prices' ),
    'optionValuePlaceholder'             => __( 'Option Value', 'woocommerce-wholesale-prices' ),
    'optionTextPlaceholder'              => __( 'Option Text', 'woocommerce-wholesale-prices' ),
    'saveButtonText'                     => __( 'Save Custom Field', 'woocommerce-wholesale-prices' ),
    'cancelButtonText'                   => __( 'Cancel', 'woocommerce-wholesale-prices' ),
);
