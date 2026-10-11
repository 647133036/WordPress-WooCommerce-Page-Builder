<?php

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access allowed' );
}

/**
 * WordPress cron substitute
 *
 * @author realmag777
 * @site https://pluginus.net
 *
 */
final class PN_WP_CRON_WOOCS {

	public $actions  = array();
	public $cron_key = null;

	/**
	 * Hooks already executed within the current request, used to make sure one
	 * request never runs the same hook more than once.
	 *
	 * @var array
	 */
	private $executed = array();

	public function __construct( $key ) {
		$this->cron_key = $key;
		$this->actions  = get_option( $this->cron_key, array() );

		if ( ! is_array( $this->actions ) ) {
			$this->actions = array();
		}
	}

	/**
	 * Run every event whose time has come.
	 */
	public function process() {

		if ( empty( $this->actions ) ) {
			return;
		}

		$now     = time();
		$changed = false;

		foreach ( $this->actions as $action_hook => $event ) {

			if ( ! isset( $event['next'] ) || ! isset( $event['recurrence'] ) ) {
				continue;
			}

			if ( (int) $event['next'] > $now ) {
				continue;
			}

			// One request never runs the same hook twice.
			if ( isset( $this->executed[ $action_hook ] ) ) {
				continue;
			}

			$this->executed[ $action_hook ] = true;

			// Reschedule before running, so a fatal error inside the hook
			// cannot leave the event permanently due and firing on every
			// request afterwards.
			if ( (int) $event['recurrence'] > 0 ) {
				$event['last_run']             = $now;
				$event['next']                 = $now + (int) $event['recurrence'];
				$this->actions[ $action_hook ] = $event;
				$changed                       = true;
			} else {
				unset( $this->actions[ $action_hook ] );
				$changed = true;
			}

			$_REQUEST['woocs_cron_running'] = true;// just marker for another applications

			try {
				do_action( $action_hook );
			} catch ( \Throwable $e ) {
				error_log( '[WOOCS CRON] Hook ' . $action_hook . ' failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			}
		}

		if ( $changed ) {
			$this->update();
		}
	}

	/**
	 * Register an event, or adopt a new recurrence for an existing one.
	 *
	 * This method is safe to call on every request. An event that already
	 * exists keeps its schedule, so calling attach() repeatedly can never
	 * postpone a pending run.
	 *
	 * @param string $hook       Action hook name.
	 * @param int    $start_time Unix timestamp the schedule starts from.
	 * @param int    $recurrence Interval in seconds.
	 */
	public function attach( $hook, $start_time, $recurrence ) {

		$recurrence = (int) $recurrence;
		$start_time = (int) $start_time;

		if ( $recurrence <= 0 ) {
			return;
		}

		// New event: schedule the first run one full interval from the start.
		if ( ! isset( $this->actions[ $hook ] ) ) {
			$this->set_event( $hook, $start_time, $start_time + $recurrence, $recurrence, 0 );
			return;
		}

		$event = $this->actions[ $hook ];

		$stored_recurrence = isset( $event['recurrence'] ) ? (int) $event['recurrence'] : 0;

		// Same interval: nothing to do. Leaving next untouched here is exactly
		// what keeps long intervals working on sites with constant traffic.
		if ( $stored_recurrence === $recurrence ) {
			return;
		}

		// The interval changed. Recalculate from the last run (or from the
		// original start time if the event never ran) instead of from now, and
		// never allow next to sit further ahead than one full new interval.
		$now      = time();
		$anchor   = isset( $event['last_run'] ) && (int) $event['last_run'] > 0 ? (int) $event['last_run'] : (int) $event['start_time'];
		$next     = $anchor + $recurrence;
		$next     = min( $next, $now + $recurrence );
		$last_run = isset( $event['last_run'] ) ? (int) $event['last_run'] : 0;

		$this->set_event( $hook, (int) $event['start_time'], $next, $recurrence, $last_run );
	}

	/**
	 * Restart the schedule from now. Called when the settings are saved.
	 *
	 * @param string $hook       Action hook name.
	 * @param int    $recurrence Interval in seconds.
	 */
	public function reset( $hook, $recurrence ) {

		$recurrence = (int) $recurrence;

		if ( $recurrence <= 0 ) {
			return;
		}

		$now = time();

		$this->set_event( $hook, $now, $now + $recurrence, $recurrence, 0 );
	}

	/**
	 * Whether the hook is registered, optionally with a specific recurrence.
	 *
	 * @param string $hook       Action hook name.
	 * @param int    $recurrence Interval in seconds, 0 to ignore the interval.
	 *
	 * @return bool
	 */
	public function is_attached( $hook, $recurrence = 0 ) {

		if ( ! isset( $this->actions[ $hook ] ) ) {
			return false;
		}

		if ( (int) $recurrence !== 0 ) {
			if ( ! isset( $this->actions[ $hook ]['recurrence'] ) || (int) $this->actions[ $hook ]['recurrence'] !== (int) $recurrence ) {
				// The stored interval is outdated, the caller has to attach again.
				return false;
			}
		}

		return true;
	}

	/**
	 * Remove a single event.
	 *
	 * @param string $hook Action hook name.
	 */
	public function remove( $hook ) {

		if ( isset( $this->actions[ $hook ] ) ) {
			unset( $this->actions[ $hook ] );
			$this->update();
		}
	}

	/**
	 * Read the stored state of one event, for diagnostics.
	 *
	 * @param string $hook Action hook name.
	 *
	 * @return array|null Event data with start_time, next, recurrence and last_run.
	 */
	public function get_event( $hook ) {
		return isset( $this->actions[ $hook ] ) ? $this->actions[ $hook ] : null;
	}

	/**
	 * Read the stored state of every event, for diagnostics.
	 *
	 * @return array
	 */
	public function get_events() {
		return $this->actions;
	}

	/**
	 * Run a hook immediately, regardless of its schedule, and move its next run
	 * one full interval ahead. Useful for manual triggers and for testing.
	 *
	 * @param string $hook Action hook name.
	 *
	 * @return bool Whether the hook was executed.
	 */
	public function run_now( $hook ) {

		if ( ! isset( $this->actions[ $hook ] ) ) {
			return false;
		}

		$now   = time();
		$event = $this->actions[ $hook ];

		$recurrence = isset( $event['recurrence'] ) ? (int) $event['recurrence'] : 0;

		if ( $recurrence > 0 ) {
			$event['last_run']     = $now;
			$event['next']         = $now + $recurrence;
			$this->actions[ $hook ] = $event;
			$this->update();
		}

		$_REQUEST['woocs_cron_running'] = true;

		try {
			do_action( $hook );
		} catch ( \Throwable $e ) {
			error_log( '[WOOCS CRON] Manual run of ' . $hook . ' failed: ' . $e->getMessage() );
			return false;
		}

		return true;
	}

	/**
	 * Clear the stored schedule of one hook, or of every hook when no hook is
	 * given. The schedule is rebuilt automatically on the next request from the
	 * current plugin settings.
	 *
	 * @param string $hook Optional action hook name.
	 */
	public function clear( $hook = '' ) {

		if ( ! empty( $hook ) ) {
			$this->remove( $hook );
			return;
		}

		$this->actions = array();
		$this->update();
	}

	/**
	 * Delete the whole scheduler option from the database.
	 */
	public function flush() {
		$this->actions = array();
		delete_option( $this->cron_key );
	}

	public function update() {
		update_option( $this->cron_key, $this->actions, false );
	}

	/**
	 * Write one event into the schedule.
	 *
	 * @param string $hook       Action hook name.
	 * @param int    $start_time Unix timestamp the schedule started from.
	 * @param int    $next       Unix timestamp of the next run.
	 * @param int    $recurrence Interval in seconds.
	 * @param int    $last_run   Unix timestamp of the last run, 0 if never.
	 */
	private function set_event( $hook, $start_time, $next, $recurrence, $last_run = 0 ) {

		$this->actions[ $hook ] = array(
			'start_time' => (int) $start_time,
			'next'       => (int) $next,
			'recurrence' => (int) $recurrence,
			'last_run'   => (int) $last_run,
		);

		$this->update();
	}
}
