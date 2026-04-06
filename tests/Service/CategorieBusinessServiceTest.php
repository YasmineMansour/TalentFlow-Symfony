<?php

namespace App\Tests\Service;

use App\Entity\Categorie;
use App\Entity\Offre;
use App\Service\CategorieBusinessService;
use PHPUnit\Framework\TestCase;

class CategorieBusinessServiceTest extends TestCase
{
    private CategorieBusinessService $service;

    protected function setUp(): void
    {
        $this->service = new CategorieBusinessService();
    }

    private function createCategorieWithOffres(int $total, int $actives = 0): Categorie
    {
        $categorie = new Categorie();
        $categorie->setNom('Test');

        for ($i = 0; $i < $total; $i++) {
            $offre = new Offre();
            $offre->setTitre('Offre ' . $i);
            $offre->setActive($i < $actives);
            $categorie->addOffre($offre);
        }

        return $categorie;
    }

    public function testPopulariteTresPopulaire(): void
    {
        $categorie = $this->createCategorieWithOffres(10);
        $this->assertSame('Très populaire', $this->service->getPopularite($categorie));
    }

    public function testPopularitePopulaire(): void
    {
        $categorie = $this->createCategorieWithOffres(5);
        $this->assertSame('Populaire', $this->service->getPopularite($categorie));
    }

    public function testPopularitePeuPopulaire(): void
    {
        $categorie = $this->createCategorieWithOffres(2);
        $this->assertSame('Peu populaire', $this->service->getPopularite($categorie));
    }

    public function testPopulariteVide(): void
    {
        $categorie = new Categorie();
        $this->assertSame('Vide', $this->service->getPopularite($categorie));
    }

    public function testTauxActiviteAucuneOffre(): void
    {
        $categorie = new Categorie();
        $this->assertSame('Aucune offre', $this->service->getTauxActivite($categorie));
    }

    public function testTauxActiviteAvecOffres(): void
    {
        $categorie = $this->createCategorieWithOffres(4, 2);
        $this->assertSame('50% actives', $this->service->getTauxActivite($categorie));
    }

    public function testIsCompleteTrue(): void
    {
        $categorie = new Categorie();
        $categorie->setNom('Test');
        $categorie->setDescription('Une description');
        $this->assertTrue($this->service->isComplete($categorie));
    }

    public function testIsCompleteFalse(): void
    {
        $categorie = new Categorie();
        $categorie->setNom('Test');
        $this->assertFalse($this->service->isComplete($categorie));
    }
}
