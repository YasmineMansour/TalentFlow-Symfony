<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\Categorie;
use App\Entity\Offre;
use App\Service\CandidatureMatchingService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service CandidatureMatchingService.
 *
 * Matrice de couverture :
 * ┌────────────────────────────────────────────────┬─────────────────────────────┐
 * │ Test                                           │ Ce qui est vérifié          │
 * ├────────────────────────────────────────────────┼─────────────────────────────┤
 * │ testScoreIsZeroWithNoOffre                     │ Sans offre → 0              │
 * │ testScoreIsZeroWithEmptyTexts                  │ Textes vides → 0            │
 * │ testHighMatchingScoreForExactKeywords          │ Mots-clés identiques → haut │
 * │ testLowMatchingScoreForUnrelatedTexts          │ Textes différents → bas     │
 * │ testScoreIsBoundedBetween0And100               │ Bornes [0, 100]             │
 * │ testScoreWithPartialKeywordOverlap             │ Chevauchement partiel       │
 * │ testCandidatureWithCompetencesMatchesOffer     │ Compétences vs description  │
 * └────────────────────────────────────────────────┴─────────────────────────────┘
 */
final class CandidatureMatchingServiceTest extends TestCase
{
    private CandidatureMatchingService $service;

    protected function setUp(): void
    {
        $this->service = new CandidatureMatchingService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeOfferWithText(string $titre, string $description, string $typeContrat = 'CDI'): Offre
    {
        $offre = new Offre();
        $offre->setTitre($titre);
        $offre->setDescription($description);
        $offre->setTypeContrat($typeContrat);
        return $offre;
    }

    private function makeCandidatureForOffer(Offre $offre, string $titrePoste, string $competences, string $description = ''): Candidature
    {
        $c = new Candidature();
        $c->setOffre($offre);
        $c->setTitrePoste($titrePoste);
        $c->setEntreprise('TestCorp');
        $c->setTypeContrat('CDI');
        $c->setCompetences($competences);
        if ($description !== '') {
            $c->setDescription($description);
        }
        return $c;
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testScoreIsZeroWithNoOffre(): void
    {
        $c = new Candidature();
        $c->setTitrePoste('Développeur');
        $c->setEntreprise('TechCorp');
        $c->setTypeContrat('CDI');
        // no offre set
        $this->assertSame(0, $this->service->computeScore($c));
    }

    public function testScoreIsZeroWithEmptyTexts(): void
    {
        $offre = $this->makeOfferWithText('', '');
        $c = $this->makeCandidatureForOffer($offre, '', '');
        $this->assertSame(0, $this->service->computeScore($c));
    }

    public function testHighMatchingScoreForExactKeywords(): void
    {
        $offre = $this->makeOfferWithText(
            'Développeur Symfony PHP',
            'Nous cherchons un développeur Symfony PHP avec Docker Doctrine MySQL.'
        );
        $c = $this->makeCandidatureForOffer(
            $offre,
            'Développeur Symfony PHP',
            'Symfony PHP Docker Doctrine MySQL',
            'Développeur Symfony PHP expérimenté Docker Doctrine MySQL'
        );

        $score = $this->service->computeScore($c);
        $this->assertGreaterThanOrEqual(50, $score, 'Identical keywords should give a high matching score');
    }

    public function testLowMatchingScoreForUnrelatedTexts(): void
    {
        $offre = $this->makeOfferWithText(
            'Cuisinier en restauration',
            'Recherche cuisinier expérimenté cuisine italienne restauration collective.'
        );
        $c = $this->makeCandidatureForOffer(
            $offre,
            'Ingénieur réseau télécommunications',
            'Cisco OSPF BGP MPLS VPN firewall'
        );

        $score = $this->service->computeScore($c);
        $this->assertLessThan(30, $score, 'Completely unrelated texts should yield a low score');
    }

    public function testScoreIsBoundedBetween0And100(): void
    {
        $offre = $this->makeOfferWithText('PHP', str_repeat('PHP Symfony ', 50));
        $c = $this->makeCandidatureForOffer($offre, 'PHP', str_repeat('PHP Symfony ', 50));
        $score = $this->service->computeScore($c);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testScoreWithPartialKeywordOverlap(): void
    {
        $offre = $this->makeOfferWithText(
            'Développeur Full Stack',
            'React Angular NodeJS TypeScript PostgreSQL Docker.'
        );
        $c = $this->makeCandidatureForOffer(
            $offre,
            'Développeur Frontend',
            'React TypeScript CSS HTML'
        );

        $score = $this->service->computeScore($c);
        $this->assertGreaterThan(0, $score, 'Partial overlap should give a positive score');
        $this->assertLessThan(100, $score, 'Partial overlap should not give full score');
    }

    public function testCandidatureWithCompetencesMatchesOffer(): void
    {
        $categorie = new Categorie();
        $categorie->setNom('Informatique');

        $offre = $this->makeOfferWithText(
            'Data Engineer',
            'Python Spark Hadoop ETL pipeline Big Data machine learning.'
        );
        $offre->setCategorie($categorie);

        $c = $this->makeCandidatureForOffer(
            $offre,
            'Data Engineer',
            'Python Spark Hadoop ETL Big Data',
            'Expérience en ETL pipeline Big Data machine learning Python.'
        );

        $score = $this->service->computeScore($c);
        $this->assertGreaterThanOrEqual(40, $score);
    }
}
