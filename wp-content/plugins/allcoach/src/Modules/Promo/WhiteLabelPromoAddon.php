<?php

declare(strict_types=1);

namespace AllCoach\Modules\Promo;

defined( 'ABSPATH' ) || exit;

use AllCoach\Modules\AbstractAddon;

/**
 * Free-side promotional placeholder for the Pro-only White Label addon.
 *
 * It carries the same public metadata as Pro's real WhiteLabelAddon so the
 * Add-ons page can advertise it, but it never boots or activates — it is always
 * locked, and the activation gate in promo/bootstrap.php points the user at the
 * pricing page. When AllCoach Pro is active, Free ghosts itself entirely and the
 * real addon takes this placeholder's spot.
 *
 * Keep id/name/description/category/icon/docsUrl/settingsRoute in sync with
 * AllCoachPro\Modules\WhiteLabel\WhiteLabelAddon — drift here shows up as the
 * card/copy changing after a user upgrades to Pro.
 */
class WhiteLabelPromoAddon extends AbstractAddon {

	protected string $id          = 'white_label';
	protected string $name        = 'White Label';
	protected string $description = 'Rebrand the admin menu, header logo, and Coach/Client role names.';
	protected string $version     = '1.0.0';
	protected string $category    = 'branding';
	protected bool $pro           = true;
	protected string $icon          = 'admin-appearance';
	protected string $docsUrl       = 'https://allcoach.io/docs/white-label';
	protected string $settingsRoute = '/settings/white-label';

	/**
	 * A promo placeholder is never actually enabled — it exists only to surface
	 * the upsell. Forcing false also keeps it locked regardless of any enabled
	 * state left in the shared _allcoach_addon_states option by a prior Pro run.
	 */
	public function isEnabled(): bool {
		return false;
	}
}
