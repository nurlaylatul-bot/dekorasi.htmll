<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Contracts\ResolverInterface;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

class Container implements ResolverInterface {

	private ContainerInterface $container;
	private bool $compiled = false;

	public function __construct() {
		$this->buildContainer();
	}

	private function buildContainer(): void {
		$builder = new ContainerBuilder();

		$builder->useAutowiring( true );
		$builder->useAttributes( true );

		// if ( $this->isProduction() ) {
		//  $builder->enableCompilation( WP_CONTENT_DIR . '/cache/allcoach' );
		//  $builder->writeProxiesToFile( true, WP_CONTENT_DIR . '/cache/allcoach/proxies' );
		//  $this->compiled = true;
		// }

		$this->loadServiceDefinitions( $builder );

		$this->container = $builder->build();
		$this->container->set( ResolverInterface::class, $this );
		$this->container->set( self::class, $this );
	}

	private function loadServiceDefinitions( ContainerBuilder $builder ): void {
		$servicesFile = Constants::path( 'config/services.php' );

		if ( file_exists( $servicesFile ) ) {
			$definitions = require $servicesFile;
			$builder->addDefinitions( $definitions );
		}
	}

	public function get( string $id ): mixed {
		return $this->container->get( $id );
	}

	public function has( string $id ): bool {
		return $this->container->has( $id );
	}

	public function make( string $id, array $parameters = [] ): mixed {
		return $this->container->make( $id, $parameters );
	}

	public function set( string $id, mixed $value ): void {
		$this->container->set( $id, $value );
	}

	public function getContainer(): ContainerInterface {
		return $this->container;
	}

	private function isProduction(): bool {
		return ! defined( 'ALLCOACH_ENV' ) || 'development' !== constant( 'ALLCOACH_ENV' );
	}

	public function isCompiled(): bool {
		return $this->compiled;
	}
}
