<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for user-toggleable addons. Subclasses set metadata via protected
 * properties and implement boot() (and activate()/deactivate() if they need
 * setup). State handling is inherited from AbstractModule (per-user states map
 * + default-on-first-seen).
 */
abstract class AbstractAddon extends AbstractModule implements AddonInterface {

	protected string $category = 'general';
	protected bool $pro        = false;
	/** @var list<string> */
	protected array $requires       = [];
	protected string $icon          = 'admin-plugins';
	protected string $thumbnail     = '';
	protected string $docsUrl       = '';
	protected string $settingsRoute = '';
	protected ?AddonNav $nav        = null;

	public function getCategory(): string {
		return $this->category;
	}

	public function isPro(): bool {
		return $this->pro;
	}

	/** @return list<string> */
	public function getRequires(): array {
		return $this->requires;
	}

	public function getIcon(): string {
		return $this->icon;
	}

	public function getThumbnail(): string {
		return $this->thumbnail;
	}

	public function getDocsUrl(): string {
		return $this->docsUrl;
	}

	public function getSettingsRoute(): string {
		return $this->settingsRoute;
	}

	public function getNav(): ?AddonNav {
		return $this->nav;
	}

	public function activate(): void {
		// Override in child addons that need setup (tables, options, rewrites).
	}

	public function deactivate(): void {
		// Override in child addons that need teardown.
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'id'             => $this->getId(),
			'name'           => $this->getName(),
			'description'    => $this->getDescription(),
			'version'        => $this->getVersion(),
			'category'       => $this->getCategory(),
			'pro'            => $this->isPro(),
			'requires'       => $this->getRequires(),
			'icon'           => $this->getIcon(),
			'thumbnail'      => $this->getThumbnail(),
			'docs_url'       => $this->getDocsUrl(),
			'settings_route' => $this->getSettingsRoute(),
			'type'           => 'addon',
			'toggleable'     => true,
			'default'        => $this->isEnabledByDefault(),
			'enabled'        => $this->isEnabled(),
		];
	}
}
