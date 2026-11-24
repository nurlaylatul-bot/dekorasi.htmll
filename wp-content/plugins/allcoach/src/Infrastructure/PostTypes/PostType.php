<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

use AllCoach\Traits\Hooks;

abstract class PostType {

	use Hooks;

	abstract protected function getPostType(): string;
	abstract protected function getLabels(): array;
	abstract protected function getArgs(): array;

	public function register(): void {
		register_post_type(
			$this->getPostType(),
			wp_parse_args( $this->getArgs(), $this->getDefaultArgs() )
		);
	}

	protected function getDefaultArgs(): array {
		return [
			'labels'             => $this->getLabels(),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => [ 'title', 'editor' ],
			'show_in_rest'       => false,
		];
	}

	public function getPostTypeObject(): ?\WP_Post_Type {
		return get_post_type_object( $this->getPostType() );
	}
}
