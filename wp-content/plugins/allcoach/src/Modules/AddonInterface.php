<?php

declare(strict_types=1);

namespace AllCoach\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * A user-toggleable feature ("addon") shown on the Add-ons page.
 *
 * Extends the base module contract with the metadata the Add-ons UI needs
 * (category, icon, docs) and an activate/deactivate lifecycle that runs when
 * the user flips the toggle (not on plugin activation).
 *
 * Core, always-on features implement only ModuleInterface (see CoreModule) and
 * are rendered as non-toggleable "Core Feature" rows.
 */
interface AddonInterface extends ModuleInterface {

	/** Grouping bucket on the Add-ons page, e.g. "engagement", "branding". */
	public function getCategory(): string;

	/** Whether activating this addon requires a valid Pro license. */
	public function isPro(): bool;

	/**
	 * Non-license prerequisites (e.g. `['woocommerce']`) surfaced as a
	 * "Requires X" note; empty when there are none.
	 *
	 * @return list<string>
	 */
	public function getRequires(): array;

	/** Dashicon slug or asset URL for the card. */
	public function getIcon(): string;

	/** Optional thumbnail asset URL ('' when none). */
	public function getThumbnail(): string;

	/** Documentation URL ('' when none). */
	public function getDocsUrl(): string;

	/**
	 * Admin SPA route for this addon's own settings screen (e.g.
	 * '/settings/white-label'), or '' when it has no settings screen. Drives the
	 * gear link on the Add-ons card and the addon's tab in Settings.
	 */
	public function getSettingsRoute(): string;

	/** Whether the addon is on by default on a fresh install. */
	public function isEnabledByDefault(): bool;

	/** Admin nav this addon contributes when enabled, or null if headless. */
	public function getNav(): ?AddonNav;

	/** One-time setup when the user enables the addon (idempotent). */
	public function activate(): void;

	/** Teardown when the user disables the addon (usually keeps data). */
	public function deactivate(): void;

	/**
	 * Serialised metadata for the REST/Add-ons UI.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array;
}
