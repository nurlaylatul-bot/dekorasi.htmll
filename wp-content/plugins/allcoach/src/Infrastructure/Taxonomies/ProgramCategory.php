<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Taxonomies;

defined( 'ABSPATH' ) || exit;

class ProgramCategory extends Taxonomy {

	protected function getTaxonomy(): string {
		return 'allcoach_program_cat';
	}

	protected function getObjectTypes(): array {
		return [ 'allcoach_program' ];
	}

	protected function getLabels(): array {
		return [
			'name'              => __( 'Program Categories', 'allcoach' ),
			'singular_name'     => __( 'Program Category', 'allcoach' ),
			'menu_name'         => __( 'Program Categories', 'allcoach' ),
			'all_items'         => __( 'All Program Categories', 'allcoach' ),
			'edit_item'         => __( 'Edit Program Category', 'allcoach' ),
			'view_item'         => __( 'View Program Category', 'allcoach' ),
			'update_item'       => __( 'Update Program Category', 'allcoach' ),
			'add_new_item'      => __( 'Add New Program Category', 'allcoach' ),
			'new_item_name'     => __( 'New Program Category Name', 'allcoach' ),
			'parent_item'       => __( 'Parent Category', 'allcoach' ),
			'parent_item_colon' => __( 'Parent Category:', 'allcoach' ),
			'search_items'      => __( 'Search Program Categories', 'allcoach' ),
			'not_found'         => __( 'No program categories found', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'hierarchical' => false,
			'show_in_rest' => false,
		];
	}
}
