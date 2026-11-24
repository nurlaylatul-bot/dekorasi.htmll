<?php

defined( 'ABSPATH' ) || exit;

use AllCoach\Admin\AdminServiceProvider;
use AllCoach\API\ApiServiceProvider;
use AllCoach\Core\Jobs\Contracts\JobDispatcher;
use AllCoach\Core\Jobs\JobServiceProvider;
use AllCoach\Infrastructure\Jobs\ActionSchedulerDispatcher;
use AllCoach\Infrastructure\Jobs\ActionSchedulerRunner;
use AllCoach\API\Controllers\V1\ActivitiesController;
use AllCoach\API\Controllers\V1\AnalyticsController;
use AllCoach\API\Controllers\V1\ClientsController;
use AllCoach\API\Controllers\V1\CoachesController;
use AllCoach\API\Controllers\V1\ModulesController;
use AllCoach\API\Controllers\V1\LibraryActivitiesController;
use AllCoach\API\Controllers\V1\ProgramCategoriesController;
use AllCoach\API\Controllers\V1\OrdersController;
use AllCoach\API\Controllers\V1\SessionBookingsController;
use AllCoach\API\Controllers\V1\SettingsController;
use AllCoach\API\Controllers\V1\AvailabilitiesController;
use AllCoach\API\Controllers\V1\SectionsController;
use AllCoach\API\Controllers\V1\ProgramsController;
use AllCoach\Application\Activity\ActivityProgressQueryService;
use AllCoach\Application\Analytics\AnalyticsQueryService;
use AllCoach\Application\Activity\CompleteActivityService;
use AllCoach\Application\Activity\CreateActivityService;
use AllCoach\Application\Activity\CreateProgramActivityService;
use AllCoach\Application\Booking\BookSessionService;
use AllCoach\Application\Booking\CancelSessionBookingService;
use AllCoach\Application\Booking\CompleteSessionService;
use AllCoach\Application\Booking\SessionBookingQueryService;
use AllCoach\Application\Order\AbandonOrderService;
use AllCoach\Application\Order\CancelOrderService;
use AllCoach\Application\Order\ConfirmOrderPaymentService;
use AllCoach\Application\Order\CreateOrderService;
use AllCoach\Application\Order\OrderQueryService;
use AllCoach\Application\Client\ClientQueryService;
use AllCoach\Application\Client\CreateClientService;
use AllCoach\Application\Availability\CreateAvailabilityService;
use AllCoach\Application\Availability\UpdateAvailabilityService;
use AllCoach\Application\Availability\AvailabilityQueryService;
use AllCoach\Application\Availability\DeleteAvailabilityService;
use AllCoach\Application\Client\DeleteClientService;
use AllCoach\Application\Client\UpdateClientService;
use AllCoach\Application\Coach\CoachQueryService;
use AllCoach\Application\Coach\CreateCoachService;
use AllCoach\Application\Coach\UpdateCoachService;
use AllCoach\Application\Program\CreateProgramService;
use AllCoach\Application\Program\SyncProgramSessionService;
use AllCoach\Core\Cache\CacheManager;
use AllCoach\Core\Data\CurrencyData;
use AllCoach\Core\Data\TimezoneData;
use AllCoach\Infrastructure\Database\Migrator;
use AllCoach\Core\CoreServiceProvider;
use AllCoach\Core\PrivacyServiceProvider;
use AllCoach\Infrastructure\Database\ConsentRepository;
use AllCoach\Infrastructure\Privacy\ConsentPolicy;
use AllCoach\Infrastructure\Privacy\UserDataEraser;
use AllCoach\Core\Features\FeatureManager;
use AllCoach\Core\Settings\SettingsManager;
use AllCoach\Core\I18n\ActivitySchemaTranslator;
use AllCoach\Frontend\FrontendServiceProvider;
use AllCoach\Infrastructure\Persistence\ProgramRepository;
use AllCoach\Modules\ModuleLoader;
use AllCoach\Modules\ModuleRegistry;
use AllCoach\Support\AssetManager;
use AllCoach\Support\Config;
use AllCoach\Support\Logger;
use AllCoach\Support\TemplateLoader;
use AllCoach\Domain\Booking\PaymentGateway as PaymentGatewayInterface;
use AllCoach\Domain\Booking\SessionBookingRepository as SessionBookingRepositoryInterface;
use AllCoach\Domain\Enrollment\EnrollmentRepository as EnrollmentRepositoryInterface;
use AllCoach\Domain\Order\OrderItemRepository as OrderItemRepositoryInterface;
use AllCoach\Domain\Order\OrderRepository as OrderRepositoryInterface;
use AllCoach\Domain\Program\ProgramRepository as ProgramRepositoryInterface;
use AllCoach\Domain\Activity\ActivityRepository as ActivityRepositoryInterface;
use AllCoach\Domain\Client\ClientRepository as ClientRepositoryInterface;
use AllCoach\Domain\Coach\CoachRepository as CoachRepositoryInterface;
use AllCoach\Domain\Activity\ActivityCompletionRepository as ActivityCompletionRepositoryInterface;
use AllCoach\Domain\Activity\ActivityTemplateRepository as ActivityTemplateRepositoryInterface;
use AllCoach\Domain\Availability\AvailabilityRepository as AvailabilityRepositoryInterface;
use AllCoach\Domain\Section\SectionRepository as SectionRepositoryInterface;
use AllCoach\Domain\Session\SessionRepository as SessionRepositoryInterface;
use AllCoach\Infrastructure\Database\ActivityCompletionRepository;
use AllCoach\Infrastructure\Database\EnrollmentRepository;
use AllCoach\Infrastructure\Database\OrderItemRepository;
use AllCoach\Infrastructure\Database\OrderRepository;
use AllCoach\Infrastructure\Database\SessionBookingRepository;
use AllCoach\Infrastructure\Payment\OfflinePaymentGateway;
use AllCoach\Modules\PayPal\PayPalPaymentGateway;
use AllCoach\Infrastructure\Payment\PaymentGatewayRegistry;
use AllCoach\Infrastructure\Persistence\ActivityRepository;
use AllCoach\Infrastructure\Persistence\ActivityTemplateRepository;
use AllCoach\Infrastructure\Persistence\ClientRepository;
use AllCoach\Infrastructure\Persistence\CoachRepository;
use AllCoach\Infrastructure\Persistence\AvailabilityRepository;
use AllCoach\Infrastructure\Persistence\SectionRepository;
use AllCoach\Infrastructure\Persistence\SessionRepository;
use AllCoach\Infrastructure\Mail\Mailer;
use AllCoach\Infrastructure\Mail\NotificationServiceProvider;
use AllCoach\Infrastructure\Mail\SendNotificationJob;
use AllCoach\Infrastructure\Mail\Notifications\CancellationNoticeNotification;
use AllCoach\Infrastructure\Mail\Notifications\NewBookingAdminNotification;
use AllCoach\Infrastructure\Mail\Notifications\NewBookingClientNotification;
use AllCoach\Infrastructure\Mail\Notifications\OrderConfirmedNotification;
use AllCoach\Infrastructure\Mail\Notifications\OrderCancelledNotification;
use AllCoach\Infrastructure\Mail\Notifications\WelcomeClientNotification;
use AllCoach\Infrastructure\Mail\Notifications\SessionReminder24hNotification;
use AllCoach\Infrastructure\Google\GoogleTokenManager;
use AllCoach\Infrastructure\Google\GoogleCredentialManager;
use AllCoach\Infrastructure\Google\GoogleOAuthClient;
use AllCoach\Infrastructure\Google\GoogleCalendarService;
use AllCoach\Infrastructure\Google\GoogleIntegrationProvider;
use AllCoach\Infrastructure\Google\SyncCalendarEventJob;
use AllCoach\API\Controllers\V1\GoogleAuthController;
use AllCoach\API\Controllers\V1\AuthController;
use AllCoach\API\Controllers\V1\ClientBookingController;
use AllCoach\API\Controllers\V1\ClientProfileController;
use AllCoach\Application\Auth\LoginService;
use AllCoach\Application\Auth\LostPasswordService;
use AllCoach\Application\Auth\ResetPasswordService;

