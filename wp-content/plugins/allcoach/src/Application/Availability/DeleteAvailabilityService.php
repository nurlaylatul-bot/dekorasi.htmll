<?php

declare(strict_types=1);

namespace AllCoach\Application\Availability;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Domain\Availability\AvailabilityRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class DeleteAvailabilityService {

	use Hooks;

	public function __construct(
		private readonly AvailabilityRepository $repository,
		private readonly Logger $logger,
	) {}

	public function execute( int $availabilityId ): void {
		$availability = $this->repository->findById( $availabilityId );
		if ( $availability === null ) {
			throw NotFoundException::entity( esc_html__( 'Availability', 'allcoach' ), absint( $availabilityId ) );
		}

		if ( ! $this->repository->delete( $availabilityId ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: Availability ID */
					esc_html__( 'Failed to delete availability with ID %d', 'allcoach' ),
					absint( $availabilityId )
				)
			);
		}

		$this->logger->info( 'Availability deleted', [ 'availability_id' => $availabilityId ] );
		$this->doAction( 'allcoach:availability:deleted', $availabilityId );
	}
}
