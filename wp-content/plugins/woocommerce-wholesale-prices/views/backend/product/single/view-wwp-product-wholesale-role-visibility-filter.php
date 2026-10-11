<script>
    jQuery(document).ready(function($) {

        $("#wholesale-visibility-select").chosen({
            width: '100%'
        });

    });
</script>
<div id="wholesale-visiblity" class="misc-pub-section">

    <strong><?php esc_html_e( 'Restrict To Wholesale Roles:', 'woocommerce-wholesale-prices' ); ?></strong>
    <p><i><?php esc_html_e( 'Set this product to be visible only to specified wholesale user role/s only', 'woocommerce-wholesale-prices' ); ?></i></p>

    <div id="wholesale-visibility-select-container" style="display: flex;">

        <select style="width: 100%;" data-placeholder="<?php esc_attr_e( 'Choose wholesale users...', 'woocommerce-wholesale-prices' ); ?>" name="wholesale-visibility-select[]" id="wholesale-visibility-select" multiple>

            <?php foreach ( $all_registered_wholesale_roles as $role_key => $role_data ) { ?>
                <option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_data['roleName'] ); ?></option>
            <?php } ?>

        </select>

    </div>

</div>
