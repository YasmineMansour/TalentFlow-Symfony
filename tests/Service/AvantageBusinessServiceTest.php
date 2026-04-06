<?php

namespace App\Tests\Service;

use App\Entity\Avantage;
use App\Entity\Offre;
use App\Service\AvantageBusinessService;
use PHPUnit\Framework\TestCase;

class AvantageBusinessServiceTest extends TestCase
{
    private AvantageBusinessService $service;

    protected function setUp(): void
    {
        $this->service = new AvantageBusinessService();
    }

    public function testImportanceElevee(): void
    {
        $avantage = new Avantage();
        $avantage->setType('Financier');
        $this->assertSame('Élevée', $this->service->getImportance($avantage));
    }

    public function testImportanceMoyenne(): void
    {
        $avantage = new Avantage();
        $avantage->setType('Bien-être');
        $this->assertSame('Moyenne', $this->service->getImportance($avantage));
    }

    public function testImportanceStandard(): void
    {
        $avantage = new Avantage();
        $avantage->setType('Matériel');
        $this->assertSame('Standard', $this->service->getImportance($avantage));
    }

    public function testImportanceBasique(): void
    {
        $avantage = new Avantage();
        $avantage->setType('AUTRE');
        $this->assertSame('Basique', $this->service->getImportance($avantage));
    }

    public function testQualiteDescriptionNonRenseignee(): void
    {
        $avantage = new Avantage();
        $this->assertSame('Non renseignée', $this->service->getQualiteDescription($avantage));
    }

    public function testQualiteDescriptionInsuffisante(): void
    {
        $avantage = new Avantage();
        $avantage->setDescription('Court');
        $this->assertSame('Insuffisante', $this->service->getQualiteDescription($avantage));
    }

    public function testQualiteDescriptionCorrecte(): void
    {
        $avantage = new Avantage();
        $avantage->setDescription('Une description de longueur correcte pour le test');
        $this->assertSame('Correcte', $this->service->getQualiteDescription($avantage));
    }

    public function testQualiteDescriptionDetaillee(): void
    {
        $avantage = new Avantage();
        $avantage->setDescription(str_repeat('a', 100));
        $this->assertSame('Détaillée', $this->service->getQualiteDescription($avantage));
    }

    public function testIsCompleteTrue(): void
    {
        $offre = new Offre();
        $offre->setTitre('Test');

        $avantage = new Avantage();
        $avantage->setNom('Ticket resto');
        $avantage->setDescription('Description complète');
        $avantage->setType('Financier');
        $avantage->setOffre($offre);
        $this->assertTrue($this->service->isComplete($avantage));
    }

    public function testIsCompleteFalseSansDescription(): void
    {
        $offre = new Offre();
        $offre->setTitre('Test');

        $avantage = new Avantage();
        $avantage->setNom('Ticket resto');
        $avantage->setType('Financier');
        $avantage->setOffre($offre);
        $this->assertFalse($this->service->isComplete($avantage));
    }

    public function testIsCompleteFalseSansOffre(): void
    {
        $avantage = new Avantage();
        $avantage->setNom('Ticket resto');
        $avantage->setDescription('Description');
        $avantage->setType('Financier');
        $this->assertFalse($this->service->isComplete($avantage));
    }
}
