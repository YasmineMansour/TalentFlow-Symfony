<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\Offre;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\CandidatureStatusHistoryRepository;
use App\Service\CandidatureDuplicateGuardService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CandidatureDuplicateGuardServiceTest extends TestCase
{
    private CandidatureRepository&MockObject $candidatureRepository;
    private CandidatureStatusHistoryRepository&MockObject $historyRepository;
    private CandidatureDuplicateGuardService $service;

    protected function setUp(): void
    {
        $this->candidatureRepository = $this->createMock(CandidatureRepository::class);
        $this->historyRepository = $this->createMock(CandidatureStatusHistoryRepository::class);
        $this->service = new CandidatureDuplicateGuardService($this->candidatureRepository, $this->historyRepository);
    }

    public function testSameCandidateSameOfferActiveIsBlocking(): void
    {
        $offre = $this->makeOffre(10);
        $candidate = $this->makeUser(5, 'a@test.tn');

        $current = $this->makeCandidature($offre, $candidate, 'a@test.tn', 'En attente');
        $existing = $this->makeCandidature($offre, $candidate, 'a@test.tn', 'Validée RH');

        $this->candidatureRepository
            ->method('findPotentialDuplicatesForCandidature')
            ->with($current, null)
            ->willReturn([$existing]);

        $analysis = $this->service->analyze($current);

        $this->assertTrue($analysis['isDuplicate']);
        $this->assertSame('BLOCKING', $analysis['severity']);
        $this->assertFalse($analysis['canSubmit']);
        $this->assertSame('candidate_offer', $analysis['matchingStrategy']);
    }

    public function testSameCandidateSameOfferAcceptedIsBlocking(): void
    {
        $offre = $this->makeOffre(11);
        $candidate = $this->makeUser(6, 'b@test.tn');

        $current = $this->makeCandidature($offre, $candidate, 'b@test.tn', 'En attente');
        $existing = $this->makeCandidature($offre, $candidate, 'b@test.tn', 'Acceptée');

        $this->candidatureRepository->method('findPotentialDuplicatesForCandidature')->willReturn([$existing]);

        $analysis = $this->service->analyze($current);

        $this->assertSame('BLOCKING', $analysis['severity']);
        $this->assertFalse($analysis['canSubmit']);
    }

    public function testSameCandidateDifferentOfferIsNotDuplicate(): void
    {
        $candidate = $this->makeUser(61, 'different-offer@test.tn');
        $offreCourante = $this->makeOffre(110);
        $offreExistante = $this->makeOffre(111);

        $current = $this->makeCandidature($offreCourante, $candidate, 'different-offer@test.tn', 'En attente');
        $existing = $this->makeCandidature($offreExistante, $candidate, 'different-offer@test.tn', 'En attente');

        $this->candidatureRepository
            ->method('findPotentialDuplicatesForCandidature')
            ->with($current, null)
            ->willReturn([$existing]);

        $analysis = $this->service->analyze($current);

        $this->assertFalse($analysis['isDuplicate']);
        $this->assertSame('NONE', $analysis['severity']);
        $this->assertTrue($analysis['canSubmit']);
    }

    public function testSameCandidateSameOfferRefusedOlderThan30DaysIsWarning(): void
    {
        $offre = $this->makeOffre(12);
        $candidate = $this->makeUser(7, 'c@test.tn');

        $current = $this->makeCandidature($offre, $candidate, 'c@test.tn', 'En attente');
        $existing = $this->makeCandidature($offre, $candidate, 'c@test.tn', 'Refusée');

        $this->candidatureRepository->method('findPotentialDuplicatesForCandidature')->willReturn([$existing]);
        $this->historyRepository
            ->method('findLatestRefusedAt')
            ->with($existing)
            ->willReturn((new \DateTimeImmutable())->modify('-40 days'));

        $analysis = $this->service->analyze($current);

        $this->assertTrue($analysis['isDuplicate']);
        $this->assertSame('WARNING', $analysis['severity']);
        $this->assertTrue($analysis['canSubmit']);
    }

    public function testSameCandidateSameOfferRefusedLessThan30DaysIsBlocking(): void
    {
        $offre = $this->makeOffre(13);
        $candidate = $this->makeUser(8, 'd@test.tn');

        $current = $this->makeCandidature($offre, $candidate, 'd@test.tn', 'En attente');
        $existing = $this->makeCandidature($offre, $candidate, 'd@test.tn', 'Refusée');

        $this->candidatureRepository->method('findPotentialDuplicatesForCandidature')->willReturn([$existing]);
        $this->historyRepository
            ->method('findLatestRefusedAt')
            ->with($existing)
            ->willReturn((new \DateTimeImmutable())->modify('-10 days'));

        $analysis = $this->service->analyze($current);

        $this->assertSame('BLOCKING', $analysis['severity']);
        $this->assertFalse($analysis['canSubmit']);
    }

    public function testNoCandidateRelationSameOfferSameEmailUsesEmailOfferFallback(): void
    {
        $offre = $this->makeOffre(14);

        $current = $this->makeCandidature($offre, null, 'same@email.tn', 'En attente');
        $existing = $this->makeCandidature($offre, null, 'same@email.tn', 'En attente');

        $this->candidatureRepository->method('findPotentialDuplicatesForCandidature')->willReturn([$existing]);

        $analysis = $this->service->analyze($current);

        $this->assertTrue($analysis['isDuplicate']);
        $this->assertSame('email_offer', $analysis['matchingStrategy']);
    }

    public function testEditModeExclusionDoesNotDetectItself(): void
    {
        $offre = $this->makeOffre(15);
        $candidate = $this->makeUser(9, 'z@test.tn');
        $current = $this->makeCandidature($offre, $candidate, 'z@test.tn', 'En attente');

        $this->candidatureRepository
            ->expects($this->once())
            ->method('findPotentialDuplicatesForCandidature')
            ->with($current, 123)
            ->willReturn([]);

        $analysis = $this->service->analyze($current, 123);

        $this->assertFalse($analysis['isDuplicate']);
        $this->assertSame('NONE', $analysis['severity']);
    }

    public function testFallbackTextOnlyDetectionIsWarning(): void
    {
        $current = $this->makeCandidature(null, null, ' test@x.tn ', 'En attente', 'Developpeur Symfony', 'Tech Corp');
        $existing = $this->makeCandidature(null, null, 'test@x.tn', 'Refusée', 'developpeur   symfony', 'tech corp');

        $this->candidatureRepository->method('findPotentialDuplicatesForCandidature')->willReturn([$existing]);
        $this->historyRepository->method('findLatestRefusedAt')->willReturn((new \DateTimeImmutable())->modify('-60 days'));

        $analysis = $this->service->analyze($current);

        $this->assertTrue($analysis['isDuplicate']);
        $this->assertSame('fallback_text', $analysis['matchingStrategy']);
        $this->assertSame('WARNING', $analysis['severity']);
        $this->assertTrue($analysis['canSubmit']);
    }

    public function testFindDuplicatesReturnsExpectedDisplayStructure(): void
    {
        $offre = $this->makeOffre(200);
        $candidate = $this->makeUser(201, 'dup@test.tn');

        $current = $this->makeCandidature($offre, $candidate, 'dup@test.tn', 'En attente', 'Dev PHP', 'Tech Corp');
        $existing = $this->makeCandidature($offre, $candidate, 'dup@test.tn', 'Validée RH', 'Dev PHP', 'Tech Corp');
        $this->setEntityId($existing, 202);

        $this->candidatureRepository
            ->method('findPotentialDuplicatesForCandidature')
            ->with($current, null)
            ->willReturn([$existing]);

        $rows = $this->service->findDuplicates($current);

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('statut', $rows[0]);
        $this->assertArrayHasKey('dateCandidature', $rows[0]);
        $this->assertArrayHasKey('offreLabel', $rows[0]);
        $this->assertArrayHasKey('candidateLabel', $rows[0]);
        $this->assertSame(202, $rows[0]['id']);
        $this->assertSame('Validée RH', $rows[0]['statut']);
        $this->assertSame('Offre 200', $rows[0]['offreLabel']);
        $this->assertSame('User TEST', $rows[0]['candidateLabel']);
    }

    private function makeCandidature(?Offre $offre, ?User $candidat, string $email, string $statut, string $titre = 'Poste', string $entreprise = 'Entreprise'): Candidature
    {
        $c = new Candidature();
        $c->setOffre($offre);
        $c->setCandidat($candidat);
        $c->setEmail($email);
        $c->setStatut($statut);
        $c->setTitrePoste($titre);
        $c->setEntreprise($entreprise);
        $c->setTypeContrat('CDI');

        return $c;
    }

    private function makeOffre(int $id): Offre
    {
        $offre = new Offre();
        $offre->setTitre('Offre ' . $id);
        $this->setEntityId($offre, $id);

        return $offre;
    }

    private function makeUser(int $id, string $email): User
    {
        $user = new User();
        $user->setNom('TEST');
        $user->setPrenom('User');
        $user->setEmail($email);
        $this->setEntityId($user, $id);

        return $user;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
