<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\I18n\ActivitySchemaTranslator;
use AllCoach\Domain\Activity\Activity;
use AllCoach\Domain\Activity\ActivityRepository as ActivityRepositoryInterface;
use AllCoach\Domain\Activity\ActivitySchema;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.SlowDBQuery

class ActivityRepository implements ActivityRepositoryInterface {

	private const POST_TYPE = 'allcoach_activity';

	public function __construct(
		private readonly ActivitySchemaTranslator $translator,
	) {}

	public function findById( int $id ): ?Activity {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	public function findByProgram( int $programId ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_key'       => '_pa_program_id',
				'meta_value'     => (string) $programId,
				'orderby'        => [ 'meta_value_num' => 'ASC' ],
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'   => '_pa_program_id',
						'value' => $programId,
						'type'  => 'NUMERIC',
					],
				],
			]
		);

		$activities = $this->hydrateCollection( $query->posts );

		usort(
			$activities,
			function ( Activity $a, Activity $b ) {
				if ( $a->getWeek() !== $b->getWeek() ) {
					return $a->getWeek() <=> $b->getWeek();
				}
				if ( $a->getDay() !== $b->getDay() ) {
					return $a->getDay() <=> $b->getDay();
				}
				return $a->getPosition() <=> $b->getPosition();
			}
		);

		return $activities;
	}

	public function findByProgramAndWeek( int $programId, int $week ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => '_pa_program_id',
						'value'   => $programId,
						'type'    => 'NUMERIC',
						'compare' => '=',
					],
					[
						'key'     => '_pa_week',
						'value'   => $week,
						'type'    => 'NUMERIC',
						'compare' => '=',
					],
				],
			]
		);

		return $this->hydrateCollection( $query->posts );
	}

	public function findByTemplate( int $templateId ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'meta_key'       => '_pa_template_id',
				'meta_value'     => (string) $templateId,
			]
		);

		return $this->hydrateCollection( $query->posts );
	}

	public function save( Activity $activity ): Activity {
		return $activity->isNew() ? $this->insert( $activity ) : $this->update( $activity );
	}

	private function insert( Activity $activity ): Activity {
		$title = sprintf(
			/* translators: 1: week number, 2: day number */
			__( 'Week %1$d – Day %2$d', 'allcoach' ),
			$activity->getWeek(),
			$activity->getDay()
		);

		$postId = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $postId ) ) {
			throw new \RuntimeException( 'Failed to save activity: ' . esc_html( $postId->get_error_message() ) );
		}

		$post     = get_post( $postId );
		$activity = Activity::reconstitute(
			id:         $postId,
			programId:  $activity->getProgramId(),
			schema:     $activity->getSchema(),
			settings:   $activity->getSettings(),
			day:        $activity->getDay(),
			week:       $activity->getWeek(),
			position:   $activity->getPosition(),
			sectionId:  $activity->getSectionId(),
			templateId: $activity->getTemplateId(),
			createdAt:  CarbonImmutable::parse( $post->post_date ),
		);
		$this->saveMeta( $activity );

		return $activity;
	}

	private function update( Activity $activity ): Activity {
		$this->saveMeta( $activity );
		return $activity;
	}

	private function saveMeta( Activity $activity ): void {
		$id = $activity->getId();
		update_post_meta( $id, '_pa_program_id', $activity->getProgramId() );
		update_post_meta( $id, '_pa_template_id', $activity->getTemplateId() ?? '' );
		update_post_meta( $id, '_pa_schema', $activity->getSchema()->toJson() );
		update_post_meta( $id, '_pa_settings', wp_json_encode( $activity->getSettings() ) );
		update_post_meta( $id, '_pa_day', $activity->getDay() );
		update_post_meta( $id, '_pa_week', $activity->getWeek() );
		update_post_meta( $id, '_pa_position', $activity->getPosition() );
		update_post_meta( $id, '_pa_section_id', $activity->getSectionId() ?? '' );
	}

	public function delete( int $id ): bool {
		$result = wp_delete_post( $id, true );
		return $result !== false && $result !== null;
	}

	public function deleteByProgram( int $programId ): int {
		$activities = $this->findByProgram( $programId );
		$deleted    = 0;

		foreach ( $activities as $activity ) {
			if ( $this->delete( (int) $activity->getId() ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	private function hydrate( \WP_Post $post ): Activity {
		$programId    = (int) get_post_meta( $post->ID, '_pa_program_id', true );
		$templateId   = get_post_meta( $post->ID, '_pa_template_id', true );
		$schemaJson   = get_post_meta( $post->ID, '_pa_schema', true );
		$settingsJson = get_post_meta( $post->ID, '_pa_settings', true );

		$schema = $schemaJson ? ActivitySchema::fromJson( $schemaJson ) : ActivitySchema::empty();

		$translationId = $templateId ? (int) $templateId : $post->ID;
		$schema        = $this->translator->translate( $translationId, $schema );

		$settings = $settingsJson ? (array) json_decode( $settingsJson, true ) : [];

		$sectionMeta = get_post_meta( $post->ID, '_pa_section_id', true );
		$sectionId   = $sectionMeta !== '' ? (int) $sectionMeta : null;

		return Activity::reconstitute(
			id:         $post->ID,
			programId:  $programId,
			schema:     $schema,
			settings:   $settings,
			day:        (int) get_post_meta( $post->ID, '_pa_day', true ) ?: 1,
			week:       (int) get_post_meta( $post->ID, '_pa_week', true ) ?: 1,
			position:   (int) get_post_meta( $post->ID, '_pa_position', true ),
			sectionId:  $sectionId,
			templateId: $templateId !== '' ? (int) $templateId : null,
			createdAt:  CarbonImmutable::parse( $post->post_date ),
		);
	}

	/** @param \WP_Post[] $posts */
	private function hydrateCollection( array $posts ): array {
		return array_map( fn( \WP_Post $p ) => $this->hydrate( $p ), $posts );
	}
}
