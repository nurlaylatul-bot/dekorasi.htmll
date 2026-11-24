<?php

declare(strict_types=1);

namespace AllCoach\Infrastructure\PostTypes;

defined( 'ABSPATH' ) || exit;

class Session extends PostType {

	protected function getPostType(): string {
		return 'allcoach_session';
	}

	protected function getLabels(): array {
		return [
			'name'               => __( 'Sessions', 'allcoach' ),
			'singular_name'      => __( 'Session', 'allcoach' ),
			'add_new_item'       => __( 'Add New Session', 'allcoach' ),
			'edit_item'          => __( 'Edit Session', 'allcoach' ),
			'not_found'          => __( 'No sessions found', 'allcoach' ),
			'not_found_in_trash' => __( 'No sessions found in trash', 'allcoach' ),
		];
	}

	protected function getArgs(): array {
		return [
			'public'          => false,
			'show_ui'         => false,
			'show_in_menu'    => false,
			'capability_type' => [ 'allcoach_session', 'allcoach_sessions' ],
			'map_meta_cap'    => true,
			'supports'        => [ 'title', 'custom-fields' ],
		];
	}
}
