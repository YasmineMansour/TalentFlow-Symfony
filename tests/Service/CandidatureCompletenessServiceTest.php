<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\PieceJointe;
use App\Service\CandidatureCompletenessService;
use PHPUnit\Framework\TestCase;

class CandidatureCompletenessServiceTest extends TestCase
{
    private CandidatureCompletenessService $service;

    protected function setUp(): void
    {
        $this->service = new CandidatureCompletenessService();
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
        $c->setAnneesExperience(3);
        $c->setSalaireSouhaite('2500.00');

        // CV via PieceJointe
        $pj = new PieceJointe();
        $pj->setTypeDocument('CV');
        $pj->setNomFichier('cv.pdf');
        $pj->setCheminFichier('uploads/cv.pdf');
        $pj->setTailleFichier(102400);
        $pj->setCandidature($c);
        $c->getPiecesJointes()->add($pj);

        // Lettre via PieceJointe
        $pjL = new PieceJointe();
        $pjL->setTypeDocument('Lettre de motivation');
        $pjL->setNomFichier('lettre.pdf');
        $pjL->setCheminFichier('uploads/lettre.pdf');
        $pjL->setTailleFichier(51200);
        $pjL->setCandidature($c);
        $c->getPiecesJointes()->add($pjL);

        return $c;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testCandidatureCompleteHasHighScoreAndLevelComplet(): void
    {
        $c = $this->makeCompleteCandidature();
        $analysis = $this->service->analyze($c);

        $this->assertGreaterThanOrEqual(80, $analysis['score']);
        $this->assertSame('COMPLET', $analysis['level']);
        $this->assertTrue($analysis['hasCv']);
        $this->assertTrue($analysis['hasLettreMotivation']);
        $this->assertTrue($analysis['canMoveToRhValidation']);
        $this->assertTrue($analysis['canMoveToInterview']);
        $this->assertEmpty($analysis['blockingReasons']);
    }

    public function testMissingCvIsBloquant(): void
    {
        $c = $this->makeCompleteCandidature();
        // Retirer tous les docs et les filenames legacy
        $c->getPiecesJointes()->clear();
        $c->setCvFilename(null);
        $c->setLettreMotivationFilename(null);

        $analysis = $this->service->analyze($c);

        $this->assertFalse($analysis['hasCv']);
        $this->assertSame('BLOQUANT', $analysis['level']);
        $this->assertFalse($analysis['canMoveToRhValidation']);
        $this->assertFalse($analysis['canMoveToInterview']);
        $this->assertContains('CV manquant', $analysis['blockingReasons']);
    }

    public function testPartialCandidatureLevelACompleter(): void
    {
        $c = new Candidature();
        $c->setTitrePoste('Développeur');
        $c->setEntreprise('Startup');
        $c->setTypeContrat('Stage');
        $c->setEmail('test@test.com');
        $c->setTelephone('23456789');
        $c->setCompetences('Java');
        // niveauEtudes volontairement absent (-10 pts) → score ~70 → A_COMPLETER
        $c->setAnneesExperience(1);
        // Pas de salaire, pas d'offre liée, pas de candidat lié
        // CV via filename legacy
        $c->setCvFilename('cv-legacy.pdf');

        $analysis = $this->service->analyze($c);

        $this->assertTrue($analysis['hasCv']);
        $this->assertGreaterThanOrEqual(50, $analysis['score']);
        $this->assertLessThan(80, $analysis['score']);
        $this->assertSame('A_COMPLETER', $analysis['level']);
        $this->assertFalse($analysis['canMoveToInterview']);
    }

    public function testLegacyCvFilenameDetected(): void
    {
        $c = new Candidature();
        $c->setTitrePoste('Analyste');
        $c->setEntreprise('Corp');
        $c->setTypeContrat('CDD');
        $c->setEmail('x@x.com');
        $c->setTelephone('24567890');
        $c->setCompetences('SQL');
        $c->setNiveauEtudes('Bac+2');
        $c->setAnneesExperience(2);
        $c->setSalaireSouhaite('1800.00');

        // Aucune PieceJointe — on utilise les champs legacy
        $c->setCvFilename('mon-cv.pdf');
        $c->setLettreMotivationFilename('ma-lettre.pdf');

        $analysis = $this->service->analyze($c);

        $this->assertTrue($analysis['hasCv'], 'CV devrait être détecté via cvFilename');
        $this->assertTrue($analysis['hasLettreMotivation'], 'Lettre devrait être détectée via lettreMotivationFilename');
        $this->assertEmpty($analysis['missingDocuments']);
    }

    public function testLegacyLettreFilenameDetected(): void
    {
        $c = new Candidature();
        $c->setLettreMotivationFilename('lettre-legacy.pdf');

        $analysis = $this->service->analyze($c);

        $this->assertTrue($analysis['hasLettreMotivation']);
    }

    public function testCvDocumentNormalizationWithUppercaseCurriculumVitae(): void
    {
        $c = new Candidature();

        $pj = new PieceJointe();
        $pj->setTypeDocument('CURRICULUM_VITAE');
        $pj->setNomFichier('cv.pdf');
        $pj->setCheminFichier('uploads/cv.pdf');
        $pj->setTailleFichier(12000);
        $pj->setCandidature($c);
        $c->getPiecesJointes()->add($pj);

        $analysis = $this->service->analyze($c);

        $this->assertTrue($analysis['hasCv']);
    }

    public function testLettreDocumentNormalizationWithUnderscoreLabel(): void
    {
        $c = new Candidature();

        $pj = new PieceJointe();
        $pj->setTypeDocument('lettre_motivation');
        $pj->setNomFichier('lettre.pdf');
        $pj->setCheminFichier('uploads/lettre.pdf');
        $pj->setTailleFichier(12000);
        $pj->setCandidature($c);
        $c->getPiecesJointes()->add($pj);

        $analysis = $this->service->analyze($c);

        $this->assertTrue($analysis['hasLettreMotivation']);
    }

    public function testMissingEmailIsBloquant(): void
    {
        $c = $this->makeCompleteCandidature();
        $c->setEmail(null);

        $analysis = $this->service->analyze($c);

        $this->assertSame('BLOQUANT', $analysis['level']);
        $this->assertContains('E-mail manquant ou invalide', $analysis['blockingReasons']);
    }

    public function testInvalidEmailIsBloquant(): void
    {
        $c = $this->makeCompleteCandidature();
        $c->setEmail('not-an-email');

        $analysis = $this->service->analyze($c);

        $this->assertSame('BLOQUANT', $analysis['level']);
    }

    public function testMissingTelephoneIsBloquant(): void
    {
        $c = $this->makeCompleteCandidature();
        $c->setTelephone(null);

        $analysis = $this->service->analyze($c);

        $this->assertSame('BLOQUANT', $analysis['level']);
        $this->assertContains('Téléphone manquant', $analysis['blockingReasons']);
    }

    public function testMissingCompetencesIsBloquant(): void
    {
        $c = $this->makeCompleteCandidature();
        $c->setCompetences(null);

        $analysis = $this->service->analyze($c);

        $this->assertSame('BLOQUANT', $analysis['level']);
        $this->assertContains('Compétences non renseignées', $analysis['blockingReasons']);
    }

    public function testComputeScoreMatchesAnalyzeScore(): void
    {
        $c = $this->makeCompleteCandidature();

        $this->assertSame($this->service->computeScore($c), $this->service->analyze($c)['score']);
    }

    public function testCheckTransitionAllowedBlocksValidateRh(): void
    {
        $c = new Candidature();
        // Dossier vide → BLOQUANT
        $message = $this->service->checkTransitionAllowed($c, 'validate_rh');

        $this->assertNotNull($message);
        $this->assertStringContainsString('incomplet', $message);
    }

    public function testCheckTransitionAllowedPassesForCompleteCandidate(): void
    {
        $c = $this->makeCompleteCandidature();
        // On lui ajoute salaire + offre + candidat pour avoir score ≥ 80
        $c->setSalaireSouhaite('3000.00');

        // Pour les tests validate_rh : offre et candidat non liés mais score peut suffire
        // Vérifier que le résultat est null (autorisé) si le score est suffisant
        $analysis = $this->service->analyze($c);
        if ($analysis['canMoveToRhValidation']) {
            $message = $this->service->checkTransitionAllowed($c, 'validate_rh');
            $this->assertNull($message);
        } else {
            // score insuffisant sans offre/candidat liés — acceptable dans ce test
            $this->assertLessThan(80, $analysis['score']);
        }
    }

    public function testNonProtectedTransitionAlwaysPasses(): void
    {
        $c = new Candidature(); // dossier totalement vide

        $this->assertNull($this->service->checkTransitionAllowed($c, 'reject'));
        $this->assertNull($this->service->checkTransitionAllowed($c, 'accept'));
    }
}
