<?php
namespace Automattic\Jetpack\Autoloader\jp92966be9e78bf75e6d5a61a4f8a8f974\al5_0_8;
if (!defined('ABSPATH')) exit;
 // phpcs:ignore
class Hook_Manager {
 private $registered_hooks;
 public function __construct() {
 $this->registered_hooks = array();
 }
 public function add_action( $tag, $callable, $priority = 10, $accepted_args = 1 ) {
 $this->registered_hooks[ $tag ][] = array(
 'priority' => $priority,
 'callable' => $callable,
 );
 add_action( $tag, $callable, $priority, $accepted_args );
 }
 public function add_filter( $tag, $callable, $priority = 10, $accepted_args = 1 ) {
 $this->registered_hooks[ $tag ][] = array(
 'priority' => $priority,
 'callable' => $callable,
 );
 add_filter( $tag, $callable, $priority, $accepted_args );
 }
 public function reset() {
 foreach ( $this->registered_hooks as $tag => $hooks ) {
 foreach ( $hooks as $hook ) {
 remove_filter( $tag, $hook['callable'], $hook['priority'] );
 }
 }
 $this->registered_hooks = array();
 }
}
