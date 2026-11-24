<?php

declare(strict_types=1);

namespace AllCoach\Application\Program;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Domain\Booking\MeetingType;
use AllCoach\Domain\Program\Events\ProgramCreated;
use AllCoach\Domain\Program\Program;
use AllCoach\Domain\Program\ProgramDuration;
use AllCoach\Domain\Program\ProgramRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class CreateProgramService {

	use Hooks;

	public function __construct(
		private readonly ProgramRepository $repository,
		private readonly Logger $logger,
		private readonly EventDispatcher $events
	) {
	}

	public function execute( ProgramDTO $dto ): Program {
		$duration = new ProgramDuration( $dto->durationValue, $dto->durationUnit );

		$program = new Program(
			title: $dto->title,
			description: $dto->description,
			duration: $duration,
			price: $dto->priceAmount,
			maxEnrollments: $dto->maxEnrollments,
			isActive: $dto->isActive,
			coachId: $dto->coachId,
			meetingType: MeetingType::fromString( $dto->meetingType ),
			customMeetingUrl: $dto->customMeetingUrl,
			meetingLocation: $dto->meetingLocation,
		);

		$program = $this->repository->save( $program );

		$this->logger->info(
			'Program created',
			[
				'program_id' => $program->getId(),
				'title'      => $program->getTitle(),
				'coach_id'   => $program->getCoachId(),
			]
		);

		$this->events->dispatch( new ProgramCreated( $program ) );
		$this->doAction( 'allcoach:program:created', $program );

		return $program;
	}
}
