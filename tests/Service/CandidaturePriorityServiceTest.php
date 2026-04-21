<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\Entretien;
use App\Entity\PieceJointe;
use App\Repository\CandidatureStatusHistoryRepository;
use App\Repository\EntretienRepository;
use App\Service\CandidatureCompletenessService;
use App\Service\CandidaturePriorityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CandidaturePriorityServiceTest extends TestCase
{
    private CandidatureCompletenessService $completenessService;
    private EntretienRepository&MockObject $entretienRepository;
    private CandidatureStatusHistoryRepository&MockObject $historyRepository;
    private CandidaturePriorityService $service;

    protected function setUp(): void
    {
        // Use the real completeness service (no DB deps)
        $this->completenessService  = new CandidatureCompletenessService();
        $this->entretienRepository  = $this->createMock(EntretienRepository::class);
        $this->historyRepository    = $this->createMock(CandidatureStatusHistoryRepository::class);

        $this->service = new CandidaturePriorityService(
            $this->completenessService,
            $this->entretienRepository,
            $this->historyRepository,
        );
    }

    // ── Test 1 — Haute priorité ─────────────────────────────────────────────

    public function testHighPriorityCandidatureIsPrioritaire(): void
    {
        $c = $this->makeCompleteCandidature();
        $c->setMatchingScore(90);
        $c->setAnneesExperience(5);
        $c->setNiveauEtudes('Bac+5');
        $c->setStatut('En attente');

        // matching component: round(90 * 0.50) = 45
        // completeness score ≥ 80 on a complete candidature → component round(X * 0.30) >= 24
        // expEdu: 12 (5+ yrs) + 8 (Bac+5) = 20
        // Total >= 45 + 24 + 20 = 89 → PRIORITAIRE (>= 80, matching >= 60, completeness >= 80)

        $analysis = $this->service->analyze($c);

        $this->assertSame('PRIORITAIRE', $analysis['category']);
        $this->assertGreaterThanOrEqual(80, $analysis['priorityScore']);
        $this->assertSame('Prioritaire', $analysis['label']);
        $this->assertSame(45, $analysis['components']['matching']);
        $this->assertSame(20, $analysis['components']['experienceEducation']);
    }

    // ── Test 2 — Dossier incomplet ──────────────────────────────────────────

    public function testIncompleteCandidatureIsIncomplete(): void
    {
        $c = new Candidature();
        // No CV, no email, no phone, no competences → BLOQUANT from completeness service
        $c->setTitrePoste('Développeur');
        $c->setEntreprise('Startup');
        $c->setTypeContrat('CDI');
        $c->setMatchingScore(85);
        $c->setStatut('En attente');

        $analysis = $this->service->analyze($c);

        $this->assertSame('INCOMPLETE', $analysis['category']);
        $this->assertSame('Incomplète', $analysis['label']);
    }

    // ── Test 3 — Candidature moyenne ────────────────────────────────────────

    public function testMediumCandidatureIsAExaminer(): void
    {
        // matching=70, completeness score around 70, exp=2yrs(5), edu=Bac+2(4) → exp+edu=9
        // components: round(70*0.50)=35, round(70*0.30)=21, 9 → total = 65 → A_EXAMINER
        $c = $this->makeSufficientCandidature();
        $c->setMatchingScore(70);
        $c->setAnneesExperience(2);
        $c->setNiveauEtudes('Bac+2');

        $analysis = $this->service->analyze($c);

        $this->assertSame('A_EXAMINER', $analysis['category']);
        $this->assertGreaterThanOrEqual(60, $analysis['priorityScore']);
        $this->assertLessThan(80, $analysis['priorityScore']);
    }

    // ── Test 4 — Faible priorité ────────────────────────────────────────────

    public function testLowPriorityCandidatureIsFaiblePriorite(): void
    {
        // low matching but completeness sufficient (>= 50, not BLOQUANT)
        // matching=10 → comp=5, completeness~55 → comp=16, exp=0, edu=null→0 → total=21 < 60
        $c = $this->makeSufficientCandidature();
        $c->setMatchingScore(10);
        $c->setAnneesExperience(null);
        $c->setNiveauEtudes(null);

        $analysis = $this->service->analyze($c);

        $this->assertSame('FAIBLE_PRIORITE', $analysis['category']);
        $this->assertLessThan(60, $analysis['priorityScore']);
    }

    // ── Test 5 — Alerte En attente > 5 jours ───────────────────────────────

    public function testPendingTooOldAlertTriggered(): void
    {
        $c = $this->makeSufficientCandidature();
        $c->setMatchingScore(50);
        $c->setStatut('En attente');

        // History returns a date 8 days ago
        $enteredAt = (new \DateTimeImmutable())->modify('-8 days');
        $this->historyRepository
            ->method('findLatestTransitionToStatus')
            ->with($c, 'En attente')
            ->willReturn($enteredAt);

        $alerts = $this->service->getDelayAlerts($c);

        $this->assertCount(1, $alerts);
        $this->assertSame('PENDING_TOO_OLD', $alerts[0]['code']);
        $this->assertSame('warning', $alerts[0]['severity']);
        $this->assertGreaterThanOrEqual(5, $alerts[0]['days']);
    }

    // ── Test 6 — Alerte Validée RH > 7 jours sans entretien ─────────────────

    public function testRhValidatedWithoutInterviewAlertTriggered(): void
    {
        $c = $this->makeSufficientCandidature();
        $c->setMatchingScore(50);
        $c->setStatut('Validée RH');

        $enteredAt = (new \DateTimeImmutable())->modify('-10 days');
        $this->historyRepository
            ->method('findLatestTransitionToStatus')
            ->with($c, 'Validée RH')
            ->willReturn($enteredAt);

        // No future interview
        $this->entretienRepository
            ->method('findFuturePlannedInterviewForCandidatureId')
            ->willReturn(null);

        $alerts = $this->service->getDelayAlerts($c);

        $this->assertCount(1, $alerts);
        $this->assertSame('RH_VALIDATED_WITHOUT_INTERVIEW', $alerts[0]['code']);
        $this->assertSame('danger', $alerts[0]['severity']);
        $this->assertGreaterThanOrEqual(7, $alerts[0]['days']);
    }

    // ── Test 7 — Validée RH avec entretien futur planifié : pas d'alerte ───

    public function testRhValidatedWithFutureInterviewNoAlert(): void
    {
        $c = $this->makeSufficientCandidature();
        $c->setMatchingScore(50);
        $c->setStatut('Validée RH');
        // Give the entity a fake ID so the interview lookup is triggered
        $this->setEntityId($c, 42);

        $enteredAt = (new \DateTimeImmutable())->modify('-10 days');
        $this->historyRepository
            ->method('findLatestTransitionToStatus')
            ->with($c, 'Validée RH')
            ->willReturn($enteredAt);

        // A future planned interview exists
        $futureInterview = new Entretien();
        $this->entretienRepository
            ->method('findFuturePlannedInterviewForCandidatureId')
            ->with(42)
            ->willReturn($futureInterview);

        $alerts = $this->service->getDelayAlerts($c);

        $this->assertCount(0, $alerts);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCompleteCandidature(): Candidature
    {
        $c = new Candidature();
        $c->setTitrePoste('Développeur PHP');
        $c->setEntreprise('TechCorp');
        $c->setTypeContrat('CDI');
        $c->setEmail('candidat@example.com');
        $c->setTelephone('22345678');
        $c->setCompetences('PHP, Symfony, Docker');
        $c->setNiveauEtudes('Bac+5');
        $c->setAnneesExperience(5);
        $c->setSalaireSouhaite('3000.00');
        $c->setStatut('En attente');

        $pj = new PieceJointe();
        $pj->setTypeDocument('CV');
        $pj->setNomFichier('cv.pdf');
        $pj->setCheminFichier('uploads/cv.pdf');
        $pj->setTailleFichier(102400);
        $pj->setCandidature($c);
        $c->getPiecesJointes()->add($pj);

        return $c;
    }

    /**
     * A candidature that is complete enough (not BLOQUANT, completeness >= 50)
     * but not necessarily at 80+ for PRIORITAIRE.
     */
    private function makeSufficientCandidature(): Candidature
    {
        $c = new Candidature();
        $c->setTitrePoste('Analyste');
        $c->setEntreprise('Startup');
        $c->setTypeContrat('CDI');
        $c->setEmail('test@example.com');
        $c->setTelephone('23456789');
        $c->setCompetences('SQL, Python');
        $c->setNiveauEtudes('Bac+3');
        $c->setAnneesExperience(2);
        $c->setStatut('En attente');
        $c->setCvFilename('cv-legacy.pdf');

        return $c;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property   = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
