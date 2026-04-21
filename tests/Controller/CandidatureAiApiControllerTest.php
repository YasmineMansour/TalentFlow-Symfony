<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Candidature;
use App\Entity\Entreprise;
use App\Entity\Offre;
use App\Entity\User;
use App\Service\CandidatureAiRecommendationService;
use App\Service\OpenAiCandidatureRecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CandidatureAiApiControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $responseJson = '{"candidateMessage":"Message candidat test.","rhSummary":"Resume RH test.","recommendations":["Ajouter un CV a jour."]}';
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [
                    [
                        'content' => [
                            ['text' => $responseJson],
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE)),
        ]);

        $service = new OpenAiCandidatureRecommendationService(
            $httpClient,
            new NullLogger(),
            new CandidatureAiRecommendationService(),
            'test-key',
            'gpt-test',
            'https://api.openai.com/v1',
            '',
            '',
        );

        static::getContainer()->set(OpenAiCandidatureRecommendationService::class, $service);
    }

    public function testAdminCanAccessAiRecommendationEndpoint(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['admin']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyOne']->getId() . '/ai-recommendation');

        $this->assertResponseIsSuccessful();

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertTrue($payload['success']);
        $this->assertSame('openai', $payload['source']);
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('candidateMessage', $payload['data']);
        $this->assertArrayHasKey('rhSummary', $payload['data']);
        $this->assertArrayHasKey('recommendations', $payload['data']);
    }

    public function testRhCannotAccessOutsideCompanyCandidatureForAiEndpoint(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['rhCompanyOne']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyTwo']->getId() . '/ai-recommendation');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCandidatCannotAccessOtherUserCandidatureForAiEndpoint(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['candidatOne']);
        $this->client->request('GET', '/api/candidatures/' . $fixtures['candidatureCompanyTwo']->getId() . '/ai-recommendation');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnknownCandidatureReturns404ForAiEndpoint(): void
    {
        $fixtures = $this->createScenario();

        $this->client->loginUser($fixtures['admin']);
        $this->client->request('GET', '/api/candidatures/99999999/ai-recommendation');

        $this->assertResponseStatusCodeSame(404);
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
            ->setEmail('admin-ai-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_ADMIN']);

        $rhCompanyOne = (new User())
            ->setNom('Rh')
            ->setPrenom('One')
            ->setEmail('rh-ai-1-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_RH'])
            ->setEntreprise($entrepriseOne);

        $candidatOne = (new User())
            ->setNom('Candidat')
            ->setPrenom('One')
            ->setEmail('cand-ai-1-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_CANDIDAT']);

        $candidatTwo = (new User())
            ->setNom('Candidat')
            ->setPrenom('Two')
            ->setEmail('cand-ai-2-' . $uniq . '@test.local')
            ->setPassword('password')
            ->setRoles(['ROLE_CANDIDAT']);

        $this->em->persist($admin);
        $this->em->persist($rhCompanyOne);
        $this->em->persist($candidatOne);
        $this->em->persist($candidatTwo);

        $offreOne = $this->createOffre($entrepriseOne, 'Offre One AI ' . $uniq);
        $offreTwo = $this->createOffre($entrepriseTwo, 'Offre Two AI ' . $uniq);

        $candidatureCompanyOne = $this->createCandidature($offreOne, $candidatOne, 'cand-ai-1-' . $uniq . '@mail.local');
        $candidatureCompanyTwo = $this->createCandidature($offreTwo, $candidatTwo, 'cand-ai-2-' . $uniq . '@mail.local');

        $this->em->flush();

        return [
            'admin' => $admin,
            'rhCompanyOne' => $rhCompanyOne,
            'candidatOne' => $candidatOne,
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
