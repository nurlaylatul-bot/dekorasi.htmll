<?php

declare(strict_types=1);

namespace AllCoach\Application\Availability;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Domain\Availability\Availability;
use AllCoach\Domain\Availability\AvailabilityRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class UpdateAvailabilityService {

	use Hooks;

	public function __construct(
		private readonly AvailabilityRepository $repository,
		private readonly Logger $logger,
	) {}

	public function execute( int $availabilityId, AvailabilityDTO $dto ): Availability {
		$availability = $this->repository->findById( $availabilityId );
		if ( $availability === null ) {
			throw NotFoundException::entity( esc_html__( 'Availability', 'allcoach' ), absint( $availabilityId ) );
		}

		$availability->setUserId( $dto->userId );
		$availability->setTimezone( $dto->timezone );
		$availability->setStartDate( $dto->startDate );
		$availability->setSchedule( $dto->schedule );
		$availability->setIsProgramSpecific( $dto->isProgramSpecific );
		$availability->setProgramIds( $dto->programIds );

		$availability = $this->repository->save( $availability );

		$this->logger->info( 'Availability updated', [ 'availability_id' => $availability->getId() ] );
		$this->doAction( 'allcoach:availability:updated', $availability );

		return $availability;
	}
}
