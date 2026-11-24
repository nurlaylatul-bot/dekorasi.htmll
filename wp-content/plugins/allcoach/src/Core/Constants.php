<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

final class Constants {

	public const VERSION         = '1.1.1';
	public const TEXT_DOMAIN     = 'allcoach';
	public const MIN_PHP_VERSION = '8.0';
	public const MIN_WP_VERSION  = '6.5';

	private static ?string $pluginFile     = null;
	private static ?string $pluginPath     = null;
	private static ?string $pluginUrl      = null;
	private static ?string $pluginBasename = null;
	private static string $environment     = 'production';

	public static function init( string $pluginFile ): void {
		self::$pluginFile     = $pluginFile;
		self::$pluginPath     = plugin_dir_path( $pluginFile );
		self::$pluginUrl      = plugin_dir_url( $pluginFile );
		self::$pluginBasename = plugin_basename( $pluginFile );
		self::$environment    = defined( 'ALLCOACH_ENV' ) ? constant( 'ALLCOACH_ENV' ) : 'production';
	}

	public static function version(): string {
		return self::VERSION;
	}

	public static function textDomain(): string {
		return self::TEXT_DOMAIN;
	}

	public static function pluginFile(): string {
		return self::$pluginFile ?? '';
	}

	public static function pluginPath(): string {
		return self::$pluginPath ?? '';
	}

	public static function pluginUrl(): string {
		return self::$pluginUrl ?? '';
	}

	public static function pluginBasename(): string {
		return self::$pluginBasename ?? '';
	}

	/**
	 * Matches ThemeGrillSDK\Product::get_key() - the sanitized product key
	 * used for SDK filter/option names (e.g. '{key}_logger_data', '{key}_logger_flag').
	 */
	public static function productKey(): string {
		return str_replace( '-', '_', strtolower( trim( dirname( self::pluginBasename() ) ) ) );
	}

	public static function environment(): string {
		return self::$environment;
	}

	public static function path( string $relativePath = '' ): string {
		return self::pluginPath() . ltrim( $relativePath, '/' );
	}

	public static function url( string $relativePath = '' ): string {
		return self::pluginUrl() . ltrim( $relativePath, '/' );
	}

	public static function meetsPHPRequirement(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );
	}

	public static function meetsWPRequirement(): bool {
		global $wp_version;
		return version_compare( $wp_version, self::MIN_WP_VERSION, '>=' );
	}
}
