<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\CandidatureStatusHistory;
use App\Entity\PieceJointe;
use App\Service\CandidatureCompletenessService;
use App\Service\CandidatureScoreHistoryService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service CandidatureScoreHistoryService.
 *
 * Matrice de couverture :
 * ┌───────────────────────────────────────────────┬──────────────────────────────┐
 * │ Test                                          │ Ce qui est vérifié           │
 * ├───────────────────────────────────────────────┼──────────────────────────────┤
 * │ testSnapshotForCompleteCandidatureIsHigh      │ Profil complet → score ≥ 50  │
 * │ testSnapshotForEmptyCandidatureIsLow          │ Profil vide → score ≤ 30     │
 * │ testSnapshotContainsExpectedKeys              │ Structure du retour          │
 * │ testCompareSnapshotsDetectsProgression        │ Delta positif → UP           │
 * │ testCompareSnapshotsDetectsRegression         │ Delta négatif → DOWN         │
 * │ testCompareSnapshotsDetectsStability          │ Delta nul → STABLE           │
 * │ testBuildTimelineFromHistoryReturnsSorted     │ Timeline sur historique       │
 * │ testRecommendationsArePresentForIncomplete    │ Recommandations générées     │
 * └───────────────────────────────────────────────┴──────────────────────────────┘
 */
final class CandidatureScoreHistoryServiceTest extends TestCase
{
    private CandidatureScoreHistoryService $service;

    protected function setUp(): void
    {
        $this->service = new CandidatureScoreHistoryService(
            new CandidatureCompletenessService()
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCompleteCandidature(): Candidature
    {
        $c = new Candidature();
        $c->setTitrePoste('Développeur PHP Senior');
        $c->setEntreprise('TechCorp');
        $c->setTypeContrat('CDI');
        $c->setEmail('yasmine@talentflow.tn');
        $c->setTelephone('22345678');
        $c->setCompetences('PHP Symfony Docker MySQL');
        $c->setNiveauEtudes('Bac+5');
        $c->setAnneesExperience(5);
        $c->setSalaireSouhaite('3000.00');
        $c->setMatchingScore(85);

        $pj = new PieceJointe();
        $pj->setTypeDocument('CV');
        $pj->setNomFichier('cv.pdf');
        $pj->setCheminFichier('uploads/cv.pdf');
        $pj->setTailleFichier(102400);
        $c->addPieceJointe($pj);

        $pjL = new PieceJointe();
        $pjL->setTypeDocument('Lettre de motivation');
        $pjL->setNomFichier('lettre.pdf');
        $pjL->setCheminFichier('uploads/lettre.pdf');
        $pjL->setTailleFichier(51200);
        $c->addPieceJointe($pjL);

        return $c;
    }

    private function makeEmptyCandidature(): Candidature
    {
        $c = new Candidature();
        $c->setTitrePoste('X');
        $c->setEntreprise('Y');
        $c->setTypeContrat('CDI');
        // No email, no docs, no competences, no experience, no matching score
        return $c;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testSnapshotForCompleteCandidatureIsHigh(): void
    {
        $snapshot = $this->service->computeSnapshot($this->makeCompleteCandidature());
        $this->assertGreaterThanOrEqual(50, $snapshot['scoreGlobal']);
    }

    public function testSnapshotForEmptyCandidatureIsLow(): void
    {
        $snapshot = $this->service->computeSnapshot($this->makeEmptyCandidature());
        $this->assertLessThanOrEqual(30, $snapshot['scoreGlobal']);
    }

    public function testSnapshotContainsExpectedKeys(): void
    {
        $snapshot = $this->service->computeSnapshot($this->makeCompleteCandidature());
        $this->assertArrayHasKey('scoreGlobal', $snapshot);
        $this->assertArrayHasKey('components', $snapshot);
        $this->assertArrayHasKey('trend', $snapshot);
        $this->assertArrayHasKey('trendLabel', $snapshot);
        $this->assertArrayHasKey('recommendations', $snapshot);
        $this->assertArrayHasKey('isReadyForInterview', $snapshot);
        $this->assertArrayHasKey('timestamp', $snapshot);
        $this->assertArrayHasKey('matching', $snapshot['components']);
        $this->assertArrayHasKey('completeness', $snapshot['components']);
        $this->assertArrayHasKey('expEdu', $snapshot['components']);
    }

    public function testCompareSnapshotsDetectsProgression(): void
    {
        $prev = ['scoreGlobal' => 40];
        $curr = ['scoreGlobal' => 70];
        $comparison = $this->service->compareSnapshots($prev, $curr);
        $this->assertSame(30, $comparison['delta']);
        $this->assertSame('UP', $comparison['direction']);
        $this->assertStringContainsString('Progression', $comparison['directionLabel']);
    }

    public function testCompareSnapshotsDetectsRegression(): void
    {
        $prev = ['scoreGlobal' => 80];
        $curr = ['scoreGlobal' => 55];
        $comparison = $this->service->compareSnapshots($prev, $curr);
        $this->assertSame(-25, $comparison['delta']);
        $this->assertSame('DOWN', $comparison['direction']);
    }

    public function testCompareSnapshotsDetectsStability(): void
    {
        $prev = ['scoreGlobal' => 60];
        $curr = ['scoreGlobal' => 60];
        $comparison = $this->service->compareSnapshots($prev, $curr);
        $this->assertSame(0, $comparison['delta']);
        $this->assertSame('STABLE', $comparison['direction']);
    }

    public function testBuildTimelineFromHistoryReturnsSorted(): void
    {
        $history1 = new CandidatureStatusHistory();
        $history1->setToStatus('En attente');
        $history1->setChangedAt(new \DateTimeImmutable('2026-01-01 10:00'));

        $history2 = new CandidatureStatusHistory();
        $history2->setToStatus('Validée RH');
        $history2->setChangedAt(new \DateTimeImmutable('2026-01-10 14:00'));

        $history3 = new CandidatureStatusHistory();
        $history3->setToStatus('Entretien');
        $history3->setChangedAt(new \DateTimeImmutable('2026-01-20 09:00'));

        $timeline = $this->service->buildTimelineFromHistory([$history1, $history2, $history3]);

        $this->assertCount(3, $timeline);
        $this->assertSame('En attente', $timeline[0]['status']);
        $this->assertSame('Validée RH', $timeline[1]['status']);
        $this->assertSame('Entretien', $timeline[2]['status']);
        $this->assertSame(25, $timeline[0]['estimatedScore']);
        $this->assertSame(55, $timeline[1]['estimatedScore']);
        $this->assertSame(75, $timeline[2]['estimatedScore']);
    }

    public function testRecommendationsArePresentForIncomplete(): void
    {
        $snapshot = $this->service->computeSnapshot($this->makeEmptyCandidature());
        $this->assertNotEmpty($snapshot['recommendations']);
    }
}
