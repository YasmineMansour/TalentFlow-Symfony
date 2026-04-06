<?php

namespace App\Tests\Controller;

use App\Entity\Categorie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategorieControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    private function createCategorie(): Categorie
    {
        $categorie = new Categorie();
        $categorie->setNom('Développement Web');
        $categorie->setDescription('Catégorie pour les développeurs web et mobile');
        $this->em->persist($categorie);
        $this->em->flush();

        return $categorie;
    }

    public function testIndex(): void
    {
        $this->createCategorie();

        $this->client->request('GET', '/categorie/');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithSearch(): void
    {
        $this->createCategorie();

        $this->client->request('GET', '/categorie/?q=Web');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithTriNom(): void
    {
        $this->createCategorie();

        $this->client->request('GET', '/categorie/?tri=nom&ordre=ASC');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithAllFilters(): void
    {
        $this->createCategorie();

        $this->client->request('GET', '/categorie/?q=Dev&tri=nom&ordre=DESC');
        $this->assertResponseIsSuccessful();
    }

    public function testShow(): void
    {
        $categorie = $this->createCategorie();

        $this->client->request('GET', '/categorie/' . $categorie->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testNewPage(): void
    {
        $this->client->request('GET', '/categorie/new');
        $this->assertResponseIsSuccessful();
    }

    public function testNewSubmit(): void
    {
        $crawler = $this->client->request('GET', '/categorie/new');
        $form = $crawler->selectButton('Créer')->form([
            'categorie[nom]' => 'Data Science',
            'categorie[description]' => 'Catégorie pour les data scientists et analystes',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/categorie/');
    }

    public function testEditPage(): void
    {
        $categorie = $this->createCategorie();

        $this->client->request('GET', '/categorie/' . $categorie->getId() . '/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testEditSubmit(): void
    {
        $categorie = $this->createCategorie();

        $crawler = $this->client->request('GET', '/categorie/' . $categorie->getId() . '/edit');
        $form = $crawler->selectButton('Enregistrer')->form([
            'categorie[nom]' => 'Nom modifié',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/categorie/');
    }

    public function testDelete(): void
    {
        $categorie = $this->createCategorie();
        $id = $categorie->getId();

        $crawler = $this->client->request('GET', '/categorie/' . $id);
        $form = $crawler->selectButton('Supprimer')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/categorie/');
    }
}
