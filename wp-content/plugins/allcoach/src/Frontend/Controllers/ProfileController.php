<?php

declare(strict_types=1);

namespace AllCoach\Frontend\Controllers;

defined( 'ABSPATH' ) || exit;

use AllCoach\Application\Client\ClientDTO;
use AllCoach\Core\Data\TimezoneData;
use AllCoach\Domain\Client\ClientRepository;
use AllCoach\Support\TemplateLoader;

class ProfileController extends BaseController {

	public function __construct(
		TemplateLoader $templateLoader,
		private readonly ClientRepository $clientRepository,
		private readonly TimezoneData $timezoneData,
	) {
		parent::__construct( $templateLoader );
	}

	public function index(): void {
		$userId = get_current_user_id();
		$client = $this->clientRepository->findById( $userId );

		if ( $client ) {
			$dto = ClientDTO::fromArray( $client->toArray() );
		} else {
			$user = get_userdata( $userId );
			$dto  = $user
				? ClientDTO::fromArray(
					[
						'id'         => $user->ID,
						'first_name' => $user->first_name,
						'last_name'  => $user->last_name,
						'email'      => $user->user_email,
						'phone'      => (string) get_user_meta( $userId, '_allcoach_client_phone', true ),
						'timezone'   => (string) get_user_meta( $userId, '_allcoach_client_timezone', true ),
					]
				)
				: ClientDTO::fromArray( [] );
		}

		$this->render(
			'account/profile.php',
			[
				'pageTitle' => __( 'My Profile', 'allcoach' ),
				'client'    => $dto,
				'timezones' => $this->timezoneData->getAll(),
			]
		);
	}
}
