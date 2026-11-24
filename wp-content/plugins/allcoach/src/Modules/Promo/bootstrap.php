<?php
/**
 * Free promo bootstrap — runs at plugin-load time, BEFORE Plugin::boot() calls
 * loadModules(), mirroring Pro's src/Pro/bootstrap.php. It advertises Pro-only
 * addons as locked placeholders on the Add-ons page and wires the gate that
 * keeps them locked (Free can never activate a paid addon).
 *
 * @package AllCoach
 */

declare(strict_types=1);

namespace AllCoach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Modules\AddonInterface;
use AllCoach\Modules\Promo\ContractsPromoAddon;
use AllCoach\Modules\Promo\NotesPromoAddon;
use AllCoach\Modules\Promo\RecurringActivitiesPromoAddon;
use AllCoach\Modules\Promo\WhiteLabelPromoAddon;

use function AllCoach\Support\upgradeUrl;

/**
 * Paid addons can never be activated on the Free plugin. Returning an
 * activation requirement here both blocks the enable endpoint (403) and drives
 * the `locked` flag + `upgrade_url` shown on the Add-ons page. The pricing URL
 * is tagged with UTM params so the marketing site can attribute the upgrade
 * click to the specific addon card.
 */
add_filter(
	'allcoach:module:activation_requirements',
	static function ( array $errors, string $id, $module ): array {
		if ( ! $module instanceof AddonInterface || ! $module->isPro() ) {
			return $errors;
		}

		$errors[] = [
			'code'        => 'pro_required',
			'message'     => __( 'This add-on is available in AllCoach Pro.', 'allcoach' ),
			'upgrade_url' => upgradeUrl( $id, 'allcoach-free' ),
		];

		return $errors;
	},
	10,
	3
);

/*
 * Advertise Pro-only addons as locked placeholders on the Add-ons page.
 *
 * This shares the id ('white_label') with Pro's real addon. That is only safe
 * because ghost mode (allcoach.php) returns before this file loads when Pro is
 * active, so the promo and the real WhiteLabelAddon never register into the same
 * ModuleRegistry. The registry is first-registration-wins and Free loads first
 * alphabetically, so if that ghost-mode guard ever regressed this placeholder
 * would shadow the real addon — keep the two coupled to that invariant.
 */
add_filter(
	'allcoach:modules:available',
	static function ( array $modules ): array {
		$modules['white_label']         = WhiteLabelPromoAddon::class;
		$modules['notes']               = NotesPromoAddon::class;
		$modules['contracts']           = ContractsPromoAddon::class;
		$modules['recurring_activities'] = RecurringActivitiesPromoAddon::class;
		return $modules;
	}
);
