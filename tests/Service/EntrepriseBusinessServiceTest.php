<?php

namespace App\Tests\Service;

use App\Entity\Entreprise;
use App\Entity\Offre;
use App\Service\EntrepriseBusinessService;
use PHPUnit\Framework\TestCase;

class EntrepriseBusinessServiceTest extends TestCase
{
    private EntrepriseBusinessService $service;

    protected function setUp(): void
    {
        $this->service = new EntrepriseBusinessService();
    }

    private function createEntrepriseWithOffres(int $total, int $actives = 0): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('Test');

        for ($i = 0; $i < $total; $i++) {
            $offre = new Offre();
            $offre->setTitre('Offre ' . $i);
            $offre->setActive($i < $actives);
            $entreprise->addOffre($offre);
        }

        return $entreprise;
    }

    public function testTailleGrande(): void
    {
        $entreprise = $this->createEntrepriseWithOffres(10);
        $this->assertSame('Grande', $this->service->getTaille($entreprise));
    }

    public function testTailleMoyenne(): void
    {
        $entreprise = $this->createEntrepriseWithOffres(5);
        $this->assertSame('Moyenne', $this->service->getTaille($entreprise));
    }

    public function testTaillePetite(): void
    {
        $entreprise = $this->createEntrepriseWithOffres(2);
        $this->assertSame('Petite', $this->service->getTaille($entreprise));
    }

    public function testTailleAucuneOffre(): void
    {
        $entreprise = new Entreprise();
        $this->assertSame('Aucune offre', $this->service->getTaille($entreprise));
    }

    public function testNiveauActiviteInactive(): void
    {
        $entreprise = new Entreprise();
        $this->assertSame('Inactive', $this->service->getNiveauActivite($entreprise));
    }

    public function testNiveauActiviteTresActive(): void
    {
        $entreprise = $this->createEntrepriseWithOffres(4, 4);
        $this->assertSame('Très active', $this->service->getNiveauActivite($entreprise));
    }

    public function testNiveauActiviteActive(): void
    {
        $entreprise = $this->createEntrepriseWithOffres(4, 2);
        $this->assertSame('Active', $this->service->getNiveauActivite($entreprise));
    }

    public function testNiveauActivitePeuActive(): void
    {
        $entreprise = $this->createEntrepriseWithOffres(4, 1);
        $this->assertSame('Peu active', $this->service->getNiveauActivite($entreprise));
    }
}
