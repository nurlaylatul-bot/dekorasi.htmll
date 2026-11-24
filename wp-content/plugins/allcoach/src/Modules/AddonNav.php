<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the admin navigation an addon contributes when enabled.
 *
 * A single source of truth read by both the PHP submenu builder
 * (AdminServiceProvider) and the React top-nav (via script data), so an
 * addon's menu/nav appears only while it is active and vanishes when disabled.
 */
final class AddonNav {

	/**
	 * @param string $slug       Route slug, e.g. "certificates".
	 * @param string $label      Menu/nav label.
	 * @param string $route      SPA hash route, e.g. "/certificates/".
	 * @param string $capability Capability required to see it.
	 * @param int    $order      Sort order among addon nav items.
	 * @param string $parent     WP admin parent menu slug.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $label,
		public readonly string $route,
		public readonly string $capability = 'manage_options',
		public readonly int $order = 50,
		public readonly string $parent = 'allcoach',
	) {}

	/** @return array{slug: string, label: string, route: string, order: int} */
	public function toArray(): array {
		return [
			'slug'  => $this->slug,
			'label' => $this->label,
			'route' => $this->route,
			'order' => $this->order,
		];
	}
}
