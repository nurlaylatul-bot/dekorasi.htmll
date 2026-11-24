<?php
/**
 * Profile page template.
 *
 * @var string                  $pageTitle     The page title.
 * @var \AllCoach\Application\Client\ClientDTO $client      The client dto object.
 * @var list<\AllCoach\Core\Data\Timezone>     $timezones   Available timezones.
 * @var \WP_User                $currentUser The current user.
 * @var bool                    $isCoach     Whether user is a coach.
 * @var bool                    $isClient    Whether user is a client.
 * @var callable                $routerUrl   URL generator.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables
$fullName  = trim( "$client->firstName $client->lastName" ) ?: $currentUser->display_name;
$initials  = mb_strtoupper( mb_substr( $client->firstName ?: $currentUser->display_name, 0, 1 ) . mb_substr( $client->lastName, 0, 1 ) );
$email     = $client->email;
$avatarUrl = get_avatar_url( $client->id, [ 'size' => 104 ] );
$phone     = $client->phone ?: '';
?>

<div class="ac-page-header">
	<span class="ac-page-header__icon">
		<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
	</span>
	<h2><?php echo esc_html( $pageTitle ); ?></h2>
</div>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:1.25rem">
	<div class="ac-avatar-xl">
		<?php if ( $avatarUrl ) : ?>
			<img src="<?php echo esc_url( $avatarUrl ); ?>" alt="<?php echo esc_attr( $fullName ); ?>">
		<?php else : ?>
			<?php echo esc_html( $initials ); ?>
		<?php endif; ?>
	</div>
	<div>
		<div style="font-size:var(--text-md);font-weight:var(--weight-semibold);color:var(--ac-text)"><?php echo esc_html( $fullName ); ?></div>
		<div style="font-size:var(--text-sm);color:var(--ac-text-muted);margin-top:2px"><?php echo esc_html( $email ); ?></div>
	</div>
</div>

<!-- Read mode -->
<div class="ac-card ac-card--padded" id="ac-profile-read">
	<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
		<div class="ac-card__title" style="margin-bottom:0"><?php esc_html_e( 'Personal information', 'allcoach' ); ?></div>
		<button type="button" class="ac-btn ac-btn--ghost ac-btn--sm" id="ac-profile-edit-btn">
			<?php esc_html_e( 'Edit', 'allcoach' ); ?>
		</button>
	</div>
	<div class="ac-profile-grid">
		<div>
			<div class="ac-profile-grid__label"><?php esc_html_e( 'First name', 'allcoach' ); ?></div>
			<div class="ac-profile-grid__value" data-field="first_name"><?php echo esc_html( ! empty( $client->firstName ) ? $client->firstName : '-' ); ?></div>
		</div>
		<div>
			<div class="ac-profile-grid__label"><?php esc_html_e( 'Last name', 'allcoach' ); ?></div>
			<div class="ac-profile-grid__value" data-field="last_name"><?php echo esc_html( ! empty( $client->lastName ) ? $client->lastName : '-' ); ?></div>
		</div>
		<div class="ac-profile-grid__full">
			<div class="ac-profile-grid__label"><?php esc_html_e( 'Email address', 'allcoach' ); ?></div>
			<div class="ac-profile-grid__value" data-field="email"><?php echo esc_html( $email ); ?></div>
		</div>
		<div class="ac-profile-grid__full">
			<div class="ac-profile-grid__label"><?php esc_html_e( 'Phone number', 'allcoach' ); ?></div>
			<div class="ac-profile-grid__value" data-field="phone"><?php echo esc_html( ! empty( $client->phone ) ? $client->phone : '-' ); ?></div>
		</div>
		<div class="ac-profile-grid__full">
			<div class="ac-profile-grid__label"><?php esc_html_e( 'Timezone', 'allcoach' ); ?></div>
			<div class="ac-profile-grid__value" data-field="timezone"><?php echo esc_html( ! empty( $client->timezone ) ? $client->timezone : '-' ); ?></div>
		</div>
	</div>
</div>

<!-- Edit mode -->
<form id="ac-profile-form" method="post" style="display:none">
	<?php wp_nonce_field( 'allcoach_update_profile', 'ac_profile_nonce' ); ?>

	<div class="ac-card ac-card--padded">
		<div class="ac-card__title" style="margin-bottom:12px"><?php esc_html_e( 'Personal information', 'allcoach' ); ?></div>
		<div class="ac-form-grid">
			<div class="ac-form-group">
				<label for="ac-first-name"><?php esc_html_e( 'First name', 'allcoach' ); ?></label>
				<input type="text" id="ac-first-name" class="ac-input" name="first_name" value="<?php echo esc_attr( $client->firstName ); ?>">
			</div>
			<div class="ac-form-group">
				<label for="ac-last-name"><?php esc_html_e( 'Last name', 'allcoach' ); ?></label>
				<input type="text" id="ac-last-name" class="ac-input" name="last_name" value="<?php echo esc_attr( $client->lastName ); ?>">
			</div>
			<div class="ac-form-group ac-form-full">
				<label for="ac-email"><?php esc_html_e( 'Email address', 'allcoach' ); ?></label>
				<input type="email" id="ac-email" class="ac-input" name="email" value="<?php echo esc_attr( $client->email ); ?>">
			</div>
			<div class="ac-form-group ac-form-full">
				<label for="ac-phone"><?php esc_html_e( 'Phone number', 'allcoach' ); ?></label>
				<input type="tel" id="ac-phone" class="ac-input" name="phone" value="<?php echo esc_attr( $client->phone ); ?>">
			</div>
			<div class="ac-form-group ac-form-full">
				<label for="ac-timezone"><?php esc_html_e( 'Timezone', 'allcoach' ); ?></label>
				<select id="ac-timezone" class="ac-select" name="timezone">
					<option value=""><?php esc_html_e( 'Select timezone', 'allcoach' ); ?></option>
					<?php foreach ( $timezones as $tz ) : ?>
						<optgroup label="<?php echo esc_attr( $tz->text ); ?>">
							<?php foreach ( $tz->utc as $identifier ) : ?>
								<option value="<?php echo esc_attr( $identifier ); ?>" <?php selected( $client->timezone, $identifier ); ?>>
									<?php echo esc_html( $identifier ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px">
			<button type="button" class="ac-btn ac-btn--ghost ac-btn--sm" id="ac-profile-cancel-btn">
				<?php esc_html_e( 'Cancel', 'allcoach' ); ?>
			</button>
			<button type="submit" class="ac-btn ac-btn--primary ac-btn--sm">
				<?php esc_html_e( 'Save changes', 'allcoach' ); ?>
			</button>
		</div>
	</div>
</form>

<div class="ac-card ac-card--padded" style="margin-top: var(--space-4)">
	<div style="display:flex;align-items:center;justify-content:space-between">
		<div>
			<div class="ac-card__title" style="margin-bottom:0"><?php esc_html_e( 'Password', 'allcoach' ); ?></div>
		</div>
		<button
			data-modal="ac-password-change"
			data-title="<?php esc_attr_e( 'Change password', 'allcoach' ); ?>"
			type="button"
			class="ac-btn ac-btn--ghost ac-btn--sm"
		><?php esc_html_e( 'Change password', 'allcoach' ); ?></button>
	</div>
</div>

<template id="ac-password-change">
	<form class="ac-password-form" method="post">
		<div class="ac-form-grid">
			<div class="ac-form-group ac-form-full">
				<label for="ac-current-password"><?php esc_html_e( 'Current password', 'allcoach' ); ?></label>
				<input type="password" id="ac-current-password" class="ac-input" name="current_password" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;">
			</div>
			<div class="ac-form-group ac-form-full">
				<label for="ac-new-password"><?php esc_html_e( 'New password', 'allcoach' ); ?></label>
				<input type="password" id="ac-new-password" class="ac-input" name="new_password" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;">
			</div>
			<div class="ac-form-group ac-form-full">
				<label for="ac-confirm-password"><?php esc_html_e( 'Confirm password', 'allcoach' ); ?></label>
				<input type="password" id="ac-confirm-password" class="ac-input" name="_password" placeholder="&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;">
			</div>
		</div>
		<div style="margin-top:1.1em;display:flex;align-items:center;justify-content:flex-end;gap:var(--space-2)">
			<button type="button" data-action="close" class="ac-btn ac-btn--ghost ac-btn--sm"><?php esc_html_e( 'Cancel', 'allcoach' ); ?></button>
			<button type="submit" class="ac-btn ac-btn--primary ac-btn--sm"><?php esc_html_e( 'Update password', 'allcoach' ); ?></button>
		</div>
	</form>
</template>
