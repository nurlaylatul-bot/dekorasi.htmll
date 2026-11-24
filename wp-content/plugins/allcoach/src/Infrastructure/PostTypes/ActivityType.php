<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

class ActivityType extends PostType {

	protected function getPostType(): string {
		return 'allcoach_act_type';
	}

	protected function getLabels(): array {
		return [
			'name'               => __( 'Activity Types', 'allcoach' ),
			'singular_name'      => __( 'Activity Type', 'allcoach' ),
			'menu_name'          => __( 'Activity Type', 'allcoach' ),
			'add_new'            => __( 'Add New', 'allcoach' ),
			'add_new_item'       => __( 'Add New Activity Type', 'allcoach' ),
			'edit_item'          => __( 'Edit Activity Type', 'allcoach' ),
			'new_item'           => __( 'New Activity Type', 'allcoach' ),
			'view_item'          => __( 'View Activity Type', 'allcoach' ),
			'search_items'       => __( 'Search Activity Types', 'allcoach' ),
			'not_found'          => __( 'No activity types found', 'allcoach' ),
			'not_found_in_trash' => __( 'No activity types found in trash', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => [ 'allcoach_act_type', 'allcoach_act_types' ],
			'map_meta_cap'    => true,
			'supports'        => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
			// 'taxonomies'      => [ 'allcoach_act_group' ],
		];
	}
}
