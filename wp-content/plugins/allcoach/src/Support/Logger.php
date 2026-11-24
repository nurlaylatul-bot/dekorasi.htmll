<?php

declare(strict_types=1);

namespace AllCoach\Support;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Features\FeatureManager;
use AllCoach\Traits\Hooks;
use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger {

	use Hooks;

	private const LEVEL_PRIORITY = [
		'debug'     => 0,
		'info'      => 1,
		'notice'    => 2,
		'warning'   => 3,
		'error'     => 4,
		'critical'  => 5,
		'alert'     => 6,
		'emergency' => 7,
	];

	private string $logFile;
	private string $prefix       = '[AllCoach]';
	private string $minimumLevel = 'info';
	private int $maxFileSize     = 10485760; // 10MB
	private int $maxFiles        = 5;

	public function __construct(
		private readonly FeatureManager $features
	) {
		$this->setupLogFile();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->minimumLevel = 'debug';
		}
	}

	private function setupLogFile(): void {
		$uploadDir = wp_upload_dir();
		$logsDir   = $uploadDir['basedir'] . '/allcoach-logs';

		if ( ! file_exists( $logsDir ) ) {
			wp_mkdir_p( $logsDir );

			$htaccess = $logsDir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			$index = $logsDir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}

		$this->logFile = $logsDir . '/allcoach-' . gmdate( 'Y-m-d' ) . '.log';
	}

	public function log( $level, string|\Stringable $message, array $context = [] ): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$levelStr = strtolower( (string) $level );
		if ( ( self::LEVEL_PRIORITY[ $levelStr ] ?? 0 ) < ( self::LEVEL_PRIORITY[ $this->minimumLevel ] ?? 0 ) ) {
			return;
		}

		$level    = strtoupper( $levelStr );
		$message  = $this->interpolate( (string) $message, $context );
		$logEntry = $this->formatLogEntry( $level, $message, $context );

		$this->writeToFile( $logEntry );
		$this->rotateLogsIfNeeded();
		$this->doAction( 'allcoach:log', $level, $message, $context );
	}

	private function formatLogEntry( string $level, string $message, array $context ): string {
		$timestamp     = gmdate( 'Y-m-d H:i:s' );
		$contextString = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		return sprintf(
			"[%s] %s [%s] %s%s\n",
			$timestamp,
			$this->prefix,
			$level,
			$message,
			$contextString
		);
	}


	private function writeToFile( string $logEntry ): void {
		file_put_contents( $this->logFile, $logEntry, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	private function rotateLogsIfNeeded(): void {
		if ( ! file_exists( $this->logFile ) ) {
			return;
		}

		$fileSize = filesize( $this->logFile );
		if ( $fileSize < $this->maxFileSize ) {
			return;
		}

		for ( $i = $this->maxFiles - 1; $i > 0; $i-- ) {
			$oldFile = $this->logFile . '.' . $i;
			$newFile = $this->logFile . '.' . ( $i + 1 );

			if ( file_exists( $oldFile ) ) {
				if ( $i === $this->maxFiles - 1 ) {
					wp_delete_file( $oldFile );
				} else {
					rename( $oldFile, $newFile ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				}
			}
		}

		rename( $this->logFile, $this->logFile . '.1' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
	}

	private function interpolate( string $message, array $context ): string {
		$replace = [];
		foreach ( $context as $key => $val ) {
			if ( ! is_array( $val ) && ( ! is_object( $val ) || method_exists( $val, '__toString' ) ) ) {
				$replace[ '{' . $key . '}' ] = (string) $val;
			}
		}
		return strtr( $message, $replace );
	}

	public function isEnabled(): bool {
		return $this->features->isEnabled( 'logging' );
	}

	public function getLogFile(): string {
		return $this->logFile;
	}

	public function getLogsDirectory(): string {
		return dirname( $this->logFile );
	}

	public function setPrefix( string $prefix ): self {
		$this->prefix = $prefix;
		return $this;
	}

	public function setMinimumLevel( string $level ): self {
		$this->minimumLevel = strtolower( $level );
		return $this;
	}

	public function setMaxFileSize( int $bytes ): self {
		$this->maxFileSize = $bytes;
		return $this;
	}

	public function setMaxFiles( int $count ): self {
		$this->maxFiles = $count;
		return $this;
	}

	public function clearLogs(): void {
		$logsDir = $this->getLogsDirectory();

		if ( ! is_dir( $logsDir ) ) {
			return;
		}

		$files = glob( $logsDir . '/allcoach-*.log*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	public function getRecentLogs( int $lines = 100 ): array {
		if ( ! file_exists( $this->logFile ) ) {
			return [];
		}

		$file = new \SplFileObject( $this->logFile );
		$file->seek( PHP_INT_MAX );
		$totalLines = $file->key() + 1;

		$startLine = max( 0, $totalLines - $lines );
		$file->seek( $startLine );

		$logs = [];
		while ( ! $file->eof() ) {
			$line = $file->current();
			if ( ! empty( trim( $line ) ) ) {
				$logs[] = $line;
			}
			$file->next();
		}

		return $logs;
	}

	public function getStats(): array {
		$logsDir = $this->getLogsDirectory();
		$files   = glob( $logsDir . '/allcoach-*.log*' );

		$totalSize = 0;
		$fileCount = 0;

		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					$totalSize += filesize( $file );
					++$fileCount;
				}
			}
		}

		return [
			'enabled'              => $this->isEnabled(),
			'current_log_file'     => $this->logFile,
			'log_directory'        => $logsDir,
			'total_files'          => $fileCount,
			'total_size'           => $totalSize,
			'total_size_formatted' => size_format( $totalSize ),
			'current_file_exists'  => file_exists( $this->logFile ),
			'current_file_size'    => file_exists( $this->logFile ) ? filesize( $this->logFile ) : 0,
			'max_file_size'        => $this->maxFileSize,
			'max_files'            => $this->maxFiles,
		];
	}
}
