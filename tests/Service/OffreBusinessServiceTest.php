<?php

namespace App\Tests\Service;

use App\Entity\Offre;
use App\Service\OffreBusinessService;
use PHPUnit\Framework\TestCase;

class OffreBusinessServiceTest extends TestCase
{
    private OffreBusinessService $service;

    protected function setUp(): void
    {
        $this->service = new OffreBusinessService();
    }

    public function testClassementOr(): void
    {
        $offre = new Offre();
        $offre->setSalaireMax(5000);
        $this->assertSame('Or', $this->service->getClassement($offre));
    }

    public function testClassementArgent(): void
    {
        $offre = new Offre();
        $offre->setSalaireMax(2500);
        $this->assertSame('Argent', $this->service->getClassement($offre));
    }

    public function testClassementBronze(): void
    {
        $offre = new Offre();
        $offre->setSalaireMax(500);
        $this->assertSame('Bronze', $this->service->getClassement($offre));
    }

    public function testClassementNonClasse(): void
    {
        $offre = new Offre();
        $this->assertSame('Non classé', $this->service->getClassement($offre));
    }

    public function testCoherenceNonRenseigne(): void
    {
        $offre = new Offre();
        $this->assertSame('Non renseigné', $this->service->getCoherence($offre));
    }

    public function testCoherenceIncoherent(): void
    {
        $offre = new Offre();
        $offre->setSalaireMin(5000);
        $offre->setSalaireMax(2000);
        $this->assertSame('Incohérent', $this->service->getCoherence($offre));
    }

    public function testCoherenceTresSerre(): void
    {
        $offre = new Offre();
        $offre->setSalaireMin(2900);
        $offre->setSalaireMax(3000);
        $this->assertSame('Très serré', $this->service->getCoherence($offre));
    }

    public function testCoherenceCoherent(): void
    {
        $offre = new Offre();
        $offre->setSalaireMin(2000);
        $offre->setSalaireMax(4000);
        $this->assertSame('Cohérent', $this->service->getCoherence($offre));
    }

    public function testIsCompleteTrue(): void
    {
        $offre = new Offre();
        $offre->setTitre('Dev PHP');
        $offre->setDescription('Description longue de test');
        $offre->setLocalisation('Tunis');
        $offre->setSalaireMax(3000);
        $this->assertTrue($this->service->isComplete($offre));
    }

    public function testIsCompleteFalse(): void
    {
        $offre = new Offre();
        $offre->setTitre('Dev PHP');
        $this->assertFalse($this->service->isComplete($offre));
    }
}