use AllCoach\Infrastructure\Cart\CartManager;
use AllCoach\API\Controllers\V1\CartController;
use AllCoach\Modules\PayPal\PayPalOrderController;
use AllCoach\Modules\PayPal\PayPalWebhookController;

use AllCoach\Frontend\AccountPages;
use AllCoach\Frontend\Router as FrontendRouter;
use AllCoach\Frontend\Controllers\DashboardController;
use AllCoach\Frontend\Controllers\ProgramController as FrontendProgramController;
use AllCoach\Frontend\Controllers\ActivityController as FrontendActivityController;
use AllCoach\Frontend\Controllers\BookingController as FrontendBookingController;
use AllCoach\Frontend\Controllers\ProfileController as FrontendProfileController;
use AllCoach\Frontend\Controllers\AuthController as FrontendAuthController;

use AllCoach\Core\Events\EventDispatcher;
use AllCoach\Core\Events\EventHookBridge;

use function DI\{autowire, create, factory, get};

return [
	// -------------------------------------------------------------------------
	// Configuration
	// -------------------------------------------------------------------------
	Config::class                                => create( Config::class )->constructor( get( 'config.app' ) ),

	// -------------------------------------------------------------------------
	// Core Services
	// -------------------------------------------------------------------------
	FeatureManager::class                        => create( FeatureManager::class ),
	SettingsManager::class                       => create( SettingsManager::class ),
	Migrator::class                              => create( Migrator::class ),
	CacheManager::class                          => create( CacheManager::class ),
	CurrencyData::class                          => autowire(),
	TimezoneData::class                          => autowire(),

	// -------------------------------------------------------------------------
	// Support Services
	// -------------------------------------------------------------------------
	TemplateLoader::class                        => autowire(),
	AssetManager::class                          => autowire(),
	Logger::class                                => autowire(),

	// -------------------------------------------------------------------------
	// Repository Bindings (Interface → Implementation)
	// -------------------------------------------------------------------------
	ProgramRepositoryInterface::class            => autowire( ProgramRepository::class ),
	ActivityTemplateRepositoryInterface::class   => autowire( ActivityTemplateRepository::class ),
	ActivityRepositoryInterface::class           => autowire( ActivityRepository::class ),
	ActivityCompletionRepositoryInterface::class => autowire( ActivityCompletionRepository::class ),
	AvailabilityRepositoryInterface::class       => autowire( AvailabilityRepository::class ),
	SectionRepositoryInterface::class            => autowire( SectionRepository::class ),
	SessionRepositoryInterface::class            => autowire( SessionRepository::class ),
	CoachRepositoryInterface::class              => autowire( CoachRepository::class ),
	ClientRepositoryInterface::class             => autowire( ClientRepository::class ),
	OrderRepositoryInterface::class              => autowire( OrderRepository::class ),
	OrderItemRepositoryInterface::class          => autowire( OrderItemRepository::class ),
	EnrollmentRepositoryInterface::class         => autowire( EnrollmentRepository::class ),
	SessionBookingRepositoryInterface::class     => autowire( SessionBookingRepository::class ),
	OfflinePaymentGateway::class                 => autowire(),
	PayPalPaymentGateway::class                  => autowire(),
	PaymentGatewayInterface::class               => factory(
		function ( \DI\Container $c ) {
			return new PaymentGatewayRegistry(
				[
					$c->get( OfflinePaymentGateway::class ),
					$c->get( PayPalPaymentGateway::class ),
				]
			);
		}
	),
	ProgramCategoriesController::class           => autowire(),
	SettingsController::class                    => autowire(),
	ModulesController::class                     => autowire(),

	// -------------------------------------------------------------------------
	// Application Services
	// -------------------------------------------------------------------------
	CreateProgramService::class                  => autowire(),
	SyncProgramSessionService::class             => autowire(),
	ClientQueryService::class                    => autowire(),
	CreateClientService::class                   => autowire(),
	UpdateClientService::class                   => autowire(),
	CoachQueryService::class                     => autowire(),
	CreateCoachService::class                    => autowire(),
	UpdateCoachService::class                    => autowire(),
	CreateActivityService::class                 => autowire(),
	CreateProgramActivityService::class          => autowire(),
	CompleteActivityService::class               => autowire(),
	ActivityProgressQueryService::class          => autowire(),
	CreateOrderService::class                    => autowire(),
	OrderQueryService::class                     => autowire(),
	ConfirmOrderPaymentService::class            => autowire(),
	CancelOrderService::class                    => autowire(),
	AbandonOrderService::class                   => autowire(),
	BookSessionService::class                    => autowire(),
	CompleteSessionService::class                => autowire(),
	CancelSessionBookingService::class           => autowire(),
	SessionBookingQueryService::class            => autowire(),
	DeleteClientService::class                   => autowire(),
	CreateAvailabilityService::class             => autowire(),
	UpdateAvailabilityService::class             => autowire(),
	AvailabilityQueryService::class              => autowire(),
	DeleteAvailabilityService::class             => autowire(),
	LoginService::class                          => autowire(),
	LostPasswordService::class                   => autowire(),
	ResetPasswordService::class                  => autowire(),

	// -------------------------------------------------------------------------
	// i18n
	// -------------------------------------------------------------------------
	ActivitySchemaTranslator::class              => autowire(),

	// -------------------------------------------------------------------------
	// API Controllers
	// -------------------------------------------------------------------------
	AnalyticsQueryService::class                 => autowire(),
	AnalyticsController::class                   => autowire(),
	ProgramsController::class                    => autowire(),
	ClientsController::class                     => autowire(),
	CoachesController::class                     => autowire(),
	LibraryActivitiesController::class           => autowire(),
	ActivitiesController::class                  => autowire(),
	AvailabilitiesController::class              => autowire(),
	OrdersController::class                      => autowire(),
	SessionBookingsController::class             => autowire(),
	SectionsController::class                    => autowire(),

	// -------------------------------------------------------------------------
	// Jobs / Background Processing
	// -------------------------------------------------------------------------
	JobDispatcher::class                         => autowire( ActionSchedulerDispatcher::class ),
	ActionSchedulerDispatcher::class             => autowire(),
	ActionSchedulerRunner::class                 => autowire(),
	JobServiceProvider::class                    => autowire(),

	// -------------------------------------------------------------------------
	// Mail / Notifications
	// -------------------------------------------------------------------------
	Mailer::class                                => autowire(),
	NewBookingClientNotification::class          => autowire(),
	NewBookingAdminNotification::class           => autowire(),
	CancellationNoticeNotification::class        => autowire(),
	SessionReminder24hNotification::class        => autowire(),
	SendNotificationJob::class                   => autowire(),
	NotificationServiceProvider::class           => autowire(),
	OrderConfirmedNotification::class            => autowire(),
	OrderCancelledNotification::class            => autowire(),
	WelcomeClientNotification::class             => autowire(),

	// -------------------------------------------------------------------------
	// Google Integration
	// -------------------------------------------------------------------------
	GoogleTokenManager::class                    => create( GoogleTokenManager::class ),
	GoogleCredentialManager::class               => create( GoogleCredentialManager::class ),
	GoogleOAuthClient::class                     => autowire(),
	GoogleCalendarService::class                 => autowire(),
	SyncCalendarEventJob::class                  => autowire(),
	GoogleIntegrationProvider::class             => autowire(),
	GoogleAuthController::class                  => autowire(),
	AuthController::class                        => autowire(),
	ClientBookingController::class               => autowire(),
	ClientProfileController::class               => autowire(),

	// -------------------------------------------------------------------------
	// Cart
	// -------------------------------------------------------------------------
	CartManager::class                           => autowire(),
	CartController::class                        => autowire(),
	PayPalWebhookController::class               => autowire(),
	PayPalOrderController::class                 => autowire(),

	// -------------------------------------------------------------------------
	// Frontend
	// -------------------------------------------------------------------------
	AccountPages::class                          => autowire(),
	FrontendRouter::class                        => autowire(),
	DashboardController::class                   => autowire(),
	FrontendProgramController::class             => autowire(),
	FrontendActivityController::class            => autowire(),
	FrontendBookingController::class             => autowire(),
	FrontendProfileController::class             => autowire(),
	FrontendAuthController::class                => autowire(),

	// -------------------------------------------------------------------------
	// Service Providers
	// -------------------------------------------------------------------------
	AdminServiceProvider::class                  => autowire(),
	FrontendServiceProvider::class               => autowire(),
	ApiServiceProvider::class                    => autowire(),
	CoreServiceProvider::class                   => autowire(),
	PrivacyServiceProvider::class                => autowire(),

	// -------------------------------------------------------------------------
	// Privacy / GDPR
	// -------------------------------------------------------------------------
	ConsentRepository::class                     => autowire(),
	ConsentPolicy::class                         => autowire(),
	UserDataEraser::class                        => autowire(),

	// -------------------------------------------------------------------------
	// Module Loader / Registry
	// -------------------------------------------------------------:------------
	ModuleLoader::class                          => autowire(),
	ModuleRegistry::class                        => factory( fn() => ModuleRegistry::getInstance() ),

	// -------------------------------------------------------------------------
	// Events
	// -------------------------------------------------------------------------
	EventDispatcher::class                       => autowire(),
	EventHookBridge::class                       => autowire(),
];
