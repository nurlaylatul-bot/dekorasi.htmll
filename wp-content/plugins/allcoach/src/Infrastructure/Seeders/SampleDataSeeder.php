<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Seeders;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Constants;

class SampleDataSeeder {

	private const SEEDED_OPTION = '_allcoach_sample_seeded';
	private const DATA_FILE     = 'resources/data/sample-program.json';

	public function maybeRun(): void {
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}
		$this->run();
		update_option( self::SEEDED_OPTION, '1', false );
	}

	private function run(): void {
		$data = $this->loadData();
		if ( ! $data ) {
			return;
		}

		$coachId   = $this->resolveCoachId();
		$programId = $this->createProgram( $coachId, $data['program'] );

		if ( ! $programId ) {
			return;
		}

		$sectionIds = [];
		foreach ( $data['sections'] as $section ) {
			$id = $this->createSection( $programId, $section['title'], (int) $section['position'] );
			if ( $id ) {
				$sectionIds[ $section['key'] ] = $id;
			}
		}

		foreach ( $data['activities'] as $activity ) {
			$sectionId = $sectionIds[ $activity['section'] ] ?? 0;
			if ( ! $sectionId ) {
				continue;
			}
			$this->createActivity(
				$programId,
				$sectionId,
				(int) $activity['week'],
				(int) $activity['day'],
				(int) $activity['position'],
				$this->hydrateIds( $activity['schema'] )
			);
		}

		foreach ( $data['sessions'] as $i => $session ) {
			$this->createSession(
				$programId,
				(int) $session['duration_minutes'],
				(int) $session['repetition_count'],
				(int) ( $session['position'] ?? $i )
			);
		}
	}

	private function loadData(): ?array {
		$file = Constants::path( self::DATA_FILE );
		if ( ! file_exists( $file ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $file );
		if ( ! $json ) {
			return null;
		}
		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Walk the TipTap schema and stamp any node/option missing an `id`.
	 */
	private function hydrateIds( array $schema ): array {
		if ( isset( $schema['content'] ) && is_array( $schema['content'] ) ) {
			$schema['content'] = $this->hydrateNode( $schema['content'] );
		}
		return $schema;
	}

	private function hydrateNode( array $node ): array {
		if ( isset( $node['attrs'] ) ) {
			if ( empty( $node['attrs']['id'] ) ) {
				$node['attrs']['id'] = uniqid();
			}
			if ( isset( $node['attrs']['options'] ) && is_array( $node['attrs']['options'] ) ) {
				$node['attrs']['options'] = array_map(
					static function ( array $opt ): array {
						if ( empty( $opt['id'] ) ) {
							$opt['id'] = uniqid();
						}
						return $opt;
					},
					$node['attrs']['options']
				);
			}
		}
		if ( isset( $node['content'] ) && is_array( $node['content'] ) ) {
			$node['content'] = array_map( [ $this, 'hydrateNode' ], $node['content'] );
		}
		return $node;
	}

	private function resolveCoachId(): int {
		$userId = get_current_user_id();
		if ( $userId > 0 ) {
			return $userId;
		}
		$admins = get_users(
			[
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			]
		);
		return ! empty( $admins ) ? (int) $admins[0] : 1;
	}

	private function createProgram( int $coachId, array $data ): int {
		$postId = wp_insert_post(
			[
				'post_type'    => 'allcoach_program',
				'post_title'   => $data['title'],
				'post_content' => $data['content'] ?? '',
				'post_status'  => $data['status'] ?? 'draft',
				'post_author'  => $coachId,
			]
		);

		if ( is_wp_error( $postId ) || ! $postId ) {
			return 0;
		}

		foreach ( $data['meta'] ?? [] as $key => $value ) {
			update_post_meta( $postId, $key, $value );
		}
		update_post_meta( $postId, '_program_coach_id', $coachId );
		update_post_meta( $postId, '_program_created_at', gmdate( 'Y-m-d H:i:s' ) );

		return $postId;
	}

	private function createSection( int $programId, string $title, int $position ): int {
		$postId = wp_insert_post(
			[
				'post_type'   => 'allcoach_section',
				'post_title'  => $title,
				'post_status' => 'publish',
			]
		);

		if ( is_wp_error( $postId ) || ! $postId ) {
			return 0;
		}

		update_post_meta( $postId, '_ps_program_id', $programId );
		update_post_meta( $postId, '_ps_position', $position );

		return $postId;
	}

	private function createActivity( int $programId, int $sectionId, int $week, int $day, int $position, array $schema ): int {
		$postId = wp_insert_post(
			[
				'post_type'   => 'allcoach_activity',
				/* translators: 1: week number 2: day number */
				'post_title'  => sprintf( __( 'Week %1$d - Day %2$d', 'allcoach' ), $week, $day ),
				'post_status' => 'publish',
			]
		);

		if ( is_wp_error( $postId ) || ! $postId ) {
			return 0;
		}

		update_post_meta( $postId, '_pa_program_id', $programId );
		update_post_meta( $postId, '_pa_schema', wp_json_encode( $schema ) );
		update_post_meta(
			$postId,
			'_pa_settings',
			wp_json_encode(
				[
					/* translators: 1: week number 2: day number */
					'title' => sprintf( __( 'Week %1$d - Day %2$d', 'allcoach' ), $week, $day ),
				]
			)
		);
		update_post_meta( $postId, '_pa_day', $day );
		update_post_meta( $postId, '_pa_week', $week );
		update_post_meta( $postId, '_pa_position', $position );
		update_post_meta( $postId, '_pa_section_id', $sectionId );
		update_post_meta( $postId, '_pa_template_id', '' );

		return $postId;
	}

	private function createSession( int $programId, int $durationMinutes, int $repetitionCount, int $position ): int {
		$postId = wp_insert_post(
			[
				'post_type'   => 'allcoach_session',
				'post_title'  => '',
				'post_status' => 'publish',
			]
		);

		if ( is_wp_error( $postId ) || ! $postId ) {
			return 0;
		}

		update_post_meta( $postId, '_session_program_id', $programId );
		update_post_meta( $postId, '_session_duration_minutes', $durationMinutes );
		update_post_meta( $postId, '_session_repetition_count', $repetitionCount );
		update_post_meta( $postId, '_session_position', $position );

		return $postId;
	}
}
