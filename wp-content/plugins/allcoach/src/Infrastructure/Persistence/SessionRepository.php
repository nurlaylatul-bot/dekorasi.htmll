<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Session\Session;
use AllCoach\Domain\Session\SessionRepository as SessionRepositoryInterface;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.SlowDBQuery

class SessionRepository implements SessionRepositoryInterface {

	private const POST_TYPE = 'allcoach_session';

	private const META_PROGRAM_ID = '_session_program_id';
	private const META_DURATION   = '_session_duration_minutes';
	private const META_REPETITION = '_session_repetition_count';
	private const META_POSITION   = '_session_position';

	public function findById( int $id ): ?Session {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	/** For now we support only one session per program; returns it or null. */
	public function findOneByProgramId( int $programId ): ?Session {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_key'       => self::META_POSITION,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [
					[
						'key'   => self::META_PROGRAM_ID,
						'value' => $programId,
						'type'  => 'NUMERIC',
					],
				],
			]
		);
		$posts = $query->posts;
		return isset( $posts[0] ) && $posts[0] instanceof \WP_Post ? $this->hydrate( $posts[0] ) : null;
	}

	/** @return Session[] */
	public function findByProgram( int $programId ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => self::META_POSITION,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_query'     => [
					[
						'key'   => self::META_PROGRAM_ID,
						'value' => $programId,
						'type'  => 'NUMERIC',
					],
				],
			]
		);
		return $this->hydrateCollection( $query->posts );
	}

	public function countByProgram( int $programId ): int {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'   => self::META_PROGRAM_ID,
						'value' => $programId,
						'type'  => 'NUMERIC',
					],
				],
			]
		);
		return (int) $query->found_posts;
	}

	public function totalDurationByProgram( int $programId ): int {
		$sessions = $this->findByProgram( $programId );
		$total    = 0;
		foreach ( $sessions as $session ) {
			$total += $session->getDurationMinutes() * $session->getRepetitionCount();
		}
		return $total;
	}

	public function save( Session $session ): Session {
		return $session->isNew() ? $this->insert( $session ) : $this->update( $session );
	}

	private function insert( Session $session ): Session {
		$postId = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_title'  => '',
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $postId ) ) {
			throw new \RuntimeException( 'Failed to save session: ' . esc_html( $postId->get_error_message() ) );
		}

		$post    = get_post( $postId );
		$session = Session::reconstitute(
			id:              $postId,
			programId:       $session->getProgramId(),
			durationMinutes: $session->getDurationMinutes(),
			repetitionCount: $session->getRepetitionCount(),
			position:        $session->getPosition(),
			createdAt:       CarbonImmutable::parse( $post->post_date ),
		);
		$this->saveMeta( $session );
		return $session;
	}

	private function update( Session $session ): Session {
		$this->saveMeta( $session );
		return $session;
	}

	private function saveMeta( Session $session ): void {
		$id = $session->getId();
		update_post_meta( $id, self::META_PROGRAM_ID, $session->getProgramId() );
		update_post_meta( $id, self::META_DURATION, $session->getDurationMinutes() );
		update_post_meta( $id, self::META_REPETITION, $session->getRepetitionCount() );
		update_post_meta( $id, self::META_POSITION, $session->getPosition() );
	}

	public function delete( int $id ): bool {
		$result = wp_delete_post( $id, true );
		return $result !== false && $result !== null;
	}

	public function deleteByProgramId( int $programId ): int {
		$sessions = $this->findByProgram( $programId );
		$deleted  = 0;
		foreach ( $sessions as $session ) {
			if ( $session->getId() && $this->delete( (int) $session->getId() ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	private function hydrate( \WP_Post $post ): Session {
		$programId  = (int) get_post_meta( $post->ID, self::META_PROGRAM_ID, true );
		$duration   = (int) get_post_meta( $post->ID, self::META_DURATION, true );
		$repetition = (int) get_post_meta( $post->ID, self::META_REPETITION, true );
		$position   = (int) get_post_meta( $post->ID, self::META_POSITION, true );

		return Session::reconstitute(
			id:              $post->ID,
			programId:       $programId,
			durationMinutes: $duration >= 0 ? $duration : 0,
			repetitionCount: $repetition >= 1 ? $repetition : 1,
			position:        $position,
			createdAt:       CarbonImmutable::parse( $post->post_date ),
		);
	}

	/** @param \WP_Post[] $posts */
	private function hydrateCollection( array $posts ): array {
		return array_map( fn( \WP_Post $p ) => $this->hydrate( $p ), $posts );
	}
}
