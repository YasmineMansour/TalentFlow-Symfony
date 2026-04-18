<?php

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Entreprise;
use App\Entity\Offre;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OffreControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setNom('Test');
        $user->setPrenom('User');
        $user->setEmail('test-' . uniqid() . '@test.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_ADMIN']);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    private function createOffre(): Offre
    {
        $offre = new Offre();
        $offre->setTitre('Développeur PHP Senior');
        $offre->setDescription('Nous recherchons un développeur PHP expérimenté');
        $offre->setLocalisation('Tunis');
        $offre->setTypeContrat('CDI');
        $offre->setModeTravail('ON_SITE');
        $offre->setSalaireMin(2000);
        $offre->setSalaireMax(4000);
        $this->em->persist($offre);
        $this->em->flush();

        return $offre;
    }

    public function testIndex(): void
    {
        $this->createOffre();

        $this->client->request('GET', '/offre/');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithSearch(): void
    {
        $this->createOffre();

        $this->client->request('GET', '/offre/?q=PHP');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithTriSalaire(): void
    {
        $this->createOffre();

        $this->client->request('GET', '/offre/?tri=salaireMax&ordre=DESC');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithTriTitre(): void
    {
        $this->createOffre();

        $this->client->request('GET', '/offre/?tri=titre&ordre=ASC');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithFiltreCategorie(): void
    {
        $categorie = new Categorie();
        $categorie->setNom('Dev Web');
        $this->em->persist($categorie);

        $offre = $this->createOffre();
        $offre->setCategorie($categorie);
        $this->em->flush();

        $this->client->request('GET', '/offre/?categorie=' . $categorie->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithFiltreEntreprise(): void
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('TestCorp');
        $this->em->persist($entreprise);

        $offre = $this->createOffre();
        $offre->setEntreprise($entreprise);
        $this->em->flush();

        $this->client->request('GET', '/offre/?entreprise=' . $entreprise->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithAllFilters(): void
    {
        $categorie = new Categorie();
        $categorie->setNom('Dev Web');
        $this->em->persist($categorie);

        $entreprise = new Entreprise();
        $entreprise->setNom('TestCorp');
        $this->em->persist($entreprise);

        $offre = $this->createOffre();
        $offre->setCategorie($categorie);
        $offre->setEntreprise($entreprise);
        $this->em->flush();

        $this->client->request('GET', '/offre/?q=PHP&categorie=' . $categorie->getId() . '&entreprise=' . $entreprise->getId() . '&tri=salaireMax&ordre=ASC');
        $this->assertResponseIsSuccessful();
    }

    public function testShow(): void
    {
        $offre = $this->createOffre();

        $this->client->request('GET', '/offre/' . $offre->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testNewPage(): void
    {
        $this->client->request('GET', '/offre/new');
        $this->assertResponseIsSuccessful();
    }

    public function testNewSubmit(): void
    {
        $crawler = $this->client->request('GET', '/offre/new');
        $form = $crawler->selectButton('Créer')->form([
            'offre[titre]' => 'Nouvelle Offre Test',
            'offre[description]' => 'Description de la nouvelle offre de test',
            'offre[localisation]' => 'Sfax',
            'offre[typeContrat]' => 'CDD',
            'offre[modeTravail]' => 'REMOTE',
            'offre[salaireMin]' => 1500,
            'offre[salaireMax]' => 3000,
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/offre/');
    }

    public function testEditPage(): void
    {
        $offre = $this->createOffre();

        $this->client->request('GET', '/offre/' . $offre->getId() . '/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testEditSubmit(): void
    {
        $offre = $this->createOffre();

        $crawler = $this->client->request('GET', '/offre/' . $offre->getId() . '/edit');
        $form = $crawler->selectButton('Enregistrer')->form([
            'offre[titre]' => 'Titre modifié',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/offre/');
    }

    public function testDelete(): void
    {
        $offre = $this->createOffre();
        $id = $offre->getId();

        $crawler = $this->client->request('GET', '/offre/' . $id);
        $form = $crawler->selectButton('Supprimer')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/offre/');
    }
}
