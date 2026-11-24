<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

class Availability extends PostType {

	protected function getPostType(): string {
		// Shorter version of 'allcoach_availability' to avoid WP error.
		return 'allcoach_avail';
	}

	protected function getLabels(): array {
		return [
			'name'               => __( 'Availabilities', 'allcoach' ),
			'singular_name'      => __( 'Availability', 'allcoach' ),
			'menu_name'          => __( 'Availabilities', 'allcoach' ),
			'add_new'            => __( 'Add New', 'allcoach' ),
			'add_new_item'       => __( 'Add New Availability', 'allcoach' ),
			'edit_item'          => __( 'Edit Availability', 'allcoach' ),
			'new_item'           => __( 'New Availability', 'allcoach' ),
			'view_item'          => __( 'View Availability', 'allcoach' ),
			'search_items'       => __( 'Search Availabilities', 'allcoach' ),
			'not_found'          => __( 'No availabilities found', 'allcoach' ),
			'not_found_in_trash' => __( 'No availabilities found in trash', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'map_meta_cap'    => true,
			'has_archive'     => false,
			'capability_type' => [ 'allcoach_availability', 'allcoach_availabilities' ],
			'supports'        => [ 'title', 'author' ],
		];
	}
}
