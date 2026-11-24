<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\I18n\ActivitySchemaTranslator;
use AllCoach\Domain\Activity\ActivitySchema;
use AllCoach\Domain\Activity\ActivityTemplate;
use AllCoach\Domain\Activity\ActivityTemplateRepository as ActivityTemplateRepositoryInterface;
use Carbon\CarbonImmutable;

class ActivityTemplateRepository implements ActivityTemplateRepositoryInterface {

	private const POST_TYPE = 'allcoach_activity';

	public function __construct(
		private readonly ActivitySchemaTranslator $translator,
	) {}

	public function findById( int $id ): ?ActivityTemplate {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::POST_TYPE ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	public function findByCoachId( int $coachId, int $limit = 100, int $offset = 0 ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'post_status'    => 'any',
				'author'         => $coachId,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		return $this->hydrateCollection( $query->posts );
	}

	public function findAll( int $limit = 100, int $offset = 0 ): array {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		return $this->hydrateCollection( $query->posts );
	}

	public function search( string $query, int $limit = 50, int $offset = 0 ): array {
		$wpQuery = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				's'              => $query,
				'posts_per_page' => $limit,
				'offset'         => $offset,
				'post_status'    => 'any',
			]
		);

		return $this->hydrateCollection( $wpQuery->posts );
	}

	public function save( ActivityTemplate $library ): ActivityTemplate {
		return $library->isNew() ? $this->insert( $library ) : $this->update( $library );
	}

	private function insert( ActivityTemplate $library ): ActivityTemplate {
		$postId = wp_insert_post(
			[
				'post_type'    => self::POST_TYPE,
				'post_title'   => $library->getTitle(),
				'post_content' => $library->getDescription(),
				'post_status'  => $library->isPublished() ? 'publish' : 'draft',
				'post_author'  => $library->getCoachId() ?? get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $postId ) ) {
			throw new \RuntimeException( 'Failed to save activity library item: ' . esc_html( $postId->get_error_message() ) );
		}

		$post    = get_post( $postId );
		$library = ActivityTemplate::reconstitute(
			id:          $postId,
			title:       $library->getTitle(),
			description: $library->getDescription(),
			schema:      $library->getSchema(),
			coachId:     $library->getCoachId(),
			isPublished: $library->isPublished(),
			createdAt:   CarbonImmutable::parse( $post->post_date ),
			updatedAt:   null,
		);
		$this->saveMeta( $library );
		$this->translator->registerStrings( $postId, $library->getSchema() );

		return $library;
	}

	private function update( ActivityTemplate $library ): ActivityTemplate {
		wp_update_post(
			[
				'ID'           => $library->getId(),
				'post_title'   => $library->getTitle(),
				'post_content' => $library->getDescription(),
				'post_status'  => $library->isPublished() ? 'publish' : 'draft',
			],
			true
		);

		$this->saveMeta( $library );
		$this->translator->registerStrings( (int) $library->getId(), $library->getSchema() );

		return $library;
	}

	private function saveMeta( ActivityTemplate $library ): void {
		$id = $library->getId();
		update_post_meta( $id, '_activity_schema', $library->getSchema()->toJson() );
		update_post_meta( $id, '_activity_version', $library->getSchema()->getVersion() );
	}

	public function delete( int $id ): bool {
		$result = wp_delete_post( $id, true );
		return $result !== false && $result !== null;
	}

	public function count(): int {
		return (int) wp_count_posts( self::POST_TYPE )->publish;
	}

	public function countByCoachId( int $coachId ): int {
		$query = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'author'         => $coachId,
				'fields'         => 'ids',
			]
		);

		return (int) $query->found_posts;
	}

	private function hydrate( \WP_Post $post ): ActivityTemplate {
		$schemaJson = get_post_meta( $post->ID, '_activity_schema', true );
		$schema     = $schemaJson ? ActivitySchema::fromJson( $schemaJson ) : ActivitySchema::empty();

		$schema = $this->translator->translate( $post->ID, $schema );

		return ActivityTemplate::reconstitute(
			id:          $post->ID,
			title:       $post->post_title,
			description: $post->post_content,
			schema:      $schema,
			coachId:     $post->post_author ? (int) $post->post_author : null,
			isPublished: $post->post_status === 'publish',
			createdAt:   CarbonImmutable::parse( $post->post_date ),
			updatedAt:   null,
		);
	}

	/** @param \WP_Post[] $posts */
	private function hydrateCollection( array $posts ): array {
		return array_map( fn( \WP_Post $p ) => $this->hydrate( $p ), $posts );
	}
}
