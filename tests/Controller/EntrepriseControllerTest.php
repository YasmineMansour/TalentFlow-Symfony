<?php

namespace App\Tests\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EntrepriseControllerTest extends WebTestCase
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

    private function createEntreprise(): Entreprise
    {
        $entreprise = new Entreprise();
        $entreprise->setNom('TechCorp');
        $entreprise->setSecteur('Informatique');
        $entreprise->setAdresse('10 Rue de la Paix, Tunis');
        $entreprise->setEmail('contact@techcorp.com');
        $entreprise->setTelephone('+216 71 000 000');
        $entreprise->setDescription('Entreprise spécialisée dans le développement logiciel');
        $this->em->persist($entreprise);
        $this->em->flush();

        return $entreprise;
    }

    public function testIndex(): void
    {
        $this->createEntreprise();

        $this->client->request('GET', '/entreprise/');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithSearch(): void
    {
        $this->createEntreprise();

        $this->client->request('GET', '/entreprise/?q=Tech');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithTriNom(): void
    {
        $this->createEntreprise();

        $this->client->request('GET', '/entreprise/?tri=nom&ordre=ASC');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithFiltreSecteur(): void
    {
        $this->createEntreprise();

        $this->client->request('GET', '/entreprise/?secteur=Informatique');
        $this->assertResponseIsSuccessful();
    }

    public function testIndexWithAllFilters(): void
    {
        $this->createEntreprise();

        $this->client->request('GET', '/entreprise/?q=Tech&secteur=Informatique&tri=nom&ordre=DESC');
        $this->assertResponseIsSuccessful();
    }

    public function testShow(): void
    {
        $entreprise = $this->createEntreprise();

        $this->client->request('GET', '/entreprise/' . $entreprise->getId());
        $this->assertResponseIsSuccessful();
    }

    public function testNewPage(): void
    {
        $this->client->request('GET', '/entreprise/new');
        $this->assertResponseIsSuccessful();
    }

    public function testNewSubmit(): void
    {
        $crawler = $this->client->request('GET', '/entreprise/new');
        $form = $crawler->selectButton('Créer')->form([
            'entreprise[nom]' => 'NouvelleEntreprise',
            'entreprise[secteur]' => 'Finance',
            'entreprise[adresse]' => '20 Avenue Bourguiba, Tunis',
            'entreprise[email]' => 'info@nouvelle.com',
            'entreprise[telephone]' => '+216 72 000 000',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/entreprise/');
    }

    public function testEditPage(): void
    {
        $entreprise = $this->createEntreprise();

        $this->client->request('GET', '/entreprise/' . $entreprise->getId() . '/edit');
        $this->assertResponseIsSuccessful();
    }

    public function testEditSubmit(): void
    {
        $entreprise = $this->createEntreprise();

        $crawler = $this->client->request('GET', '/entreprise/' . $entreprise->getId() . '/edit');
        $form = $crawler->selectButton('Enregistrer')->form([
            'entreprise[nom]' => 'Nom modifié',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/entreprise/');
    }

    public function testDelete(): void
    {
        $entreprise = $this->createEntreprise();
        $id = $entreprise->getId();

        $crawler = $this->client->request('GET', '/entreprise/' . $id);
        $form = $crawler->selectButton('Supprimer')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/entreprise/');
    }
}
