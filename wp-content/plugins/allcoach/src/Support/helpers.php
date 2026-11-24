<?php

declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Cache\CacheManager;
use AllCoach\Core\Data\CurrencyData;
use AllCoach\Core\Data\TimezoneData;
use AllCoach\Core\Features\FeatureManager;
use AllCoach\Plugin;

if ( ! function_exists( __NAMESPACE__ . '\\config' ) ) {
	function config( string $key, mixed $defaultValue = null ): mixed {
		static $config = null;

		if ( null === $config ) {
			$container = Plugin::getInstance()->getContainer();
			$config    = $container->get( Config::class );
		}

		return $config->get( $key, $defaultValue );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\cache' ) ) {
	function cache(): CacheManager {
		static $cache = null;

		if ( null === $cache ) {
			$cache = Plugin::getInstance()->getContainer()->get( CacheManager::class );
		}

		return $cache;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\currencies' ) ) {
	function currencies(): CurrencyData {
		static $currencies = null;
		if ( null === $currencies ) {
			$currencies = Plugin::getInstance()->getContainer()->get( CurrencyData::class );
		}
		return $currencies;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\timezones' ) ) {
	function timezones(): TimezoneData {
		static $timezones = null;
		if ( null === $timezones ) {
			$timezones = Plugin::getInstance()->getContainer()->get( TimezoneData::class );
		}
		return $timezones;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\feature' ) ) {
	function feature( string $feature ): bool {
		static $features = null;
		if ( null === $features ) {
			$features = Plugin::getInstance()->getContainer()->get( FeatureManager::class );
		}
		return $features->isEnabled( $feature );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\collect' ) ) {
	/**
	 * @template T
	 * @param array<T> $items
	 * @return Collection<T>
	 */
	function collect( array $items = [] ): Collection {
		return Collection::make( $items );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\template' ) ) {
	function template( string $templateName, array $args = [] ): void {
		static $loader = null;
		if ( null === $loader ) {
			$container = Plugin::getInstance()->getContainer();
			$loader    = $container->get( TemplateLoader::class );
		}
		$loader->loadTemplate( $templateName, $args );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\retry' ) ) {
	function retry( callable $callback, int $times = 3, int $sleep = 100 ) {
		$attempts = 0;
		while ( true ) {
			++$attempts;
			try {
				return $callback( $attempts );
			} catch ( \Exception $e ) {
				if ( $attempts >= $times ) {
					throw $e;
				}
				usleep( $sleep * $attempts * 1000 );
			}
		}
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\value' ) ) {
	function value( mixed $value, mixed ...$args ): mixed {
		return $value instanceof \Closure ? $value( ...$args ) : $value;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\tap' ) ) {
	function tap( mixed $value, callable $callback ): mixed {
		$callback( $value );
		return $value;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\getAttachmentImages' ) ) {
	function getAttachmentImages( ?int $id ): ?array {
		if ( $id === null ) {
			return null;
		}

		$metadata = wp_get_attachment_metadata( $id );

		if ( ! $metadata || empty( $metadata['sizes'] ) ) {
			return null;
		}

		$sizes = [];

		foreach ( $metadata['sizes'] as $sizeName => $sizeData ) {
			$url = wp_get_attachment_image_url( $id, $sizeName );
			if ( ! $url ) {
				continue;
			}
			$sizes[ $sizeName ] = [
				'url'    => $url,
				'width'  => $sizeData['width'],
				'height' => $sizeData['height'],
			];
		}

		$fullUrl = wp_get_attachment_image_url( $id, 'full' );
		if ( $fullUrl ) {
			$fullMeta      = wp_get_attachment_metadata( $id );
			$sizes['full'] = [
				'url'    => $fullUrl,
				'width'  => $fullMeta['width'] ?? null,
				'height' => $fullMeta['height'] ?? null,
			];
		}

		if ( empty( $sizes ) ) {
			return null;
		}

		return [
			'id'    => $id,
			'sizes' => $sizes,
		];
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\upgradeUrl' ) ) {
	/**
	 * Build the pricing-page URL for an addon upsell, UTM-tagged so the
	 * marketing site can attribute the upgrade click to the specific addon card
	 * and to the plugin that surfaced it (Free promo vs. unlicensed Pro).
	 *
	 * @param string $addonId Module id, used as the utm_campaign.
	 * @param string $source  utm_source (e.g. 'allcoach-free' | 'allcoach-pro').
	 */
	function upgradeUrl( string $addonId, string $source ): string {
		return (string) add_query_arg(
			[
				'utm_source'   => $source,
				'utm_medium'   => 'addons',
				'utm_campaign' => $addonId,
			],
			'https://allcoach.io/pricing'
		);
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\maskSecret' ) ) {
	function maskSecret( string $value ): string {
		$len = strlen( $value );
		if ( $len <= 12 ) {
			return str_repeat( '*', $len );
		}
		return substr( $value, 0, 8 ) . str_repeat( '*', $len - 12 ) . substr( $value, -4 );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\getAllowedSvgTags' ) ) {
	function getAllowedSvgTags() {
		return [
			'svg'      => [
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'class'           => true,
				'aria-hidden'     => true,
				'role'            => true,
			],
			'g'        => [
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			],
			'path'     => [
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			],
			'rect'     => [
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
			],
			'circle'   => [
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'fill'   => true,
				'stroke' => true,
			],
			'ellipse'  => [
				'cx'     => true,
				'cy'     => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
			],
			'line'     => [
				'x1'     => true,
				'y1'     => true,
				'x2'     => true,
				'y2'     => true,
				'stroke' => true,
			],
			'polyline' => [
				'points' => true,
				'fill'   => true,
				'stroke' => true,
			],
			'polygon'  => [
				'points' => true,
				'fill'   => true,
				'stroke' => true,
			],
			'title'    => [],
		];
	}
}
