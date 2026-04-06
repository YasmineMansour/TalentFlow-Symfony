<?php

namespace App\Tests\Controller;

use App\Entity\Avantage;
use App\Entity\Offre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AvantageControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createOffre(): Offre
    {
        $offre = new Offre();
        $offre->setTitre('Offre Test');
        $offre->setTypeContrat('CDI');
        $offre->setModeTravail('ON_SITE');
        $this->em->persist($offre);
        $this->em->flush();

        return $offre;
    }

    private function createAvantage(?Offre $offre = null): Avantage
    {
        $offre = $offre ?? $this->createOffre();

        $avantage = new Avantage();
        $avantage->setNom('Ticket Restaurant');
        $avantage->setDescription('Ticket restaurant de 10 euros');
        $avantage->setType('Financier');
        $avantage->setOffre($offre);
        $this->em->persist($avantage);
        $this->em->flush();

        return $avantage;
    }

    public function testIndex(): void
    {
        $this->createAvantage();

        $this->client->request('GET', '/avantage/');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithSearch(): void
    {
        $this->createAvantage();

        $this->client->request('GET', '/avantage/?q=Ticket');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithTriNom(): void
    {
        $this->createAvantage();

        $this->client->request('GET', '/avantage/?tri=nom&ordre=ASC');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithFiltreType(): void
    {
        $this->createAvantage();

        $this->client->request('GET', '/avantage/?type=Financier');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithFiltreOffre(): void
    {
        $avantage = $this->createAvantage();

        $this->client->request('GET', '/avantage/?offre=' . $avantage->getOffre()->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithAllFilters(): void
    {
        $avantage = $this->createAvantage();

        $this->client->request('GET', '/avantage/?q=Ticket&type=Financier&offre=' . $avantage->getOffre()->getId() . '&tri=type&ordre=ASC');
        $this->assertResponseIsSuccessful();
    }

    public function testShow(): void
    {
        $avantage = $this->createAvantage();

        $this->client->request('GET', '/avantage/' . $avantage->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testNewPage(): void
    {
        $this->client->request('GET', '/avantage/new');
        $this->assertResponseIsSuccessful();
    }

    public function testNewSubmit(): void
    {
        $offre = $this->createOffre();

        $crawler = $this->client->request('GET', '/avantage/new');
        $form = $crawler->selectButton('Créer')->form([
            'avantage[nom]' => 'Prime annuelle',
            'avantage[description]' => 'Prime versée chaque année',
            'avantage[type]' => 'Financier',
            'avantage[offre]' => $offre->getId(),
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/avantage/');
    }

    public function testEditPage(): void
    {
        $avantage = $this->createAvantage();

        $this->client->request('GET', '/avantage/' . $avantage->getId() . '/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testEditSubmit(): void
    {
        $avantage = $this->createAvantage();

        $crawler = $this->client->request('GET', '/avantage/' . $avantage->getId() . '/edit');
        $form = $crawler->selectButton('Enregistrer')->form([
            'avantage[nom]' => 'Nom modifié',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/avantage/');
    }

    public function testDelete(): void
    {
        $avantage = $this->createAvantage();
        $id = $avantage->getId();

        $crawler = $this->client->request('GET', '/avantage/' . $id);
        $form = $crawler->selectButton('Supprimer')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/avantage/');
    }
}
