<?php

declare(strict_types=1);

namespace AllCoach\Modules\Promo;

defined( 'ABSPATH' ) || exit;

use AllCoach\Modules\AbstractAddon;

/**
 * Free-side promotional placeholder for the Pro-only Notes addon.
 *
 * It carries the same public metadata as Pro's real NotesAddon so the Add-ons
 * page can advertise it, but it never boots or activates — it is always locked,
 * and the activation gate in promo/bootstrap.php points the user at the pricing
 * page. When AllCoach Pro is active, Free ghosts itself entirely and the real
 * addon takes this placeholder's spot.
 *
 * Keep id/name/description/category/icon/docsUrl in sync with
 * AllCoachPro\Modules\Notes\NotesAddon — drift here shows up as the card/copy
 * changing after a user upgrades to Pro.
 */
class NotesPromoAddon extends AbstractAddon {

	protected string $id          = 'notes';
	protected string $name        = 'Notes for Clients & Coach';
	protected string $description = 'Coaches and clients can add private or shared notes to each appointment.';
	protected string $version     = '1.0.0';
	protected string $category    = 'clients';
	protected bool $pro           = true;
	protected string $icon          = 'edit';
	protected string $docsUrl       = 'https://allcoach.io/docs/notes';

	/**
	 * A promo placeholder is never actually enabled — it exists only to surface
	 * the upsell. Forcing false also keeps it locked regardless of any enabled
	 * state left in the shared _allcoach_addon_states option by a prior Pro run.
	 */
	public function isEnabled(): bool {
		return false;
	}
}
