<?php

declare(strict_types=1);

namespace AllCoach\Application\Program;

defined( 'ABSPATH' ) || exit;

use AllCoach\Domain\Session\Session;
use AllCoach\Domain\Session\SessionRepository;

/**
 * Creates, updates, or removes the single session for a program based on request payload.
 */
class SyncProgramSessionService {

	public function __construct(
		private readonly SessionRepository $sessionRepository,
	) {}

	/**
	 * Sync the program's session from payload. No-op if no session-related keys are present.
	 *
	 * @param array $payload Request body; may contain:
	 *   - remove_session (bool): if true, delete the program's session
	 *   - session_duration_minutes (int): duration per occurrence in minutes
	 *   - session_repetition_count (int): number of times the session repeats (count)
	 */
	public function sync( int $programId, array $payload ): void {
		if ( ! empty( $payload['remove_session'] ) ) {
			$this->sessionRepository->deleteByProgramId( $programId );
			return;
		}

		$hasSessionData = array_key_exists( 'session_duration_minutes', $payload )
			|| array_key_exists( 'session_repetition_count', $payload );

		if ( ! $hasSessionData ) {
			return;
		}

		$session = $this->sessionRepository->findOneByProgramId( $programId );

		if ( $session !== null ) {
			$this->updateExistingSession( $session, $payload );
		} else {
			$this->createSession( $programId, $payload );
		}
	}

	private function updateExistingSession( Session $session, array $payload ): void {
		if ( array_key_exists( 'session_duration_minutes', $payload ) ) {
			$session->setDurationMinutes( max( 0, (int) $payload['session_duration_minutes'] ) );
		}
		if ( array_key_exists( 'session_repetition_count', $payload ) ) {
			$session->setRepetitionCount( max( 1, (int) $payload['session_repetition_count'] ) );
		}
		$this->sessionRepository->save( $session );
	}

	private function createSession( int $programId, array $payload ): void {
		$minutes    = array_key_exists( 'session_duration_minutes', $payload )
			? max( 0, (int) $payload['session_duration_minutes'] )
			: 0;
		$repetition = array_key_exists( 'session_repetition_count', $payload )
			? max( 1, (int) $payload['session_repetition_count'] )
			: 1;

		$session = new Session(
			programId:       $programId,
			durationMinutes: $minutes,
			repetitionCount: $repetition,
			position:        0,
		);
		$this->sessionRepository->save( $session );
	}
}
