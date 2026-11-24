<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Container;
use AllCoach\Traits\Hooks;

abstract class AbstractModule implements ModuleInterface {

	use Hooks;

	protected Container $container;
	protected bool $enabled          = true;
	protected bool $enabledByDefault = false;
	protected string $id             = '';
	protected string $name           = '';
	protected string $description    = '';
	protected string $version        = '1.0.0';
	protected array $dependencies    = [];

	private const STATES_OPTION = '_allcoach_addon_states';
	private const KNOWN_OPTION  = '_allcoach_known_addons';

	public function __construct( Container $container ) {
		$this->container = $container;
		$this->loadModuleState();
	}

	/**
	 * {@inheritdoc}
	 */
	public function register(): void {
		// Override in child classes.
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		// Override in child classes.
	}

	/**
	 * {@inheritdoc}
	 */
	public function isEnabled(): bool {
		return $this->enabled && $this->checkDependencies();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getVersion(): string {
		return $this->version;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDependencies(): array {
		return $this->dependencies;
	}

	/**
	 * {@inheritdoc}
	 */
	public function enable(): void {
		$this->enabled = true;
		$this->saveModuleState();
		$this->doAction( "allcoach:module:{$this->id}:enabled" );
	}

	/**
	 * {@inheritdoc}
	 */
	public function disable(): void {
		$this->enabled = false;
		$this->saveModuleState();
		$this->doAction( "allcoach:module:{$this->id}:disabled" );
	}

	protected function checkDependencies(): bool {
		$registry = ModuleRegistry::getInstance();

		foreach ( $this->dependencies as $dependencyId ) {
			$dependency = $registry->getModule( $dependencyId );

			if ( ! $dependency || ! $dependency->isEnabled() ) {
				return false;
			}
		}

		return true;
	}

	/** Whether this module is on by default before the user has ever toggled it. */
	public function isEnabledByDefault(): bool {
		return $this->enabledByDefault;
	}

	/**
	 * Resolve enabled state from the per-user states map, falling back to the
	 * module's default the first time it is seen (and recording that default so
	 * a later change to the default never silently flips an existing site).
	 */
	protected function loadModuleState(): void {
		$states = get_option( self::STATES_OPTION, [] );
		$states = is_array( $states ) ? $states : [];

		if ( array_key_exists( $this->id, $states ) ) {
			$this->enabled = (bool) $states[ $this->id ];
			return;
		}

		$this->enabled = $this->isEnabledByDefault();

		$known = get_option( self::KNOWN_OPTION, [] );
		$known = is_array( $known ) ? $known : [];
		if ( ! in_array( $this->id, $known, true ) ) {
			$known[] = $this->id;
			update_option( self::KNOWN_OPTION, array_values( $known ) );
			$states[ $this->id ] = $this->enabled;
			update_option( self::STATES_OPTION, $states );
		}
	}

	protected function saveModuleState(): void {
		$states = get_option( self::STATES_OPTION, [] );
		$states = is_array( $states ) ? $states : [];

		$states[ $this->id ] = $this->enabled;
		update_option( self::STATES_OPTION, $states );
	}

	protected function get( string $id ): mixed {
		return $this->container->get( $id );
	}

	protected function has( string $id ): bool {
		return $this->container->has( $id );
	}
}
