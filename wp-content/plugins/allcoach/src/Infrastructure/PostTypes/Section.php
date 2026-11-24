<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

class Section extends PostType {

	protected function getPostType(): string {
		return 'allcoach_section';
	}

	protected function getLabels(): array {
		return [
			'name'               => __( 'Sections', 'allcoach' ),
			'singular_name'      => __( 'Section', 'allcoach' ),
			'add_new_item'       => __( 'Add New Section', 'allcoach' ),
			'edit_item'          => __( 'Edit Section', 'allcoach' ),
			'not_found'          => __( 'No sections found', 'allcoach' ),
			'not_found_in_trash' => __( 'No sections found in trash', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => [ 'allcoach_section', 'allcoach_sections' ],
			'map_meta_cap'    => true,
			'supports'        => [ 'title', 'editor' ],
		];
	}
}
