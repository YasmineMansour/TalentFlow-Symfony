<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Entretien;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires sur l'entité Entretien.
 *
 * Matrice de couverture :
 * ┌──────────────────────────────────────────────┬───────────────────────────────┐
 * │ Test                                         │ Ce qui est vérifié            │
 * ├──────────────────────────────────────────────┼───────────────────────────────┤
 * │ testTypeIsNormalisedToUppercase              │ setType normalise en majusc.  │
 * │ testStatutIsNormalisedToUppercase            │ setStatut normalise en majusc.│
 * │ testNotesTechniqueInRange                    │ Notes techniques 0-20         │
 * │ testNotesCommunicationInRange                │ Notes com. 0-20               │
 * │ testMeetUrlSetterGetter                      │ Lien Meet round-trip          │
 * │ testCandidatureIdSetterGetter                │ FK candidature round-trip     │
 * │ testDateHeureSetterGetter                    │ DateTime round-trip           │
 * └──────────────────────────────────────────────┴───────────────────────────────┘
 */
final class EntretienEntityTest extends TestCase
{
    public function testTypeIsNormalisedToUppercase(): void
    {
        $e = new Entretien();
        $e->setType('en_ligne');
        $this->assertSame('EN_LIGNE', $e->getType());

        $e->setType('Presentiel');
        $this->assertSame('PRESENTIEL', $e->getType());
    }

    public function testStatutIsNormalisedToUppercase(): void
    {
        $e = new Entretien();
        $e->setStatut('planifie');
        $this->assertSame('PLANIFIE', $e->getStatut());
    }

    public function testNotesTechniqueInRange(): void
    {
        $e = new Entretien();
        $e->setNoteTechnique(15);
        $this->assertSame(15, $e->getNoteTechnique());

        $e->setNoteTechnique(0);
        $this->assertSame(0, $e->getNoteTechnique());

        $e->setNoteTechnique(20);
        $this->assertSame(20, $e->getNoteTechnique());
    }

    public function testNotesCommunicationInRange(): void
    {
        $e = new Entretien();
        $e->setNoteCommunication(18);
        $this->assertSame(18, $e->getNoteCommunication());
    }

    public function testMeetUrlIsGeneratedFromId(): void
    {
        $e = new Entretien();
        // Without ID set, getMeetUrl uses 'preview' as suffix
        $url = $e->getMeetUrl();
        $this->assertStringStartsWith('https://meet.jit.si/talentflow-entretien-', $url);
        $this->assertStringContainsString('preview', $url);
    }

    public function testLienSetterGetter(): void
    {
        $e = new Entretien();
        $e->setLien('https://meet.google.com/abc-defg-hij');
        $this->assertSame('https://meet.google.com/abc-defg-hij', $e->getLien());
    }

    public function testCandidatureIdSetterGetter(): void
    {
        $e = new Entretien();
        $e->setCandidatureId(42);
        $this->assertSame(42, $e->getCandidatureId());
    }

    public function testDateHeureSetterGetter(): void
    {
        $e = new Entretien();
        $date = new \DateTime('2026-06-15 10:30:00');
        $e->setDateHeure($date);
        $this->assertSame($date, $e->getDateHeure());
        $this->assertSame('2026-06-15 10:30:00', $e->getDateHeure()?->format('Y-m-d H:i:s'));
    }
}
