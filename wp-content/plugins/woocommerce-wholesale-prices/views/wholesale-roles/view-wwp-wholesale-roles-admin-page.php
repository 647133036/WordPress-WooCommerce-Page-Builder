<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id='wwpp-wholesale-roles-page' class='wwp-page wrap nosubsub'>
    <h2><?php esc_html_e( 'Wholesale Roles', 'woocommerce-wholesale-prices' ); ?></h2>
    <a href="#" class="page-title-action"><?php esc_html_e( 'Add New Role', 'woocommerce-wholesale-prices' ); ?></a>
    <div id="col-container">
        <div id="col-right">
            <div class="col-wrap">
                <div>
                    <table id="wholesale-roles-table" class="wp-list-table widefat fixed tags" style="margin-top: 74px;">

                        <thead>
                            <tr>
                                <th scope="col" id="role-name" class="manage-column column-role-name"><span><?php esc_html_e( 'Name', 'woocommerce-wholesale-prices' ); ?></span></th>
                                <th scope="col" id="role-key" class="manage-column column-role-key"><span><?php esc_html_e( 'Key', 'woocommerce-wholesale-prices' ); ?></span></th>
                                <th scope="col" id="role-desc" class="manage-column column-role-desc"><span><?php esc_html_e( 'Description', 'woocommerce-wholesale-prices' ); ?></span></th>
                            </tr>
                        </thead>

                        <tbody id="the-list">
                            <?php
                            $count = 0;
                            foreach ( $all_registered_wholesale_roles as $role_key => $role ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                                $count++; // phpcs:ignore
                                $alternate = '';

                                if ( 0 !== $count % 2 ) {
                                    $alternate = 'alternate';
                                }
                            ?>

                            <tr id="<?php echo esc_attr( $role_key ); ?>" class="<?php echo esc_attr( $alternate ); ?>">

                                <td class="role-name column-role-name">

                                    <?php if ( array_key_exists( 'main', $role ) && $role['main'] ) : ?>

                                        <strong><a class="main-role-name"><?php echo esc_html( $role['roleName'] ); ?></a></strong>

                                        <div class="row-actions">
                                            <span class="edit"><a class="edit-role" href="#"><?php esc_html_e( 'Edit', 'woocommerce-wholesale-prices' ); ?></a>
                                        </div>

                                    <?php else : ?>

                                        <strong><a><?php echo esc_html( $role['roleName'] ); ?></a></strong><br>

                                        <div class="row-actions">
                                            <span class="edit"><a class="edit-role" href="#"><?php esc_html_e( 'Edit', 'woocommerce-wholesale-prices' ); ?></a> | </span>
                                            <span class="delete"><a class="delete-role" href="#"><?php esc_html_e( 'Delete', 'woocommerce-wholesale-prices' ); ?></a></span>
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <td class="role-key column-role-key"><?php echo esc_html( $role_key ); ?></td>

                                <td class="role-desc column-role-desc"><?php echo esc_html( $role['desc'] ); ?></td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>

                        <tfoot>
                            <tr>
                                <th scope="col" id="role-name" class="manage-column column-role-name"><span><?php esc_html_e( 'Name', 'woocommerce-wholesale-prices' ); ?></span></th>
                                <th scope="col" id="role-key" class="manage-column column-role-key"><span><?php esc_html_e( 'Key', 'woocommerce-wholesale-prices' ); ?></span></th>
                                <th scope="col" id="role-desc" class="manage-column column-role-desc"><span><?php esc_html_e( 'Description', 'woocommerce-wholesale-prices' ); ?></span></th>
                            </tr>
                        </tfoot>

                    </table>

                    <br class="clear">
                </div>
                <div class="upsell-area">
                    <h1><?php esc_html_e( 'Add additional wholesale roles', 'woocommerce-wholesale-prices' ); ?></h1>
                    <p><?php esc_html_e( 'You\'re currently using the free version of WooCommerce Wholesale Prices which lets you have one level of wholesale customers.', 'woocommerce-wholesale-prices' ); ?></p>
                    <p>
                        <?php
                        echo wp_kses_post(
                            sprintf(
                                // translators: %1$s <a> link to premium add-on, %2$s </a> closing tag.
                                __(
                                    'In the %1$sPremium add-on%2$s you can add multiple wholesale roles. This will let you create separate "levels" of wholesale customers,
                                                    each of which can have separate wholesale pricing, shipping and payment mapping, order minimums and more.',
                                    'woocommerce-wholesale-prices'
                                ),
                                sprintf(
                                    '<a href="%s" target="_blank">',
                                    esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwprolespagelink' ) )
                                ),
                                '</a>'
                            )
                        );
                        ?>
                    </p>
                    <p>
                        <a class="button" href="<?php echo esc_url( WWP_Helper_Functions::get_utm_url( 'woocommerce-wholesale-prices-premium', 'wwp', 'upsell', 'wwprolespagebutton' ) ); ?>" target="_blank">
                            <?php esc_html_e( 'See the full feature list', 'woocommerce-wholesale-prices' ); ?>
                            <span class="dashicons dashicons-arrow-right-alt" style="margin-top: 7px"></span>
                        </a>
                    </p>
                </div>
            </div><!--.col-wrap-->

        </div><!--#col-right-->

        <div id="col-left">

            <div class="col-wrap">

                <div class="form-wrap">
                    <h3><?php esc_html_e( 'Edit Wholesale Role', 'woocommerce-wholesale-prices' ); ?></h3>

                    <div id="wholesale-form">

                        <div class="form-field form-required">
                            <label for="role-name"><?php esc_html_e( 'Role Name', 'woocommerce-wholesale-prices' ); ?></label>
                            <input id="role-name" value="" size="40" type="text">
                            <p><?php esc_html_e( 'Required. Recommended to be unique.', 'woocommerce-wholesale-prices' ); ?></p>
                        </div>

                        <div class="form-field form-required">
                            <label for="role-key"><?php esc_html_e( 'Role Key', 'woocommerce-wholesale-prices' ); ?></label>
                            <input id="role-key" value="" size="40" type="text">
                            <p><?php esc_html_e( 'Required. Must be unique. Must only contain letters, numbers and underscores', 'woocommerce-wholesale-prices' ); ?></p>
                        </div>

                        <div class="form-field form-required">
                            <label for="role-desc"><?php esc_html_e( 'Description', 'woocommerce-wholesale-prices' ); ?></label>
                            <textarea id="role-desc" rows="5" cols="40"></textarea>
                            <p><?php esc_html_e( 'Optional.', 'woocommerce-wholesale-prices' ); ?></p>
                        </div>

                        <p class="submit edit-controls">
                            <input id="edit-wholesale-role-submit" class="button button-primary" value="<?php esc_html_e( 'Edit Wholesale Role', 'woocommerce-wholesale-prices' ); ?>" type="button"><span class="spinner"></span>
                            <input id="cancel-edit-wholesale-role-submit" class="button button-secondary" value="<?php esc_html_e( 'Cancel Edit', 'woocommerce-wholesale-prices' ); ?>" type="button"/>
                        </p>

                    </div>
                </div>

            </div><!--.col-wrap-->

        </div><!--#col-left-->

    </div><!--#col-container-->

</div><!--#wwpp-wholesale-roles-page-->
