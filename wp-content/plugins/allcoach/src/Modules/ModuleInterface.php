<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

interface ModuleInterface {

	/**
	 * Register module services and bindings.
	 *
	 * Called before boot(), used for registering services in the container.
	 */
	public function register(): void;

	/**
	 * Boot the module.
	 *
	 * Called after all modules are registered.
	 */
	public function boot(): void;

	/**
	 * Check if module is enabled.
	 */
	public function isEnabled(): bool;

	/**
	 * Get module unique identifier.
	 */
	public function getId(): string;

	/**
	 * Get module name.
	 */
	public function getName(): string;

	/**
	 * Get module description.
	 */
	public function getDescription(): string;

	/**
	 * Get module version.
	 */
	public function getVersion(): string;

	/**
	 * Get module dependencies (array of module IDs).
	 */
	public function getDependencies(): array;

	/**
	 * Enable the module.
	 */
	public function enable(): void;

	/**
	 * Disable the module.
	 */
	public function disable(): void;
}
