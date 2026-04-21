<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates post-submission notifications for candidatures.
 *
 * Decision rules:
 *  - BLOQUANT level  → send a blocking-reminder SMS (if phone present, not already sent)
 *  - COMPLET / A_COMPLETER → send a confirmation email (if email present, not already sent)
 */
class CandidatureNotificationOrchestrator
{
    public function __construct(
        private readonly CandidatureCompletenessService $completenessService,
        private readonly BrevoEmailService $brevoEmailService,
        private readonly BrevoSmsService $brevoSmsService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Decides and dispatches the right notification channel after a candidature is submitted.
     *
     * @return array{channel: string, success: bool, reason: string}
     */
    public function handlePostSubmissionNotifications(Candidature $candidature): array
    {
        $analysis = $this->completenessService->analyze($candidature);
        $level    = $analysis['level']; // 'COMPLET' | 'A_COMPLETER' | 'BLOQUANT'

        if ($level === 'BLOQUANT') {
            return $this->handleBlockingSms($candidature);
        }

        return $this->handleConfirmationEmail($candidature);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function handleConfirmationEmail(Candidature $candidature): array
    {
        if ($candidature->getConfirmationEmailSentAt() !== null) {
            $this->logger->info('Candidature confirmation email skipped: already sent.', [
                'candidature_id' => $candidature->getId(),
            ]);
            return ['channel' => 'email', 'success' => false, 'reason' => 'already_sent'];
        }

        $sent = $this->brevoEmailService->sendCandidatureConfirmation($candidature);

        if ($sent) {
            $candidature->setConfirmationEmailSentAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        return [
            'channel' => 'email',
            'success' => $sent,
            'reason'  => $sent ? 'complete_candidature' : 'email_send_failed',
        ];
    }

    private function handleBlockingSms(Candidature $candidature): array
    {
        if ($candidature->getBlockingSmsSentAt() !== null) {
            $this->logger->info('Blocking candidature SMS skipped: already sent.', [
                'candidature_id' => $candidature->getId(),
            ]);
            return ['channel' => 'sms', 'success' => false, 'reason' => 'already_sent'];
        }

        $sent = $this->brevoSmsService->sendBlockingCandidatureReminder($candidature);

        if ($sent) {
            $candidature->setBlockingSmsSentAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        return [
            'channel' => 'sms',
            'success' => $sent,
            'reason'  => $sent ? 'blocking_candidature' : 'sms_send_failed',
        ];
    }
}
