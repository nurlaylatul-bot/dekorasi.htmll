<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Persistence;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Section\Section;
use AllCoach\Domain\Section\SectionRepository as SectionRepositoryInterface;
use Carbon\CarbonImmutable;

// phpcs:disable WordPress.DB.SlowDBQuery

class SectionRepository implements SectionRepositoryInterface {

	private const POST_TYPE = 'allcoach_section';

	public function findById( int $id ): ?Section {
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
				'meta_query'     => [
					[
						'key'     => '_ps_program_id',
						'value'   => $programId,
						'type'    => 'NUMERIC',
						'compare' => '=',
					],
				],
				'meta_key'       => '_ps_position',
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			]
		);

		return $this->hydrateCollection( $query->posts );
	}

	public function save( Section $section ): Section {
		return $section->isNew() ? $this->insert( $section ) : $this->update( $section );
	}

	public function delete( int $id ): bool {
		$result = wp_delete_post( $id, true );
		return $result !== false && $result !== null;
	}

	public function deleteByProgram( int $programId ): int {
		$sections = $this->findByProgram( $programId );
		$deleted  = 0;

		foreach ( $sections as $section ) {
			if ( $this->delete( (int) $section->getId() ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	private function insert( Section $section ): Section {
		$postId = wp_insert_post(
			[
				'post_type'    => self::POST_TYPE,
				'post_title'   => $section->getTitle(),
				'post_content' => $section->getDescription(),
				'post_status'  => 'publish',
			],
			true
		);

		if ( is_wp_error( $postId ) ) {
			throw new \RuntimeException( 'Failed to save section: ' . esc_html( $postId->get_error_message() ) );
		}

		$post    = get_post( $postId );
		$section = Section::reconstitute(
			id:          $postId,
			programId:   $section->getProgramId(),
			title:       $section->getTitle(),
			description: $section->getDescription(),
			position:    $section->getPosition(),
			createdAt:   CarbonImmutable::parse( $post->post_date ),
		);
		$this->saveMeta( $section );

		return $section;
	}

	private function update( Section $section ): Section {
		wp_update_post(
			[
				'ID'           => $section->getId(),
				'post_title'   => $section->getTitle(),
				'post_content' => $section->getDescription(),
			]
		);

		$this->saveMeta( $section );

		return $section;
	}

	private function saveMeta( Section $section ): void {
		$id = $section->getId();
		update_post_meta( $id, '_ps_program_id', $section->getProgramId() );
		update_post_meta( $id, '_ps_position', $section->getPosition() );
	}

	private function hydrate( \WP_Post $post ): Section {
		$programId = (int) get_post_meta( $post->ID, '_ps_program_id', true );
		$position  = (int) get_post_meta( $post->ID, '_ps_position', true );

		return Section::reconstitute(
			id:          $post->ID,
			programId:   $programId,
			title:       $post->post_title ?: '',
			description: $post->post_content ?: '',
			position:    $position,
			createdAt:   CarbonImmutable::parse( $post->post_date ),
		);
	}

	/**
	 * @param \WP_Post[] $posts
	 * @return Section[]
	 */
	private function hydrateCollection( array $posts ): array {
		return array_map( fn( \WP_Post $p ) => $this->hydrate( $p ), $posts );
	}
}
