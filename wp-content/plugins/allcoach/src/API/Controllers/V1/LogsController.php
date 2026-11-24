<?php

declare(strict_types=1);

namespace AllCoach\API\Controllers\V1;

defined( 'ABSPATH' ) || exit;

use AllCoach\API\RestController;
use AllCoach\Support\Logger;

class LogsController extends RestController {

	protected string $restBase = 'logs';

	public function __construct(
		private readonly Logger $logger,
	) {}

	public function registerRoutes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->restBase,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getLogs' ],
					'permission_callback' => [ $this, 'permManage' ],
					'args'                => [
						'lines' => $this->argInteger(
							description: __( 'Number of recent log lines to return.', 'allcoach' ),
							min:         1,
							max:         1000,
							default:     200,
						),
					],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'clearLogs' ],
					'permission_callback' => [ $this, 'permManage' ],
				],
			]
		);
	}

	public function permManage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function getLogs( \WP_REST_Request $request ): \WP_REST_Response {
		$lines      = (int) $request->get_param( 'lines' );
		$rawLines   = $this->logger->getRecentLogs( $lines );
		$parsed     = array_values( array_filter( array_map( [ $this, 'parseLine' ], $rawLines ) ) );
		$stats      = $this->logger->getStats();

		return $this->successResponse( [
			'logs'  => $parsed,
			'stats' => [
				'enabled'              => $stats['enabled'],
				'total_files'          => $stats['total_files'],
				'total_size_formatted' => $stats['total_size_formatted'],
				'current_file_exists'  => $stats['current_file_exists'],
			],
		] );
	}

	public function clearLogs( \WP_REST_Request $request ): \WP_REST_Response {
		$this->logger->clearLogs();
		return $this->successResponse( [ 'cleared' => true ] );
	}

	private function parseLine( string $line ): ?array {
		$line = trim( $line );
		if ( empty( $line ) ) {
			return null;
		}

		// Format: [YYYY-MM-DD HH:MM:SS] [AllCoach] [LEVEL] message {context_json}
		$pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[AllCoach\] \[(\w+)\] (.*?)(\s+(\{.*\}|\[.*\]))?$/s';
		if ( ! preg_match( $pattern, $line, $m ) ) {
			return [
				'timestamp' => null,
				'level'     => 'UNKNOWN',
				'message'   => $line,
				'context'   => null,
			];
		}

		$context = null;
		if ( ! empty( $m[4] ) ) {
			$decoded = json_decode( trim( $m[4] ), true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$context = $decoded;
			}
		}

		return [
			'timestamp' => $m[1],
			'level'     => strtoupper( $m[2] ),
			'message'   => trim( $m[3] ),
			'context'   => $context,
		];
	}
}
