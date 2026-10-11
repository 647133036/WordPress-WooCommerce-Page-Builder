<?php
namespace WOOCS\Rates\Aggregators;

/**
 * Abstract class for rate aggregator
 *
 * @author Pavlo
 */
abstract class RateProvider {

	protected string $name      = '';
	protected string $lastError = '';
	protected string $base      = '';
	protected string $key       = '';

	public function __construct( string $base, string $key = '' ) {
		$this->base = $base;
		$this->key  = $key;
	}

	public function getRate( string $to ): float {
		$this->resetError();
		$url      = $this->getApiUrl( $to );
		$response = $this->doRequest( $url );
		return $this->parseResponse( $response, $to );
	}

	public function getName(): string {
		return $this->name;
	}

	protected function doRequest( string $url ) {
		$data = array();

		try {
			$response = \wp_remote_get( $url, array( 'timeout' => 15 ) );

			if ( \is_wp_error( $response ) ) {
				$this->setError( $response->get_error_message() );
				\error_log( '[WOOCS] wp_remote_get error: ' . $response->get_error_message() . ' | URL: ' . $url );// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return $data;
			}

			$json_response = \wp_remote_retrieve_body( $response );
			$data          = json_decode( $json_response, true );

			if ( $data === null ) {
				$this->setError( 'Invalid JSON response' );
				\error_log( '[WOOCS] Invalid JSON from: ' . $url );// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		} catch ( \Exception $e ) {
			$this->setError( \esc_html__( 'It looks like the aggregator server sent an incorrect response.', 'woocommerce-currency-switcher' ) );
			\error_log( '[WOOCS] Exception in doRequest: ' . $e->getMessage() );// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $data;
	}

	abstract protected function parseResponse( $response, string $to ): float;

	abstract protected function getApiUrl( string $to ): string;

	protected function setError( string $m ) {
		$this->lastError = $m;
	}

	public function resetError() {
		$this->lastError = '';
	}

	public function getLastError(): string {
		return $this->lastError;
	}
}
