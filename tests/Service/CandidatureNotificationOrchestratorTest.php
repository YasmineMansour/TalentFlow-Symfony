<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Service\BrevoEmailService;
use App\Service\BrevoSmsService;
use App\Service\CandidatureCompletenessService;
use App\Service\CandidatureNotificationOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CandidatureNotificationOrchestratorTest extends TestCase
{
    private CandidatureCompletenessService $completenessService;
    private BrevoEmailService&MockObject $emailService;
    private BrevoSmsService&MockObject $smsService;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private CandidatureNotificationOrchestrator $service;

    protected function setUp(): void
    {
        $this->completenessService = new CandidatureCompletenessService();
        $this->emailService = $this->createMock(BrevoEmailService::class);
        $this->smsService = $this->createMock(BrevoSmsService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CandidatureNotificationOrchestrator(
            $this->completenessService,
            $this->emailService,
            $this->smsService,
            $this->em,
            $this->logger,
        );
    }

    public function testCompleteCandidatureTriggersEmailOnly(): void
    {
        $candidature = $this->makeCompleteCandidature('candidate@test.tn', '22334455');

        $this->emailService->expects($this->once())
            ->method('sendCandidatureConfirmation')
            ->with($candidature)
            ->willReturn(true);

        $this->smsService->expects($this->never())
            ->method('sendBlockingCandidatureReminder');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->handlePostSubmissionNotifications($candidature);

        $this->assertSame('email', $result['channel']);
        $this->assertTrue($result['success']);
        $this->assertSame('complete_candidature', $result['reason']);
        $this->assertNotNull($candidature->getConfirmationEmailSentAt());
        $this->assertNull($candidature->getBlockingSmsSentAt());
    }

    public function testBlockingCandidatureTriggersSmsOnly(): void
    {
        $candidature = $this->makeBlockingCandidature('candidate@test.tn', '22334455');

        $this->smsService->expects($this->once())
            ->method('sendBlockingCandidatureReminder')
            ->with($candidature)
            ->willReturn(true);

        $this->emailService->expects($this->never())
            ->method('sendCandidatureConfirmation');

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->handlePostSubmissionNotifications($candidature);

        $this->assertSame('sms', $result['channel']);
        $this->assertTrue($result['success']);
        $this->assertSame('blocking_candidature', $result['reason']);
        $this->assertNotNull($candidature->getBlockingSmsSentAt());
    }

    public function testMissingEmailRoutesToBlockingSmsFlowSafely(): void
    {
        $candidature = $this->makeCompleteCandidature(null, '22334455');

        $this->smsService->expects($this->once())
            ->method('sendBlockingCandidatureReminder')
            ->willReturn(false);

        $this->emailService->expects($this->never())->method('sendCandidatureConfirmation');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->handlePostSubmissionNotifications($candidature);

        $this->assertSame('sms', $result['channel']);
        $this->assertFalse($result['success']);
        $this->assertSame('sms_send_failed', $result['reason']);
    }

    public function testMissingPhoneOnBlockingFlowReturnsSmsFailureSafely(): void
    {
        $candidature = $this->makeBlockingCandidature('candidate@test.tn', null);

        $this->smsService->expects($this->once())
            ->method('sendBlockingCandidatureReminder')
            ->willReturn(false);

        $this->emailService->expects($this->never())->method('sendCandidatureConfirmation');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->handlePostSubmissionNotifications($candidature);

        $this->assertSame('sms', $result['channel']);
        $this->assertFalse($result['success']);
        $this->assertSame('sms_send_failed', $result['reason']);
    }

    public function testAlreadySentEmailPreventsDuplicateSend(): void
    {
        $candidature = $this->makeCompleteCandidature('candidate@test.tn', '22334455');
        $candidature->setConfirmationEmailSentAt(new \DateTimeImmutable('-1 day'));

        $this->emailService->expects($this->never())->method('sendCandidatureConfirmation');
        $this->smsService->expects($this->never())->method('sendBlockingCandidatureReminder');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->handlePostSubmissionNotifications($candidature);

        $this->assertSame('email', $result['channel']);
        $this->assertFalse($result['success']);
        $this->assertSame('already_sent', $result['reason']);
    }

    public function testAlreadySentSmsPreventsDuplicateSend(): void
    {
        $candidature = $this->makeBlockingCandidature('candidate@test.tn', '22334455');
        $candidature->setBlockingSmsSentAt(new \DateTimeImmutable('-1 day'));

        $this->smsService->expects($this->never())->method('sendBlockingCandidatureReminder');
        $this->emailService->expects($this->never())->method('sendCandidatureConfirmation');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->handlePostSubmissionNotifications($candidature);

        $this->assertSame('sms', $result['channel']);
        $this->assertFalse($result['success']);
        $this->assertSame('already_sent', $result['reason']);
    }

    public function testEmailServiceExceptionBubblesUp(): void
    {
        $candidature = $this->makeCompleteCandidature('candidate@test.tn', '22334455');

        $this->emailService->method('sendCandidatureConfirmation')
            ->willThrowException(new \RuntimeException('email provider down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('email provider down');

        $this->service->handlePostSubmissionNotifications($candidature);
    }

    private function makeCompleteCandidature(?string $email, ?string $phone): Candidature
    {
        $c = new Candidature();
        $c->setTitrePoste('Développeur Symfony');
        $c->setEntreprise('TalentFlow');
        $c->setTypeContrat('CDI');
        $c->setStatut('En attente');
        $c->setEmail($email);
        $c->setTelephone($phone);
        $c->setCompetences('PHP, Symfony');
        $c->setNiveauEtudes('Bac+5');
        $c->setAnneesExperience(5);
        $c->setSalaireSouhaite('3000.00');
        $c->setCvFilename('cv.pdf');
        $c->setLettreMotivationFilename('lettre.pdf');

        return $c;
    }

    private function makeBlockingCandidature(?string $email, ?string $phone): Candidature
    {
        $c = new Candidature();
        $c->setTitrePoste('Développeur Symfony');
        $c->setEntreprise('TalentFlow');
        $c->setTypeContrat('CDI');
        $c->setStatut('En attente');
        $c->setEmail($email);
        $c->setTelephone($phone);
        $c->setCompetences(null);
        $c->setNiveauEtudes(null);
        $c->setAnneesExperience(null);
        $c->setCvFilename(null);
        $c->setLettreMotivationFilename(null);

        return $c;
    }
}
