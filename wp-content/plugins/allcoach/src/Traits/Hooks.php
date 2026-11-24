<?php

declare(strict_types=1);

namespace AllCoach\Traits;

defined( 'ABSPATH' ) || exit;

trait Hooks {

	/**
	 * Add a callback to an action or filter hook.
	 *
	 * @param string $type 'action' or 'filter'
	 * @param string $hookName The name of the hook to add the callback to.
	 * @param callable|string|array $callback The callback to be run.
	 * @param int $priority Optional. Default 10.
	 * @param int $acceptedArgs Optional. Default 1.
	 * @return bool Always returns true.
	 */
	private function addHook( string $type, string $hookName, callable|string|array $callback, int $priority = 10, int $acceptedArgs = 1 ): bool {
		$function = "add_{$type}";
		return call_user_func_array( $function, [ $hookName, $callback, $priority, $acceptedArgs ] );
	}

	/**
	 * Add a callback to an action hook.
	 *
	 * @param string $hookName The name of the hook to add the callback to.
	 * @param callable|string|array $callback The callback to be run.
	 * @param int $priority Optional. Default 10.
	 * @param int $acceptedArgs Optional. Default 1.
	 * @return bool Always returns true.
	 */
	public function addAction( string $hookName, callable|string|array $callback, int $priority = 10, int $acceptedArgs = 1 ): bool {
		return $this->addHook( 'action', $hookName, $callback, $priority, $acceptedArgs );
	}

	/**
	 * Add a callback to an filter hook.
	 *
	 * @param string $hookName The name of the hook to add the callback to.
	 * @param callable|string|array $callback The callback to be run.
	 * @param int $priority Optional. Default 10.
	 * @param int $acceptedArgs Optional. Default 1.
	 * @return bool Always returns true.
	 */
	public function addFilter( string $hookName, callable|string|array $callback, int $priority = 10, int $acceptedArgs = 1 ): bool {
		return $this->addHook( 'filter', $hookName, $callback, $priority, $acceptedArgs );
	}

	/**
	 * Removes a callback function from a filter or action hook.
	 *
	 * @param string $type 'filter' or 'action'
	 * @param string $hookName The hook name.
	 * @param callable|string|array $callback The callback to be removed.
	 * @param int $priority Optional. Default 10.
	 * @return bool Whether the function existed before it was removed.
	 */
	private function removeHook( string $type, string $hookName, $callback, int $priority = 10 ): bool {
		$function = "remove_{$type}";
		return $function( $hookName, $callback, $priority );
	}

	/**
	 * Removes a callback function from a filter hook.
	 *
	 * @param string $hookName The hook name.
	 * @param callable|string|array $callback The callback to be removed.
	 * @param int $priority Optional. Default 10.
	 * @return bool Whether the function existed before it was removed.
	 */
	public function removeFilter( string $hookName, callable|string|array $callback, int $priority = 10 ): bool {
		return $this->removeHook( 'filter', $hookName, $callback, $priority );
	}

	/**
	 * Removes a callback function from a action hook.
	 *
	 * @param string $type 'filter' or 'action'
	 * @param string $hookName The hook name.
	 * @param callable|string|array $callback The callback to be removed.
	 * @param int $priority Optional. Default 10.
	 * @return bool Whether the function existed before it was removed.
	 */
	public function removeAction( string $hookName, callable|string|array $callback, int $priority = 10 ): bool {
		return $this->removeHook( 'action', $hookName, $callback, $priority );
	}

	/**
	 * Do action with arguments.
	 *
	 * @param string $hookName The action hook to execute.
	 * @param mixed ...$args Additional arguments.
	 * @return void
	 */
	public function doAction( string $hookName, ...$args ): void {
		do_action_ref_array( $hookName, $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}

	/**
	 * Apply filters with arguments.
	 *
	 * @param string $hookName The filter hook to apply.
	 * @param mixed $value The value to filter.
	 * @param mixed ...$args Additional arguments.
	 * @return mixed The filtered value.
	 */
	public function applyFilters( string $hookName, mixed $value, ...$args ) {
		return apply_filters_ref_array( $hookName, array_merge( [ $value ], $args ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}
}
