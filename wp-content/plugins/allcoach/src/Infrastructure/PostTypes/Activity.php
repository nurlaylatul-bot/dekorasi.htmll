<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

class Activity extends PostType {

	protected function getPostType(): string {
		return 'allcoach_activity';
	}


	protected function getLabels(): array {
		return [
			'name'               => __( 'Activities', 'allcoach' ),
			'singular_name'      => __( 'Activity', 'allcoach' ),
			'add_new_item'       => __( 'Add New Activity', 'allcoach' ),
			'edit_item'          => __( 'Edit Activity', 'allcoach' ),
			'not_found'          => __( 'No activities found', 'allcoach' ),
			'not_found_in_trash' => __( 'No activities found in trash', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => [ 'allcoach_activity', 'allcoach_activities' ],
			'map_meta_cap'    => true,
			'supports'        => [ 'title', 'thumbnail', 'custom-fields' ],
		];
	}
}
