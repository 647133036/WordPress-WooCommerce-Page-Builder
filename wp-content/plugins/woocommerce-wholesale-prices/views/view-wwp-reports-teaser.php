<?php
/**
 * Wholesale Reports teaser landing page.
 *
 * Static, sample-data-only preview of the Wholesale Prices Premium Reports
 * page. Rendered when WWPP is inactive so free users can discover the
 * feature. The upsell modal is shown on top by default; closing the modal
 * leaves the static preview underneath visible. Clicking anywhere on the
 * preview re-opens the modal so the upsell CTA stays one click away.
 *
 * No DB queries, no AJAX, no real store data is touched here. All numbers,
 * table rows, and chart data are hardcoded fixtures. The structure and
 * styling mirror WWPP's wholesale-analytics page so the teaser reads as a
 * faithful preview.
 *
 * @package WooCommerceWholeSalePrices\Views
 *
 * @since 2.2.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wwp_reports_teaser_unlock_url = WWP_Helper_Functions::get_utm_url( 'bundle', 'wwp', 'upsell', 'reports-landing' );
?>

<div id="wwp-reports-teaser" class="wwp-reports-teaser">

    <div id="wwp-reports-teaser-preview" class="wwp-reports-teaser__preview">

        <header class="wwp-reports-teaser__header">
            <div class="wwp-reports-teaser__header-inner">
                <div class="wwp-reports-teaser__header-left">
                    <div class="wwp-reports-teaser__header-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
                <div class="wwp-reports-teaser__header-text">
                    <h1 class="wwp-reports-teaser__header-title">
                        <?php esc_html_e( 'Wholesale Analytics', 'woocommerce-wholesale-prices' ); ?>
                    </h1>
                    <p class="wwp-reports-teaser__header-desc">
                        <?php esc_html_e( 'Comprehensive insights into your wholesale sales performance.', 'woocommerce-wholesale-prices' ); ?>
                    </p>
                </div>
            </div>
                <div class="wwp-reports-teaser__header-right">
                    <div class="wwp-reports-teaser__section-pill">
                        <div class="wwp-reports-teaser__section-pill-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                <path d="M3 9h18"></path>
                                <path d="M3 15h18"></path>
                                <path d="M9 3v18"></path>
                                <path d="M15 3v18"></path>
                            </svg>
                        </div>
                        <div class="wwp-reports-teaser__section-pill-text">
                            <span class="wwp-reports-teaser__section-pill-title">
                                <?php esc_html_e( 'Sales Overview', 'woocommerce-wholesale-prices' ); ?>
                            </span>
                            <span class="wwp-reports-teaser__section-pill-sub">
                                <?php esc_html_e( 'Revenue, sales split, and growth metrics', 'woocommerce-wholesale-prices' ); ?>
                            </span>
                        </div>
                        <span class="wwp-reports-teaser__section-pill-chevron" aria-hidden="true">&#9662;</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="wwp-reports-teaser__main">

            <div class="wwp-reports-teaser__heading">
                <h2 class="wwp-reports-teaser__heading-title">
                    <?php esc_html_e( 'Sales Overview', 'woocommerce-wholesale-prices' ); ?>
                </h2>
                <p class="wwp-reports-teaser__heading-desc">
                    <?php esc_html_e( "Comprehensive view of your store's financial performance, highlighting wholesale contributions and retail comparisons.", 'woocommerce-wholesale-prices' ); ?>
                </p>
            </div>

            <div class="wwp-reports-teaser__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Report sections', 'woocommerce-wholesale-prices' ); ?>">
                <button type="button" class="wwp-reports-teaser__tab is-active" tabindex="-1" aria-disabled="true">
                    <span class="wwp-reports-teaser__tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path>
                            <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path>
                            <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path>
                        </svg>
                    </span>
                    <span><?php esc_html_e( 'Total Wholesale Revenue', 'woocommerce-wholesale-prices' ); ?></span>
                </button>
                <button type="button" class="wwp-reports-teaser__tab" tabindex="-1" aria-disabled="true">
                    <span class="wwp-reports-teaser__tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                            <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                        </svg>
                    </span>
                    <span><?php esc_html_e( 'Wholesale Vs Retail Sales', 'woocommerce-wholesale-prices' ); ?></span>
                </button>
            </div>

            <div class="wwp-reports-teaser__filters">
                <div class="wwp-reports-teaser__filters-left">
                    <div class="wwp-reports-teaser__filters-label">
                        <span class="wwp-reports-teaser__filters-label-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <line x1="21" x2="14" y1="4" y2="4"></line>
                                <line x1="10" x2="3" y1="4" y2="4"></line>
                                <line x1="21" x2="12" y1="12" y2="12"></line>
                                <line x1="8" x2="3" y1="12" y2="12"></line>
                                <line x1="21" x2="16" y1="20" y2="20"></line>
                                <line x1="12" x2="3" y1="20" y2="20"></line>
                                <line x1="14" x2="14" y1="2" y2="6"></line>
                                <line x1="8" x2="8" y1="10" y2="14"></line>
                                <line x1="16" x2="16" y1="18" y2="22"></line>
                            </svg>
                        </span>
                        <span><?php esc_html_e( 'Filters:', 'woocommerce-wholesale-prices' ); ?></span>
                    </div>
                    <span class="wwp-reports-teaser__filter-select">
                        <?php esc_html_e( 'Last 30 days', 'woocommerce-wholesale-prices' ); ?>
                        <span aria-hidden="true">&#9662;</span>
                    </span>
                    <span class="wwp-reports-teaser__filter-select">
                        <?php esc_html_e( 'All Roles', 'woocommerce-wholesale-prices' ); ?>
                        <span aria-hidden="true">&#9662;</span>
                    </span>
                </div>
                <div class="wwp-reports-teaser__filters-right">
                    <span class="wwp-reports-teaser__toggle" aria-hidden="true"></span>
                    <span><?php esc_html_e( 'Comparison Mode', 'woocommerce-wholesale-prices' ); ?></span>
                </div>
            </div>

            <div class="wwp-reports-teaser__hero">
                <div class="wwp-reports-teaser__hero-glow" aria-hidden="true"></div>
                <div class="wwp-reports-teaser__hero-content">
                    <p class="wwp-reports-teaser__hero-eyebrow">
                        <span class="wwp-reports-teaser__hero-eyebrow-line" aria-hidden="true"></span>
                        <?php esc_html_e( 'TOTAL WHOLESALE REVENUE', 'woocommerce-wholesale-prices' ); ?>
                    </p>
                    <h2 class="wwp-reports-teaser__hero-value">$24,580.00</h2>
                    <div class="wwp-reports-teaser__hero-delta is-up">
                        <span aria-hidden="true">&#9650;</span>
                        <span><?php esc_html_e( '+18.4% vs previous period', 'woocommerce-wholesale-prices' ); ?></span>
                    </div>
                </div>
                <div class="wwp-reports-teaser__hero-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                        <line x1="12" x2="12" y1="2" y2="22"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                </div>
            </div>

            <div class="wwp-reports-teaser__stats">
                <div class="wwp-reports-teaser__stat wwp-reports-teaser__stat--blue">
                    <div class="wwp-reports-teaser__stat-row">
                        <div class="wwp-reports-teaser__stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <circle cx="8" cy="21" r="1"></circle>
                                <circle cx="19" cy="21" r="1"></circle>
                                <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
                            </svg>
                        </div>
                        <p class="wwp-reports-teaser__stat-label">
                            <?php esc_html_e( 'Total Orders', 'woocommerce-wholesale-prices' ); ?>
                        </p>
                    </div>
                    <p class="wwp-reports-teaser__stat-value">342</p>
                </div>
                <div class="wwp-reports-teaser__stat wwp-reports-teaser__stat--amber">
                    <div class="wwp-reports-teaser__stat-row">
                        <div class="wwp-reports-teaser__stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                                <polyline points="16 7 22 7 22 13"></polyline>
                            </svg>
                        </div>
                        <p class="wwp-reports-teaser__stat-label">
                            <?php esc_html_e( 'Yearly Growth Rate', 'woocommerce-wholesale-prices' ); ?>
                        </p>
                    </div>
                    <p class="wwp-reports-teaser__stat-value">+24.6%</p>
                </div>
                <div class="wwp-reports-teaser__stat wwp-reports-teaser__stat--green">
                    <div class="wwp-reports-teaser__stat-row">
                        <div class="wwp-reports-teaser__stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <circle cx="12" cy="12" r="10"></circle>
                                <circle cx="12" cy="10" r="3"></circle>
                                <path d="M7 20.662V19a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v1.662"></path>
                            </svg>
                        </div>
                        <p class="wwp-reports-teaser__stat-label">
                            <?php esc_html_e( 'Unique Customers', 'woocommerce-wholesale-prices' ); ?>
                        </p>
                    </div>
                    <p class="wwp-reports-teaser__stat-value">127</p>
                </div>
            </div>

            <div class="wwp-reports-teaser__heading wwp-reports-teaser__heading--secondary">
                <h2 class="wwp-reports-teaser__heading-title">
                    <?php esc_html_e( 'Product Sales Overview', 'woocommerce-wholesale-prices' ); ?>
                </h2>
                <p class="wwp-reports-teaser__heading-desc">
                    <?php esc_html_e( 'Identify your top-performing products ranked by volume and revenue to optimize inventory and marketing.', 'woocommerce-wholesale-prices' ); ?>
                </p>
            </div>

            <div class="wwp-reports-teaser__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Product Sales Overview sections', 'woocommerce-wholesale-prices' ); ?>">
                <button type="button" class="wwp-reports-teaser__tab is-active" tabindex="-1" aria-disabled="true">
                    <span class="wwp-reports-teaser__tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <path d="m7.5 4.27 9 5.15"></path>
                            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                            <path d="m3.3 7 8.7 5 8.7-5"></path>
                            <path d="M12 22V12"></path>
                        </svg>
                    </span>
                    <span><?php esc_html_e( 'Top Products by Wholesale Volume', 'woocommerce-wholesale-prices' ); ?></span>
                </button>
                <button type="button" class="wwp-reports-teaser__tab" tabindex="-1" aria-disabled="true">
                    <span class="wwp-reports-teaser__tab-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                            <line x1="12" x2="12" y1="2" y2="22"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </span>
                    <span><?php esc_html_e( 'Top Products by Wholesale Revenue', 'woocommerce-wholesale-prices' ); ?></span>
                </button>
            </div>

            <div class="wwp-reports-teaser__filters">
                <div class="wwp-reports-teaser__filters-left">
                    <div class="wwp-reports-teaser__filters-label">
                        <span class="wwp-reports-teaser__filters-label-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                <line x1="21" x2="14" y1="4" y2="4"></line>
                                <line x1="10" x2="3" y1="4" y2="4"></line>
                                <line x1="21" x2="12" y1="12" y2="12"></line>
                                <line x1="8" x2="3" y1="12" y2="12"></line>
                                <line x1="21" x2="16" y1="20" y2="20"></line>
                                <line x1="12" x2="3" y1="20" y2="20"></line>
                            </svg>
                        </span>
                        <span><?php esc_html_e( 'Filters:', 'woocommerce-wholesale-prices' ); ?></span>
                    </div>
                    <span class="wwp-reports-teaser__filter-select">
                        <?php esc_html_e( 'Last 30 days', 'woocommerce-wholesale-prices' ); ?>
                        <span aria-hidden="true">&#9662;</span>
                    </span>
                    <span class="wwp-reports-teaser__filter-select">
                        <?php esc_html_e( 'All Roles', 'woocommerce-wholesale-prices' ); ?>
                        <span aria-hidden="true">&#9662;</span>
                    </span>
                    <span class="wwp-reports-teaser__filter-select">
                        <?php esc_html_e( 'All Categories', 'woocommerce-wholesale-prices' ); ?>
                        <span aria-hidden="true">&#9662;</span>
                    </span>
                </div>
            </div>

            <div class="wwp-reports-teaser__products-table">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="is-center"><?php esc_html_e( 'ID', 'woocommerce-wholesale-prices' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Product', 'woocommerce-wholesale-prices' ); ?></th>
                            <th scope="col" class="is-center"><?php esc_html_e( 'Total Units Sold', 'woocommerce-wholesale-prices' ); ?></th>
                            <th scope="col" class="is-center"><?php esc_html_e( 'Total Revenue', 'woocommerce-wholesale-prices' ); ?></th>
                            <th scope="col" class="is-center"><?php esc_html_e( 'Average Order Value', 'woocommerce-wholesale-prices' ); ?></th>
                            <th scope="col" class="is-center"><?php esc_html_e( 'Number of Orders', 'woocommerce-wholesale-prices' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="is-center"><span class="wwp-reports-teaser__rank">1</span></td>
                            <td><?php esc_html_e( 'Premium Coffee Beans 1kg', 'woocommerce-wholesale-prices' ); ?></td>
                            <td class="is-center">184</td>
                            <td class="is-center">$3,680.00</td>
                            <td class="is-center">$80.00</td>
                            <td class="is-center">46</td>
                        </tr>
                        <tr>
                            <td class="is-center"><span class="wwp-reports-teaser__rank">2</span></td>
                            <td><?php esc_html_e( 'Artisan Pasta Variety Pack', 'woocommerce-wholesale-prices' ); ?></td>
                            <td class="is-center">212</td>
                            <td class="is-center">$2,544.00</td>
                            <td class="is-center">$72.40</td>
                            <td class="is-center">35</td>
                        </tr>
                        <tr>
                            <td class="is-center"><span class="wwp-reports-teaser__rank">3</span></td>
                            <td><?php esc_html_e( 'Organic Olive Oil 5L', 'woocommerce-wholesale-prices' ); ?></td>
                            <td class="is-center">96</td>
                            <td class="is-center">$2,880.00</td>
                            <td class="is-center">$120.00</td>
                            <td class="is-center">24</td>
                        </tr>
                        <tr>
                            <td class="is-center"><span class="wwp-reports-teaser__rank">4</span></td>
                            <td><?php esc_html_e( 'Cold-Pressed Honey 1L', 'woocommerce-wholesale-prices' ); ?></td>
                            <td class="is-center">132</td>
                            <td class="is-center">$1,980.00</td>
                            <td class="is-center">$66.00</td>
                            <td class="is-center">30</td>
                        </tr>
                        <tr>
                            <td class="is-center"><span class="wwp-reports-teaser__rank">5</span></td>
                            <td><?php esc_html_e( 'Single-Origin Cocoa 2kg', 'woocommerce-wholesale-prices' ); ?></td>
                            <td class="is-center">74</td>
                            <td class="is-center">$2,220.00</td>
                            <td class="is-center">$92.50</td>
                            <td class="is-center">24</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </main>

    </div>

    <div
        id="wwp-reports-teaser-modal"
        class="wwp-reports-teaser__modal is-hidden"
        role="dialog"
        aria-modal="true"
        aria-hidden="true"
        aria-labelledby="wwp-reports-teaser-modal-title"
        aria-describedby="wwp-reports-teaser-modal-desc"
    >
        <div class="wwp-reports-teaser__modal-backdrop" data-wwp-reports-teaser-close="true"></div>
        <div class="wwp-reports-teaser__modal-card" role="document">
            <button
                type="button"
                class="wwp-reports-teaser__modal-close"
                data-wwp-reports-teaser-close="true"
                aria-label="<?php esc_attr_e( 'Close', 'woocommerce-wholesale-prices' ); ?>"
            >
                <span aria-hidden="true">&times;</span>
            </button>

            <div class="wwp-reports-teaser__modal-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="32" height="32" focusable="false">
                    <path
                        fill="currentColor"
                        d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 5a3 3 0 1 1 6 0v3H9V7Zm3 7a2 2 0 0 1 1 3.732V19a1 1 0 1 1-2 0v-1.268A2 2 0 0 1 12 14Z"
                    />
                </svg>
            </div>

            <h3 id="wwp-reports-teaser-modal-title" class="wwp-reports-teaser__modal-title">
                <?php esc_html_e( 'Unlock Wholesale Reports', 'woocommerce-wholesale-prices' ); ?>
            </h3>

            <p id="wwp-reports-teaser-modal-desc" class="wwp-reports-teaser__modal-desc">
                <?php esc_html_e( 'Track wholesale-specific sales, top wholesale customers, revenue by wholesale role, and more. Available in Wholesale Prices Premium.', 'woocommerce-wholesale-prices' ); ?>
            </p>

            <div class="wwp-reports-teaser__modal-actions">
                <button
                    type="button"
                    class="wwp-reports-teaser__btn wwp-reports-teaser__btn--secondary"
                    data-wwp-reports-teaser-close="true"
                >
                    <?php esc_html_e( 'View Sample Reports', 'woocommerce-wholesale-prices' ); ?>
                </button>
                <a
                    href="<?php echo esc_url( $wwp_reports_teaser_unlock_url ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="wwp-reports-teaser__btn wwp-reports-teaser__btn--primary"
                >
                    <?php esc_html_e( 'Unlock Reports', 'woocommerce-wholesale-prices' ); ?>
                </a>
            </div>
        </div>
    </div>
</div>
