<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\Taxonomies;

defined( 'ABSPATH' ) || exit;

class ActivityGroup extends Taxonomy {

	protected function getTaxonomy(): string {
		return 'allcoach_act_group';
	}

	protected function getObjectTypes(): array {
		return [ 'allcoach_act_type' ];
	}

	protected function getLabels(): array {
		return [
			'name'              => __( 'Activity Groups', 'allcoach' ),
			'singular_name'     => __( 'Activity Group', 'allcoach' ),
			'menu_name'         => __( 'Activity Groups', 'allcoach' ),
			'all_items'         => __( 'All Activity Groups', 'allcoach' ),
			'edit_item'         => __( 'Edit Activity Group', 'allcoach' ),
			'view_item'         => __( 'View Activity Group', 'allcoach' ),
			'update_item'       => __( 'Update Activity Group', 'allcoach' ),
			'add_new_item'      => __( 'Add New Activity Group', 'allcoach' ),
			'new_item_name'     => __( 'New Activity Group Name', 'allcoach' ),
			'parent_item'       => __( 'Parent Category', 'allcoach' ),
			'parent_item_colon' => __( 'Parent Category:', 'allcoach' ),
			'search_items'      => __( 'Search Activity Groups', 'allcoach' ),
			'not_found'         => __( 'No activity groups found', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'hierarchical' => false,
			'show_in_rest' => false,
		];
	}
}
