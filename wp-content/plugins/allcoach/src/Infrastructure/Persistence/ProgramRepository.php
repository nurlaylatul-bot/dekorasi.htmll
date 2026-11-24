<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Booking\MeetingType;
use AllCoach\Domain\Program\Program;
use AllCoach\Domain\Program\ProgramDuration;
use AllCoach\Domain\Program\ProgramFilter;
use AllCoach\Domain\Program\ProgramRepository as ProgramRepositoryInterface;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.SlowDBQuery

class ProgramRepository implements ProgramRepositoryInterface {

	private const POST_TYPE            = 'allcoach_program';
	private const PROGRAM_CATEGORY_TAX = 'allcoach_program_cat';

	public function findById( int $id ): ?Program {
		$post = get_post( $id );

		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}

		return $this->hydrateFromPost( $post );
	}

	public function findAll( int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery(
			[
				'post_status' => 'any',
				'orderby'     => 'date',
				'order'       => 'DESC',
			],
			$limit,
			$offset
		);
	}

	public function findActive( int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery(
			[
				'post_status' => 'publish',
				'meta_query'  => [
					[
						'key'     => '_program_is_active',
						'value'   => '1',
						'compare' => '=',
					],
				],
				'orderby'     => 'date',
				'order'       => 'DESC',
			],
			$limit,
			$offset
		);
	}

	public function findByCoachId( int $coachId, int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery(
			[
				'post_status' => 'any',
				'meta_key'    => '_program_coach_id',
				'meta_value'  => (string) $coachId,
				'orderby'     => 'date',
				'order'       => 'DESC',
			],
			$limit,
			$offset
		);
	}

	public function findByAuthorId( int $wpUserId, int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery(
			[
				'post_status' => 'any',
				'author'      => $wpUserId,
				'orderby'     => 'date',
				'order'       => 'DESC',
			],
			$limit,
			$offset
		);
	}

	public function findWithFilter( ProgramFilter $filter, int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery(
			$this->buildQueryArgsFromFilter( $filter ),
			$limit,
			$offset
		);
	}

	public function search( string $query, int $limit = 100, int $offset = 0 ): array {
		return $this->runQuery(
			[
				'post_status' => 'any',
				's'           => $query,
			],
			$limit,
			$offset
		);
	}

	public function count(): int {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
			]
		);

		return (int) $query->found_posts;
	}

	public function countActive(): int {
		return $this->runCountQuery(
			[
				'post_status' => 'publish',
				'meta_query'  => [
					[
						'key'     => '_program_is_active',
						'value'   => '1',
						'compare' => '=',
					],
				],
			]
		);
	}

	public function countByAuthorId( int $wpUserId ): int {
		return $this->runCountQuery(
			[
				'post_status' => 'any',
				'author'      => $wpUserId,
			]
		);
	}

	public function countWithFilter( ProgramFilter $filter ): int {
		return $this->runCountQuery(
			$this->buildQueryArgsFromFilter( $filter )
		);
	}

	public function save( Program $program ): Program {
		return $program->isNew() ? $this->insert( $program ) : $this->update( $program );
	}

	public function delete( int $id ): bool {
		$result = wp_delete_post( $id, true );

		return $result !== false && $result !== null;
	}

	private function insert( Program $program ): Program {
		if ( $program->getCoachId() === null ) {
			throw new \InvalidArgumentException(
				'Cannot insert a Program without a coachId. Set coachId before saving.'
			);
		}

		$postId = wp_insert_post(
			[
				'post_type'    => self::POST_TYPE,
				'post_title'   => $program->getTitle(),
				'post_content' => $program->getDescription(),
				'post_status'  => $program->isActive() ? 'publish' : 'draft',
				'post_author'  => $program->getCoachId(),
			],
			true
		);

		if ( is_wp_error( $postId ) ) {
			throw new \RuntimeException(
				'Failed to insert program: ' . esc_html( $postId->get_error_message() )
			);
		}

		$post    = get_post( $postId );
		$program = Program::reconstitute(
			id:             $postId,
			title:          $program->getTitle(),
			description:    $program->getDescription(),
			duration:       $program->getDuration(),
			price:          $program->getPrice(),
			maxEnrollments: $program->getMaxEnrollments(),
			isActive:       $program->isActive(),
			coachId:        $program->getCoachId(),
			thumbnailId:    $program->getThumbnailId(),
			createdAt:      CarbonImmutable::parse( $post->post_date ),
			updatedAt:      null,
			meetingType:       $program->getMeetingType(),
			customMeetingUrl: $program->getCustomMeetingUrl(),
			meetingLocation:  $program->getMeetingLocation(),
		);
		$this->saveMeta( $program );

		return $program;
	}

	private function update( Program $program ): Program {
		$result = wp_update_post(
			[
				'ID'           => $program->getId(),
				'post_title'   => $program->getTitle(),
				'post_content' => $program->getDescription(),
				'post_status'  => $program->isActive() ? 'publish' : 'draft',
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException(
				'Failed to update program: ' . esc_html( $result->get_error_message() )
			);
		}

		$this->saveMeta( $program );

		return $program;
	}

	private function saveMeta( Program $program ): void {
		$id = $program->getId();

		update_post_meta( $id, '_program_duration_value', $program->getDuration()->getValue() );
		update_post_meta( $id, '_program_duration_unit', $program->getDuration()->getUnit() );
		update_post_meta( $id, '_program_price_amount', $program->getPrice() );
		update_post_meta( $id, '_program_max_enrollments', $program->getMaxEnrollments() );
		update_post_meta( $id, '_program_is_active', $program->isActive() ? 1 : 0 );
		update_post_meta( $id, '_program_coach_id', $program->getCoachId() ?? '' );
		update_post_meta( $id, '_program_created_at', $program->getCreatedAt()->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $id, '_program_meeting_type', $program->getMeetingType()->getValue() );
		update_post_meta( $id, '_program_custom_meeting_url', $program->getCustomMeetingUrl() ?? '' );
		update_post_meta( $id, '_program_meeting_location', $program->getMeetingLocation() ?? '' );

		$thumbnailId = $program->getThumbnailId();
		$thumbnailId !== null
			? set_post_thumbnail( $id, $thumbnailId )
			: delete_post_thumbnail( $id );

		$updatedAt = $program->getUpdatedAt();
		if ( $updatedAt !== null ) {
			update_post_meta( $id, '_program_updated_at', $updatedAt->format( 'Y-m-d H:i:s' ) );
		}
	}

	/**
	 * @param  array<string, mixed> $args  WP_Query args (post_type, posts_per_page,
	 *                                     and offset are injected automatically).
	 * @return array<Program>
	 */
	private function runQuery( array $args, int $limit, int $offset ): array {
		$query = new \WP_Query(
			array_merge(
				[
					'post_type'      => self::POST_TYPE,
					'posts_per_page' => $limit,
					'offset'         => $offset,
				],
				$args
			)
		);

		return $this->hydrateCollection( $query->posts );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function runCountQuery( array $args ): int {
		$query = new \WP_Query(
			array_merge(
				[
					'post_type'      => self::POST_TYPE,
					'fields'         => 'ids',
					'posts_per_page' => 1,
				],
				$args
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildQueryArgsFromFilter( ProgramFilter $filter ): array {
		$args = [
			'post_status' => 'any',
			'orderby'     => $filter->orderBy,
			'order'       => $filter->order,
		];

		if ( $filter->authorId !== null ) {
			$args['author'] = $filter->authorId;
		}

		if ( $filter->coachId !== null ) {
			$args['meta_key']   = '_program_coach_id';
			$args['meta_value'] = (string) $filter->coachId;
		}

		if ( null !== $filter->isActive ) {
			$args['post_status'] = $filter->isActive ? 'publish' : 'draft';
		}

		if ( $filter->search !== null && $filter->search !== '' ) {
			$args['s'] = $filter->search;
		}

		if ( $filter->categoryId !== null ) {
			$args['tax_query'] = [
				[
					'taxonomy' => self::PROGRAM_CATEGORY_TAX,
					'field'    => 'term_id',
					'terms'    => $filter->categoryId,
				],
			];
		}

		return $args;
	}

	private function hydrateFromPost( \WP_Post $post ): Program {
		$durationValue  = (int) get_post_meta( $post->ID, '_program_duration_value', true );
		$durationUnit   = get_post_meta( $post->ID, '_program_duration_unit', true ) ?: 'weeks';
		$priceAmount    = (float) get_post_meta( $post->ID, '_program_price_amount', true );
		$maxEnrollments = (int) get_post_meta( $post->ID, '_program_max_enrollments', true );
		$isActive       = (bool) get_post_meta( $post->ID, '_program_is_active', true );
		$coachId        = get_post_meta( $post->ID, '_program_coach_id', true );
		$createdAtStr   = get_post_meta( $post->ID, '_program_created_at', true );
		$updatedAtStr   = get_post_meta( $post->ID, '_program_updated_at', true );
		$thumbnailId    = has_post_thumbnail( $post ) ? (int) get_post_thumbnail_id( $post ) : null;
		$meetingTypeStr = get_post_meta( $post->ID, '_program_meeting_type', true ) ?: 'google_meet';
		$customUrl      = get_post_meta( $post->ID, '_program_custom_meeting_url', true );
		$location       = get_post_meta( $post->ID, '_program_meeting_location', true );

		return Program::reconstitute(
			id:             $post->ID,
			title:          $post->post_title,
			description:    $post->post_content,
			duration:       new ProgramDuration( $durationValue ?: 1, $durationUnit ),
			price:          $priceAmount,
			maxEnrollments: $maxEnrollments,
			isActive:       $isActive,
			coachId:        ( $coachId !== '' && $coachId !== null ) ? (int) $coachId : null,
			thumbnailId:    $thumbnailId,
			createdAt:      $createdAtStr ? CarbonImmutable::parse( $createdAtStr ) : CarbonImmutable::parse( $post->post_date ),
			updatedAt:      $updatedAtStr ? CarbonImmutable::parse( $updatedAtStr ) : null,
			meetingType:       MeetingType::fromString( $meetingTypeStr ),
			customMeetingUrl: $customUrl ?: null,
			meetingLocation:  $location ?: null,
		);
	}

	/**
	 * @param array<\WP_Post> $posts
	 * @return array<Program>
	 */
	private function hydrateCollection( array $posts ): array {
		if ( empty( $posts ) ) {
			return [];
		}

		update_meta_cache( 'post', array_column( $posts, 'ID' ) );

		return array_map(
			fn( \WP_Post $p ) => $this->hydrateFromPost( $p ),
			$posts
		);
	}
}
