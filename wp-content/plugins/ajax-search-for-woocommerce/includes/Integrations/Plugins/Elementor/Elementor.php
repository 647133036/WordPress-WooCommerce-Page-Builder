<?php

namespace DgoraWcas\Integrations\Plugins\Elementor;

use DgoraWcas\Integrations\Plugins\AbstractPluginIntegration;
use Elementor\Elements_Manager;
use Elementor\Widgets_Manager;
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class Elementor extends AbstractPluginIntegration {
    protected const VERSION_CONST = 'ELEMENTOR_PRO_VERSION';

    protected const MIN_VERSION = '3.6.0';

    protected const LABEL = 'Elementor Pro';

    public function init() : void {
        add_action( 'elementor/widgets/register', [$this, 'registerWidgets'], 20 );
        add_action( 'elementor/editor/before_enqueue_scripts', [$this, 'editorEnqueueScripts'] );
        add_action( 'wp_enqueue_scripts', [$this, 'enqueuePopupFixes'], 20 );
    }

    /**
     * @param Widgets_Manager $widgets_manager
     *
     * @return void
     */
    public function registerWidgets( $widgets_manager ) {
        // Register "FiboSearch" widget.
        $widgets_manager->register( new FiboSearchWidget() );
    }

    /**
     * @return void
     */
    public function editorEnqueueScripts() {
        wp_enqueue_style(
            'fibosearch-elementor-fibosearchicon',
            DGWT_WCAS_URL . 'assets/elementor-icons/style.css',
            [],
            DGWT_WCAS_VERSION
        );
    }

    /**
     * Elementor popups move focus to the close button after popup animations.
     * FiboSearch needs a dedicated guard here instead of a generic core workaround.
     */
    public function enqueuePopupFixes() : void {
        ob_start();
        ?>
		<style>
			.elementor-popup-modal .dgwt-wcas-style-pirx.dgwt-wcas-search-filled .dgwt-wcas-sf-wrapp button.dgwt-wcas-search-submit {
				animation: none;
			}
		</style>
		<?php 
        $css = (string) ob_get_clean();
        wp_add_inline_style( 'dgwt-wcas-style', str_replace( ['<style>', '</style>'], '', $css ) );
        ob_start();
        ?>
		<script>
			(function($) {
				$(function() {
					if ($(document).data('fibosearchElementorPopupFocusGuardBound')) {
						return;
					}

					$(document).data('fibosearchElementorPopupFocusGuardBound', true);

					var keepFocusOnInput = function($modal, input, tokenKey, maxChecks) {
						var guardToken = Date.now() + Math.random();
						var checks = 0;

						$modal.data(tokenKey, guardToken);

						var interval = setInterval(function() {
							var activeElement = document.activeElement;

							checks++;

							if (
								$modal.data(tokenKey) !== guardToken ||
								!document.body.contains(input) ||
								checks > maxChecks
							) {
								clearInterval(interval);
								return;
							}

							if (!$(input).is(':visible')) {
								return;
							}

							if (
								activeElement &&
								$(activeElement).closest('.elementor-popup-modal')[0] !== $modal[0]
							) {
								clearInterval(interval);
								return;
							}

							if (
								activeElement !== input &&
								(
									!activeElement ||
									activeElement === document.body ||
									$(activeElement).hasClass('dialog-close-button') ||
									activeElement === $modal.find('.dialog-widget-content')[0]
								)
							) {
								input.focus();
							}
						}, 60);
					};

					$(document).on('focusin', '.elementor-popup-modal .dgwt-wcas-search-input', function() {
						var $modal = $(this).closest('.elementor-popup-modal');
						keepFocusOnInput($modal, this, 'fibosearchFocusGuardToken', 25);
					});
				});
			})(jQuery);
		</script>
		<?php 
        $js = (string) ob_get_clean();
        wp_add_inline_script( 'jquery-dgwt-wcas', trim( str_replace( ['<script>', '</script>'], '', $js ) ), 'after' );
    }

}
