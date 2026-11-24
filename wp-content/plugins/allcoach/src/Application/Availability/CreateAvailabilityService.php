<?php

declare(strict_types=1);

namespace AllCoach\Application\Availability;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Availability\Availability;
use AllCoach\Domain\Availability\AvailabilityRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class CreateAvailabilityService {

	use Hooks;

	public function __construct(
		private readonly AvailabilityRepository $repository,
		private readonly Logger $logger,
	) {}

	public function execute( AvailabilityDTO $dto ): Availability {
		$availability = new Availability(
			userId:            $dto->userId,
			timezone:          $dto->timezone,
			startDate:         $dto->startDate,
			schedule:         $dto->schedule,
			isProgramSpecific: $dto->isProgramSpecific,
			programIds:        $dto->programIds,
		);

		$availability = $this->repository->save( $availability );

		$this->logger->info( 'Availability created', [ 'availability_id' => $availability->getId() ] );
		$this->doAction( 'allcoach:availability:created', $availability );

		return $availability;
	}
}
