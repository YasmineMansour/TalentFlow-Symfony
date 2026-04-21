<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Candidature;
use App\Entity\Entreprise;
use App\Entity\Offre;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CandidatureAnalysisApiControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAdminCanAccessAnalyseEndpoint(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['admin']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyOne']->getId() . '/analyse');

        $this->assertResponseIsSuccessful();

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('data', $payload);

        $this->assertArrayHasKey('summary', $payload['data']);
        $this->assertArrayHasKey('scores', $payload['data']);
        $this->assertArrayHasKey('decision', $payload['data']);
        $this->assertArrayHasKey('issues', $payload['data']);
        $this->assertArrayHasKey('actions', $payload['data']);
        $this->assertArrayHasKey('ai', $payload['data']);

        $this->assertArrayHasKey('recommendedStatus', $payload['data']['summary']);
        $this->assertArrayHasKey('matching', $payload['data']['scores']);
        $this->assertArrayHasKey('canMoveToRhValidation', $payload['data']['decision']);
        $this->assertArrayHasKey('blockingReasons', $payload['data']['issues']);
        $this->assertArrayHasKey('summary', $payload['data']['ai']);
        $this->assertArrayHasKey('candidateMessage', $payload['data']['ai']);
        $this->assertArrayHasKey('rhSummary', $payload['data']['ai']);
        $this->assertArrayHasKey('recommendations', $payload['data']['ai']);

        $this->assertArrayHasKey('analysis', $payload['data']);
        $this->assertArrayHasKey('matchingScore', $payload['data']['analysis']);
        $this->assertArrayHasKey('completenessScore', $payload['data']['analysis']);
        $this->assertArrayHasKey('priorityScore', $payload['data']['analysis']);
        $this->assertArrayHasKey('recommendedStatus', $payload['data']['analysis']);
    }

    public function testRhCanAccessAllowedCompanyCandidature(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['rhCompanyOne']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyOne']->getId() . '/analyse');

        $this->assertResponseIsSuccessful();
    }

    public function testRhCannotAccessOutsideCompanyCandidature(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['rhCompanyOne']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyTwo']->getId() . '/analyse');

        $this->assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertFalse($payload['success']);
    }

    public function testCandidatCanAccessOwnCandidature(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['candidatOne']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyOne']->getId() . '/analyse');

        $this->assertResponseIsSuccessful();
    }

    public function testCandidatCannotAccessOtherUserCandidature(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['candidatOne']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyTwo']->getId() . '/analyse');

        $this->assertResponseStatusCodeSame(403);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertFalse($payload['success']);
    }

    public function testUnknownCandidatureReturns404(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['admin']);
        $this->client->request('GET', '/api/candidatures/99999999/analyse');

        $this->assertResponseStatusCodeSame(404);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertFalse($payload['success']);
    }

    /**
     * @return array<string,mixed>
     */
    private function createScenario(): array
    {
        $uniq = uniqid('', true);

        $entrepriseOne = (new Entreprise())->setNom('Entreprise One ' . $uniq);
        $entrepriseTwo = (new Entreprise())->setNom('Entreprise Two ' . $uniq);
        $this->em->persist($entrepriseOne);
        $this->em->persist($entrepriseTwo);

        $admin = (new User())
            ->setNom('Admin')
            ->setPrenom('User')
            ->setEmail('admin-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_ADMIN']);

        $rhCompanyOne = (new User())
            ->setNom('Rh')
            ->setPrenom('One')
            ->setEmail('rh1-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_RH'])
            ->setEntreprise($entrepriseOne);

        $rhCompanyTwo = (new User())
            ->setNom('Rh')
            ->setPrenom('Two')
            ->setEmail('rh2-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_RH'])
            ->setEntreprise($entrepriseTwo);

        $candidatOne = (new User())
            ->setNom('Candidat')
            ->setPrenom('One')
            ->setEmail('cand1-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_CANDIDAT']);

        $candidatTwo = (new User())
            ->setNom('Candidat')
            ->setPrenom('Two')
            ->setEmail('cand2-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_CANDIDAT']);

        $this->em->persist($admin);
        $this->em->persist($rhCompanyOne);
        $this->em->persist($rhCompanyTwo);
        $this->em->persist($candidatOne);
        $this->em->persist($candidatTwo);

        $offreOne = $this->createOffre($entrepriseOne, 'Offre One ' . $uniq);
        $offreTwo = $this->createOffre($entrepriseTwo, 'Offre Two ' . $uniq);

        $candidatureCompanyOne = $this->createCandidature($offreOne, $candidatOne, 'cand1-' . $uniq . '@mail.local');
        $candidatureCompanyTwo = $this->createCandidature($offreTwo, $candidatTwo, 'cand2-' . $uniq . '@mail.local');

        $this->em->flush();

        return [
            'admin' => $admin,
            'rhCompanyOne' => $rhCompanyOne,
            'rhCompanyTwo' => $rhCompanyTwo,
            'candidatOne' => $candidatOne,
            'candidatTwo' => $candidatTwo,
            'candidatureCompanyOne' => $candidatureCompanyOne,
            'candidatureCompanyTwo' => $candidatureCompanyTwo,
        ];
    }

    private function createOffre(Entreprise $entreprise, string $title): Offre
    {
        $offre = new Offre();
        $offre->setTitre($title);
        $offre->setDescription('Description ' . $title);
        $offre->setLocalisation('Tunis');
        $offre->setTypeContrat('CDI');
        $offre->setModeTravail('ON_SITE');
        $offre->setSalaireMin(1500);
        $offre->setSalaireMax(3000);
        $offre->setEntreprise($entreprise);

        $this->em->persist($offre);

        return $offre;
    }

    private function createCandidature(Offre $offre, User $candidat, string $email): Candidature
    {
        $candidature = new Candidature();
        $candidature->setOffre($offre);
        $candidature->setCandidat($candidat);
        $candidature->setTitrePoste($offre->getTitre() ?? 'Poste test');
        $candidature->setEntreprise($offre->getEntreprise()?->getNom() ?? 'Entreprise test');
        $candidature->setTypeContrat('CDI');
        $candidature->setStatut('En attente');
        $candidature->setEmail($email);
        $candidature->setTelephone('22345678');
        $candidature->setCompetences('PHP, Symfony');
        $candidature->setNiveauEtudes('Bac+5');
        $candidature->setAnneesExperience(3);
        $candidature->setMatchingScore(70);

        $this->em->persist($candidature);

        return $candidature;
    }
}
