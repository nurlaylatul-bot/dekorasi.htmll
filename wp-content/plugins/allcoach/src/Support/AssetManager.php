<?php
declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;

class AssetManager {

	private const HANDLE_PREFIX = 'allcoach-';
	private const DEV_SERVER    = 'http://localhost:8887/';
	private string $buildPath;
	private string $buildUrl;
	private Logger $logger;
	private bool $hooksRegistered = false;

	private array $assetCache = [];
	private bool $isDevServer = false;


	public function __construct( Logger $logger ) {
		$this->isDevServer = defined( 'ALLCOACH_ENV' ) && 'development' === ALLCOACH_ENV;
		$this->buildPath   = Constants::path( 'resources/build/' );
		$this->buildUrl    = $this->isDevServer ? self::DEV_SERVER : Constants::url( 'resources/build/' );
		$this->logger      = $logger;

		$this->registerHooks();
	}

	private function registerHooks(): void {
		if ( $this->hooksRegistered ) {
			return;
		}
		$this->hooksRegistered = true;
	}

	public function enqueueEntry(
		string $handle,
		string $src,
		array $deps = [],
		bool $inFooter = true,
		array $localize = []
	): void {
		$this->enqueueScript( $handle, $src, $deps, $inFooter, $localize );
		$this->enqueueCompanionCss( $handle, $src );
	}

	public function registerScript(
		string $handle,
		string $src,
		array $deps = [],
		bool $inFooter = true
	): void {
		$fullHandle = self::HANDLE_PREFIX . $handle;
		$asset      = $this->loadAssetFile( $src );

		wp_register_script(
			$fullHandle,
			$this->scriptUrl( $src ),
			array_unique( array_merge( $asset['dependencies'], $deps ) ),
			$asset['version'],
			$inFooter
		);
	}

	public function enqueueScript(
		string $handle,
		string $src,
		array $deps = [],
		bool $inFooter = true,
		array $localize = []
	): void {
		$fullHandle = self::HANDLE_PREFIX . $handle;

		if ( wp_script_is( $fullHandle, 'registered' ) ) {
			wp_enqueue_script( $fullHandle );
		} else {
			$asset = $this->loadAssetFile( $src );

			wp_enqueue_script(
				$fullHandle,
				$this->scriptUrl( $src ),
				array_unique( array_merge( $asset['dependencies'], $deps ) ),
				$asset['version'],
				$inFooter
			);
		}

		if ( ! empty( $localize ) ) {
			$this->localizeScript( $handle, $localize );
		}
	}

	public function localizeScript( string $handle, array $localize ): void {
		$fullHandle = self::HANDLE_PREFIX . $handle;
		$objectName = $localize['object_name'] ?? '__ALLCOACH__';
		$data       = $localize['data'] ?? [];

		if ( ! preg_match( '/^[a-zA-Z_$][a-zA-Z0-9_$]*$/', $objectName ) ) {
			$objectName = '__ALLCOACH__';
		}

		wp_localize_script( $fullHandle, $objectName, $data );
	}

	public function registerStyle(
		string $handle,
		string $src,
		array $deps = [],
		string $media = 'all'
	): void {
		$fullHandle = self::HANDLE_PREFIX . $handle;

		wp_register_style(
			$fullHandle,
			$this->styleUrl( $src ),
			$deps,
			$this->versionForCss( $src ),
			$media
		);
	}

	public function enqueueStyle(
		string $handle,
		string $src,
		array $deps = [],
		string $media = 'all'
	): void {
		$fullHandle = self::HANDLE_PREFIX . $handle;

		if ( wp_style_is( $fullHandle, 'registered' ) ) {
			wp_enqueue_style( $fullHandle );
			return;
		}

		wp_enqueue_style(
			$fullHandle,
			$this->styleUrl( $src ),
			$deps,
			$this->versionForCss( $src ),
			$media
		);
	}

	/**
	 * @param string $src
	 * @return array{ dependencies: string[], version: string }
	 */
	public function loadAssetFile( string $src ): array {
		$stem     = $this->stem( $src );
		$cacheKey = $stem;

		if ( isset( $this->assetCache[ $cacheKey ] ) ) {
			return $this->assetCache[ $cacheKey ];
		}

		$assetFile = $this->buildPath . $stem . '.asset.php';

		if ( ! file_exists( $assetFile ) ) {
			$this->logger->warning(
				"AssetManager: '{$stem}.asset.php' not found. Run pnpm build."
			);
			$this->assetCache[ $cacheKey ] = $this->fallbackAsset();
			return $this->assetCache[ $cacheKey ];
		}

		$asset = require $assetFile;

		if ( ! is_array( $asset ) || ! array_key_exists( 'dependencies', $asset ) || ! array_key_exists( 'version', $asset ) ) {
			$this->logger->warning(
				"AssetManager: '{$stem}.asset.php' has an unexpected format."
			);
			$this->assetCache[ $cacheKey ] = $this->fallbackAsset();
			return $this->assetCache[ $cacheKey ];
		}

		$this->assetCache[ $cacheKey ] = [
			'dependencies' => (array) $asset['dependencies'],
			'version'      => (string) $asset['version'],
		];

		return $this->assetCache[ $cacheKey ];
	}

	public function enqueueCompanionCss(
		string $handle,
		string $jsSrc,
		bool $checkStylePrefix = true
	): void {
		$stem = $this->stem( $jsSrc );

		$candidate = $stem . '.css';
		if ( file_exists( $this->buildPath . $candidate ) ) {
			$this->enqueueStyle( $handle, $candidate );
			return;
		}

		if ( $checkStylePrefix ) {
			$styleCandidate = 'style-' . $stem . '.css';
			if ( file_exists( $this->buildPath . $styleCandidate ) ) {
				$this->enqueueStyle( $handle . '-style', $styleCandidate );
			}
		}
	}

	public function scriptUrl( string $src ): string {
		return rtrim( $this->buildUrl, '/' ) . '/' . ltrim( $src, '/' );
	}

	public function styleUrl( string $src ): string {
		return rtrim( $this->buildUrl, '/' ) . '/' . ltrim( $src, '/' );
	}

	private function versionForCss( string $cssSrc ): string {
		$stem      = $this->stem( $cssSrc );
		$assetFile = $this->buildPath . $stem . '.asset.php';

		if ( file_exists( $assetFile ) ) {
			$asset = $this->loadAssetFile( $stem . '.js' );
			if ( $asset['version'] !== Constants::VERSION ) {
				return $asset['version'];
			}
		}

		$cssFile = $this->buildPath . ltrim( $cssSrc, '/' );
		if ( file_exists( $cssFile ) ) {
			return (string) filemtime( $cssFile );
		}

		return Constants::VERSION;
	}

	private function stem( string $src ): string {
		return pathinfo( ltrim( $src, '/' ), PATHINFO_FILENAME );
	}

	private function fallbackAsset(): array {
		return [
			'dependencies' => [],
			'version'      => Constants::VERSION,
		];
	}

	public function getBuildPath(): string {
		return $this->buildPath;
	}

	public function getBuildUrl(): string {
		return $this->buildUrl;
	}
}
