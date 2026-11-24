<?php

declare(strict_types=1);

namespace AllCoach\Application\Coach;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Domain\Coach\Coach;
use AllCoach\Domain\Coach\CoachRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class UpdateCoachService {

	use Hooks;

	public function __construct(
		private readonly CoachRepository $repository,
		private readonly Logger $logger
	) {
	}

	public function execute( int $coachId, CoachDTO $dto ): Coach {
		$coach = $this->repository->findById( $coachId );
		if ( $coach === null ) {
			throw NotFoundException::entity( esc_html__( 'Coach', 'allcoach' ), absint( $coachId ) );
		}

		$coach->setName( $dto->name );
		$coach->setEmail( $dto->email );
		$coach->setBio( $dto->bio );

		$coach = $this->repository->save( $coach );

		$this->logger->info( 'Coach updated', [ 'coach_id' => $coach->getId() ] );
		$this->doAction( 'allcoach:coach:updated', $coach );

		return $coach;
	}
}
