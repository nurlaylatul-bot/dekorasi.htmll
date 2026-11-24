<?php

declare(strict_types=1);

namespace AllCoach\Core;

defined( 'ABSPATH' ) || exit;

use AllCoach\Infrastructure\Database\Migrator;
use AllCoach\Domain\Session\SessionRepository;
use AllCoach\Infrastructure\PostTypes\Activity;
use AllCoach\Infrastructure\PostTypes\ActivityType;
use AllCoach\Infrastructure\PostTypes\Availability;
use AllCoach\Infrastructure\PostTypes\Program;
use AllCoach\Infrastructure\PostTypes\Section;
use AllCoach\Infrastructure\PostTypes\Session as SessionPostType;
use AllCoach\Infrastructure\Taxonomies\ActivityGroup;
use AllCoach\Infrastructure\Taxonomies\ProgramCategory;
use AllCoach\Core\Contracts\ServiceProviderInterface;
use AllCoach\Infrastructure\Seeders\SampleDataSeeder;
use AllCoach\Traits\Hooks;

class CoreServiceProvider implements ServiceProviderInterface {

	use Hooks;

	public function __construct(
		private RoleManager $roleManager,
		private SessionRepository $sessionRepository,
		private Migrator $migrator,
	) {}

	public function boot(): void {
		$this->addAction( 'init', [ $this, 'registerPostTypes' ] );
		$this->addAction( 'init', [ $this, 'registerTaxonomies' ] );
		$this->addAction( 'before_delete_post', [ $this, 'onBeforeDeletePost' ], 10, 2 );
		$this->addAction( 'allcoach:activated', [ $this, 'onActivation' ] );
		$this->addAction( 'admin_init', [ $this, 'runPendingMigrations' ] );
		$this->addAction( 'admin_init', [ $this, 'maybeSeedSampleData' ] );
	}

	public function runPendingMigrations(): void {
		if ( $this->migrator->hasPending() ) {
			$this->migrator->run();
		}
	}

	public function registerPostTypes(): void {
		( new Program() )->register();
		( new Section() )->register();
		( new Activity() )->register();
		( new ActivityType() )->register();
		( new Availability() )->register();
		( new SessionPostType() )->register();
	}

	public function onBeforeDeletePost( int $postId, \WP_Post $post ): void {
		if ( $post->post_type === 'allcoach_program' ) {
			$this->sessionRepository->deleteByProgramId( $postId );
		}
	}

	public function registerTaxonomies(): void {
		( new ProgramCategory() )->register();
		( new ActivityGroup() )->register();
	}

	public function maybeSeedSampleData(): void {
		( new SampleDataSeeder() )->maybeRun();
	}

	public function onActivation(): void {
		$this->migrator->run();
		$this->roleManager->createRoles();

		$this->doAction( 'allcoach:activated:complete' );
	}
}
