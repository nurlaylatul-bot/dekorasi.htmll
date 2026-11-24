<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\Exceptions\NotFoundException;
use AllCoach\Core\Exceptions\ValidationException;
use AllCoach\Core\I18n\ActivitySchemaTranslator;
use AllCoach\Domain\Activity\Activity;
use AllCoach\Domain\Activity\ActivityRepository;
use AllCoach\Domain\Activity\ActivitySchema;
use AllCoach\Domain\Activity\ActivityTemplateRepository;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class CreateProgramActivityService {

	use Hooks;

	public function __construct(
		private readonly ActivityRepository $activityRepo,
		private readonly ActivityTemplateRepository $libraryRepo,
		private readonly ActivitySchemaTranslator $translator,
		private readonly Logger $logger,
	) {}

	public function execute( ProgramActivityDTO $dto ): Activity {
		$schema = $this->resolveSchema( $dto );

		$activity = new Activity(
			programId:  $dto->programId,
			schema:     $schema,
			settings:   $dto->settings,
			day:        $dto->day,
			week:       $dto->week,
			position:   $dto->position,
			sectionId:  $dto->sectionId,
			templateId: $dto->templateId,
		);

		$activity = $this->activityRepo->save( $activity );

		if ( $dto->templateId === null ) {
			$this->translator->registerStrings( (int) $activity->getId(), $schema );
		}

		$this->logger->info(
			'Program activity created',
			[
				'activity_id' => $activity->getId(),
				'program_id'  => $dto->programId,
				'template_id' => $dto->templateId,
				'week'        => $dto->week,
				'day'         => $dto->day,
			]
		);

		$this->doAction( 'allcoach:program_activity:created', $activity );

		return $activity;
	}

	private function resolveSchema( ProgramActivityDTO $dto ): ActivitySchema {
		if ( $dto->templateId !== null ) {
			$template = $this->libraryRepo->findById( $dto->templateId );

			if ( $template === null ) {
				throw NotFoundException::entity( esc_html__( 'Activity template', 'allcoach' ), (int) $dto->templateId );
			}

			return $template->getSchema();
		}

		if ( $dto->schemaJson === '' ) {
			throw ValidationException::withErrors(
				[
					'schemaJson' => esc_html__( 'A custom program activity requires a schemaJson when no templateId is provided.', 'allcoach' ),
				]
			);
		}

		return ActivitySchema::fromJson( $dto->schemaJson );
	}
}
