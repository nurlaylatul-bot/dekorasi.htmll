<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Taxonomies;

defined( 'ABSPATH' ) || exit;

abstract class Taxonomy {

	abstract protected function getTaxonomy(): string;

	abstract protected function getObjectTypes(): array;

	abstract protected function getLabels(): array;

	abstract protected function getArgs(): array;

	public function register(): void {
		register_taxonomy(
			$this->getTaxonomy(),
			$this->getObjectTypes(),
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
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'query_var'          => true,
			'rewrite'            => false,
		];
	}

	public function getTaxonomyObject(): ?\WP_Taxonomy {
		return get_taxonomy( $this->getTaxonomy() );
	}
}
