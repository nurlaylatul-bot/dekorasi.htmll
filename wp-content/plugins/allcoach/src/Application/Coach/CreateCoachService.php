<?php

declare(strict_types=1);

namespace AllCoach\Application\Coach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Domain\Coach\Coach;
use AllCoach\Domain\Coach\CoachRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class CreateCoachService {

	use Hooks;

	public function __construct(
		private readonly CoachRepository $repository,
		private readonly Logger $logger,
	) {}

	public function execute( CoachDTO $dto ): Coach {
		if ( $this->repository->findByEmail( $dto->email ) !== null ) {
			throw ValidationException::withErrors(
				[
					'email' => esc_html__( 'A coach with this email already exists.', 'allcoach' ),
				]
			);
		}

		$coach = new Coach( name: $dto->name, email: $dto->email );

		if ( $dto->bio !== '' ) {
			$coach->setBio( $dto->bio );
		}

		$coach = $this->repository->save( $coach );

		$this->logger->info( 'Coach created', [ 'coach_id' => $coach->getId() ] );
		$this->doAction( 'allcoach:coach:created', $coach );

		return $coach;
	}
}
