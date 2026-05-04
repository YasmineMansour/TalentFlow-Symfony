<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Candidature;
use App\Entity\PieceJointe;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur l'entité Candidature.
 *
 * Matrice de couverture :
 * ┌─────────────────────────────────────────────────┬──────────────────────────────┐
 * │ Test                                            │ Ce qui est vérifié           │
 * ├─────────────────────────────────────────────────┼──────────────────────────────┤
 * │ testDefaultStatutIsEnAttente                    │ Statut par défaut            │
 * │ testMatchingScoreBoundsAreRespected             │ Valeur in [0,100]            │
 * │ testAddPieceJointeIncreasesCollection           │ Relation OneToMany           │
 * │ testRemovePieceJointeDecollection               │ Suppression de relation      │
 * │ testCreatedAtIsSetOnInstantiation               │ Timestamp auto-init          │
 * │ testSettersAndGettersRoundTrip                  │ Getters / setters basiques   │
 * │ testEmailSetterAndGetter                        │ Champ email                  │
 * │ testDateCandidatureDefaultsToToday              │ Date auto-init               │
 * └─────────────────────────────────────────────────┴──────────────────────────────┘
 */
final class CandidatureEntityTest extends TestCase
{
    public function testDefaultStatutIsEnAttente(): void
    {
        $c = new Candidature();
        $this->assertSame('En attente', $c->getStatut());
    }

    public function testMatchingScoreBoundsAreRespected(): void
    {
        $c = new Candidature();

        $c->setMatchingScore(0);
        $this->assertSame(0, $c->getMatchingScore());

        $c->setMatchingScore(100);
        $this->assertSame(100, $c->getMatchingScore());

        $c->setMatchingScore(75);
        $this->assertSame(75, $c->getMatchingScore());
    }

    public function testAddPieceJointeIncreasesCollection(): void
    {
        $c = new Candidature();
        $this->assertCount(0, $c->getPiecesJointes());

        $pj = new PieceJointe();
        $pj->setTypeDocument('CV');
        $pj->setNomFichier('cv.pdf');
        $pj->setCheminFichier('uploads/cv.pdf');
        $pj->setTailleFichier(102400);
        $c->addPieceJointe($pj);

        $this->assertCount(1, $c->getPiecesJointes());
        $this->assertSame($c, $pj->getCandidature());
    }

    public function testRemovePieceJointeDecollection(): void
    {
        $c = new Candidature();
        $pj = new PieceJointe();
        $pj->setTypeDocument('Lettre de motivation');
        $pj->setNomFichier('lettre.pdf');
        $pj->setCheminFichier('uploads/lettre.pdf');
        $pj->setTailleFichier(51200);
        $c->addPieceJointe($pj);
        $this->assertCount(1, $c->getPiecesJointes());

        $c->removePieceJointe($pj);
        $this->assertCount(0, $c->getPiecesJointes());
    }

    public function testCreatedAtIsSetOnInstantiation(): void
    {
        $c = new Candidature();
        $this->assertInstanceOf(\DateTimeImmutable::class, $c->getCreatedAt());
    }

    public function testSettersAndGettersRoundTrip(): void
    {
        $c = new Candidature();
        $c->setTitrePoste('Ingénieur DevOps');
        $c->setEntreprise('CloudCorp');
        $c->setTypeContrat('CDD');
        $c->setNiveauEtudes('Bac+5');
        $c->setAnneesExperience(3);
        $c->setCompetences('Kubernetes Docker Terraform');
        $c->setNotes('Candidature prioritaire');

        $this->assertSame('Ingénieur DevOps', $c->getTitrePoste());
        $this->assertSame('CloudCorp', $c->getEntreprise());
        $this->assertSame('CDD', $c->getTypeContrat());
        $this->assertSame('Bac+5', $c->getNiveauEtudes());
        $this->assertSame(3, $c->getAnneesExperience());
        $this->assertSame('Kubernetes Docker Terraform', $c->getCompetences());
        $this->assertSame('Candidature prioritaire', $c->getNotes());
    }

    public function testEmailSetterAndGetter(): void
    {
        $c = new Candidature();
        $c->setEmail('candidat@example.com');
        $this->assertSame('candidat@example.com', $c->getEmail());
    }

    public function testDateCandidatureDefaultsToToday(): void
    {
        $c = new Candidature();
        $today = new \DateTimeImmutable();
        $this->assertNotNull($c->getDateCandidature());
        $this->assertSame(
            $today->format('Y-m-d'),
            $c->getDateCandidature()?->format('Y-m-d')
        );
    }
}
