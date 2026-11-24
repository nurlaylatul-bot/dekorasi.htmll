<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Availability\Availability;
use AllCoach\Domain\Availability\AvailabilityRepository as AvailabilityRepositoryInterface;
use AllCoach\Domain\Availability\WeeklySchedule;
use Carbon\CarbonImmutable;

class AvailabilityRepository implements AvailabilityRepositoryInterface {

	private const POST_TYPE = 'allcoach_avail';

	private const META_TIMEZONE            = '_ac_av_timezone';
	private const META_START_DATE          = '_ac_av_start_date';
	private const META_SCHEDULE            = '_ac_av_schedule';
	private const META_IS_PROGRAM_SPECIFIC = '_ac_av_is_program_specific';
	private const META_PROGRAM_IDS         = '_ac_av_program_ids';

	public function findById( int $id ): ?Availability {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	public function findByUser( int $userId, int $limit = 100, int $offset = 0 ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'author'         => $userId,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);
		return $this->hydrateCollection( $query->posts );
	}

	public function countByUser( int $userId ): int {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'author'         => $userId,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);
		return (int) $query->found_posts;
	}

	public function save( Availability $availability ): Availability {
		return $availability->isNew() ? $this->insert( $availability ) : $this->update( $availability );
	}

	public function delete( int $id ): bool {
		$result = wp_delete_post( $id, true );
		return $result !== false && $result !== null;
	}

	private function insert( Availability $availability ): Availability {
		$title = sprintf(
			/* translators: %s: timezone */
			__( 'Availability (%s)', 'allcoach' ),
			$availability->getTimezone()
		);

		$postId = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => $availability->getUserId(),
			],
			true
		);

		if ( is_wp_error( $postId ) ) {
			throw new \RuntimeException( 'Failed to save availability: ' . esc_html( $postId->get_error_message() ) );
		}

		$post         = get_post( $postId );
		$availability = Availability::reconstitute(
			id:                $postId,
			userId:            $availability->getUserId(),
			timezone:          $availability->getTimezone(),
			startDate:         $availability->getStartDate(),
			schedule:          $availability->getSchedule(),
			isProgramSpecific: $availability->isProgramSpecific(),
			programIds:        $availability->getProgramIds(),
			createdAt:         CarbonImmutable::parse( $post->post_date ),
		);
		$this->saveMeta( $availability );

		return $availability;
	}

	private function update( Availability $availability ): Availability {
		$title = sprintf(
			/* translators: %s: timezone */
			__( 'Availability (%s)', 'allcoach' ),
			$availability->getTimezone()
		);

		wp_update_post(
			[
				'ID'          => $availability->getId(),
				'post_title'  => $title,
				'post_author' => $availability->getUserId(),
			]
		);

		$this->saveMeta( $availability );
		return $availability;
	}

	private function saveMeta( Availability $availability ): void {
		$id = $availability->getId();
		update_post_meta( $id, self::META_TIMEZONE, $availability->getTimezone() );
		update_post_meta( $id, self::META_START_DATE, $availability->getStartDate() );
		update_post_meta( $id, self::META_SCHEDULE, wp_json_encode( $availability->getSchedule()->toArray() ) );
		update_post_meta( $id, self::META_IS_PROGRAM_SPECIFIC, $availability->isProgramSpecific() ? '1' : '0' );
		update_post_meta( $id, self::META_PROGRAM_IDS, wp_json_encode( $availability->getProgramIds() ) );
	}

	private function hydrate( \WP_Post $post ): Availability {
		$timezone     = (string) get_post_meta( $post->ID, self::META_TIMEZONE, true );
		$startDate    = (string) get_post_meta( $post->ID, self::META_START_DATE, true );
		$scheduleJson = get_post_meta( $post->ID, self::META_SCHEDULE, true );
		$schedule     = is_string( $scheduleJson ) ? (array) json_decode( $scheduleJson, true ) : [];
		$schedule     = is_array( $schedule ) ? $schedule : [];

		$programIdsJson = get_post_meta( $post->ID, self::META_PROGRAM_IDS, true );
		$programIds     = is_string( $programIdsJson ) ? (array) json_decode( $programIdsJson, true ) : [];
		$programIds     = array_values( array_map( 'intval', array_filter( $programIds ?? [] ) ) );

		if ( $timezone === '' ) {
			$timezone = wp_timezone_string();
		}
		if ( $startDate === '' ) {
			$startDate = current_time( 'Y-m-d' );
		}

		return Availability::reconstitute(
			id:                $post->ID,
			userId:            (int) $post->post_author,
			timezone:          $timezone,
			startDate:         $startDate,
			schedule:          WeeklySchedule::fromArray( $schedule ),
			isProgramSpecific: (bool) ( (int) get_post_meta( $post->ID, self::META_IS_PROGRAM_SPECIFIC, true ) === 1 ),
			programIds:        $programIds,
			createdAt:         CarbonImmutable::parse( $post->post_date ),
		);
	}

	/**
	 * @param \WP_Post[] $posts
	 * @return Availability[]
	 */
	private function hydrateCollection( array $posts ): array {
		return array_map( fn( \WP_Post $p ) => $this->hydrate( $p ), $posts );
	}
}
