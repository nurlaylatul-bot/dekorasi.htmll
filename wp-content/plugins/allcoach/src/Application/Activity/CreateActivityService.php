<?php

declare(strict_types=1);

namespace AllCoach\Application\Activity;

defined( 'ABSPATH' ) || exit;

use AllCoach\Core\I18n\ActivitySchemaTranslator;
use AllCoach\Domain\Activity\ActivityTemplate;
use AllCoach\Domain\Activity\ActivityTemplateRepository;
use AllCoach\Domain\Activity\ActivitySchema;
use AllCoach\Support\Logger;
use AllCoach\Traits\Hooks;

class CreateActivityService {

	use Hooks;

	public function __construct(
		private readonly ActivityTemplateRepository $repository,
		private readonly ActivitySchemaTranslator $translator,
		private readonly Logger $logger,
	) {}

	public function execute( ActivityDTO $dto ): ActivityTemplate {
		$schema = $dto->schemaJson !== ''
			? ActivitySchema::fromJson( $dto->schemaJson )
			: ActivitySchema::empty();

		$library = new ActivityTemplate(
			title:       $dto->title,
			description: $dto->description,
			schema:      $schema,
			coachId:     $dto->coachId,
			isPublished: $dto->isPublished,
		);

		$library = $this->repository->save( $library );

		if ( ! $schema->isEmpty() ) {
			$this->translator->registerStrings( (int) $library->getId(), $schema );
		}

		$this->logger->info(
			'Activity library item created',
			[
				'activity_id' => $library->getId(),
				'coach_id'    => $dto->coachId,
			]
		);

		$this->doAction( 'allcoach:activity-library:created', $library );

		return $library;
	}
}
