<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for=""><?php esc_html_e( 'Knowledge Base', 'woocommerce-wholesale-prices' ); ?></label>
    </th>
    <td class="forminp forminp-help_resources_controls">
        <?php
            echo wp_kses_post(
                sprintf(
                    // translators: %1$s: <a> link to knowledge base, %2$s: </a> closing link tag.
                    __( 'Looking for documentation? Please see our growing %1$sKnowledge Base%2$s', 'woocommerce-wholesale-prices' ),
                    sprintf(
                        '<a href="%s" target="_blank">',
                        esc_url( WWP_Helper_Functions::get_utm_url( 'knowledge-base', 'wwp', 'kb', 'helppagekblink' ) )
                    ),
                    '</a>'
                )
            );
        ?>
    </td>
</tr>
