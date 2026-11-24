<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

class Program extends PostType {

	protected function getPostType(): string {
		return 'allcoach_program';
	}

	protected function getLabels(): array {
		return [
			'name'               => __( 'Programs', 'allcoach' ),
			'singular_name'      => __( 'Program', 'allcoach' ),
			'menu_name'          => __( 'Programs', 'allcoach' ),
			'add_new'            => __( 'Add New', 'allcoach' ),
			'add_new_item'       => __( 'Add New Program', 'allcoach' ),
			'edit_item'          => __( 'Edit Program', 'allcoach' ),
			'new_item'           => __( 'New Program', 'allcoach' ),
			'view_item'          => __( 'View Program', 'allcoach' ),
			'search_items'       => __( 'Search Programs', 'allcoach' ),
			'not_found'          => __( 'No programs found', 'allcoach' ),
			'not_found_in_trash' => __( 'No programs found in trash', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'public'          => true,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'map_meta_cap'    => true,
			'has_archive'     => true,
			'capability_type' => [ 'allcoach_program', 'allcoach_programs' ],
			'supports'        => [ 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ],
			'taxonomies'      => [ 'allcoach_program_cat' ],
		];
	}
}
