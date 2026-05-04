<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Service\ProfileCompletionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ProfileCompletionService.
 *
 * Matrice de couverture :
 * ┌────────────────────────────────────────────┬───────────────────────────────┐
 * │ Test                                       │ Ce qui est vérifié            │
 * ├────────────────────────────────────────────┼───────────────────────────────┤
 * │ testEmptyUserScoreIsZero                   │ Profil vide → score 0         │
 * │ testFullProfileScoreIs100                  │ Profil complet → score 100    │
 * │ testPartialProfileScoreIsIntermediate      │ Champs partiels → entre 0-100 │
 * │ testMissingFieldsReturnsCorrectLabels      │ Labels FR des champs manqts   │
 * │ testScoreColorDanger                       │ score < 50 → danger           │
 * │ testScoreColorWarning                      │ 50 ≤ score < 80 → warning     │
 * │ testScoreColorSuccess                      │ score ≥ 80 → success          │
 * │ testEmailAloneContributes20Points          │ Seul email → 20 pts (20 %)   │
 * └────────────────────────────────────────────┴───────────────────────────────┘
 */
final class ProfileCompletionServiceTest extends TestCase
{
    private ProfileCompletionService $service;

    protected function setUp(): void
    {
        $this->service = new ProfileCompletionService();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function emptyUser(): User
    {
        $user = new User();
        // No fields set — all null/empty
        return $user;
    }

    private function fullUser(): User
    {
        $user = new User();
        $user->setNom('Trabelsi');
        $user->setPrenom('Yasmine');
        $user->setEmail('yasmine@talentflow.tn');
        $user->setTelephone('+21622000000');
        $user->setTitrePoste('Ingénieure logiciel');
        $user->setBio('Passionnée par le développement web et l\'IA.');
        $entreprise = new Entreprise();
        $entreprise->setNom('TechCorp');
        $user->setEntreprise($entreprise);
        return $user;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testEmptyUserScoreIsZero(): void
    {
        $score = $this->service->getScore($this->emptyUser());
        $this->assertSame(0, $score);
    }

    public function testFullProfileScoreIs100(): void
    {
        $score = $this->service->getScore($this->fullUser());
        $this->assertSame(100, $score);
    }

    public function testPartialProfileScoreIsIntermediate(): void
    {
        $user = new User();
        $user->setNom('Ben Ali');
        $user->setPrenom('Mounib');
        $user->setEmail('mounib@talentflow.tn');
        // Missing: telephone, titrePoste, bio, entreprise

        $score = $this->service->getScore($user);
        $this->assertGreaterThan(0, $score);
        $this->assertLessThan(100, $score);
        // nom(15) + prenom(15) + email(20) = 50
        $this->assertSame(50, $score);
    }

    public function testMissingFieldsReturnsCorrectLabels(): void
    {
        $user = new User();
        $user->setNom('Rayen');
        // Everything else missing

        $missing = $this->service->getMissingFields($user);

        $this->assertArrayHasKey('prenom', $missing);
        $this->assertArrayHasKey('email', $missing);
        $this->assertArrayHasKey('telephone', $missing);
        $this->assertArrayHasKey('titrePoste', $missing);
        $this->assertArrayHasKey('bio', $missing);
        $this->assertArrayHasKey('entreprise', $missing);
        $this->assertArrayNotHasKey('nom', $missing);

        // Check French labels
        $this->assertSame('Prénom', $missing['prenom']);
        $this->assertSame('Adresse email', $missing['email']);
    }

    public function testScoreColorDanger(): void
    {
        $this->assertSame('danger', $this->service->getScoreColor(0));
        $this->assertSame('danger', $this->service->getScoreColor(49));
    }

    public function testScoreColorWarning(): void
    {
        $this->assertSame('warning', $this->service->getScoreColor(50));
        $this->assertSame('warning', $this->service->getScoreColor(79));
    }

    public function testScoreColorSuccess(): void
    {
        $this->assertSame('success', $this->service->getScoreColor(80));
        $this->assertSame('success', $this->service->getScoreColor(100));
    }

    public function testEmailAloneContributes20Points(): void
    {
        $user = new User();
        $user->setEmail('only-email@test.tn');

        $score = $this->service->getScore($user);
        $this->assertSame(20, $score);
    }
}
