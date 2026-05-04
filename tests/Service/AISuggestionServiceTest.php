<?php

namespace App\Tests\Service;

use App\Service\AISuggestionService;
use PHPUnit\Framework\TestCase;

class AISuggestionServiceTest extends TestCase
{
    private AISuggestionService $service;

    protected function setUp(): void
    {
        $this->service = new AISuggestionService();
    }

    // --- suggestAvantages ---

    public function testSuggestAvantagesForDevPost(): void
    {
        $suggestions = $this->service->suggestAvantages('Développeur PHP Symfony', 'CDI', 'REMOTE');
        $this->assertNotEmpty($suggestions);
        $this->assertLessThanOrEqual(8, count($suggestions));

        $noms = array_column($suggestions, 'nom');
        // Devrait inclure des avantages liés au secteur informatique
        $this->assertTrue(
            count(array_filter($noms, fn($n) => str_contains(mb_strtolower($n), 'télétravail') || str_contains(mb_strtolower($n), 'formation') || str_contains(mb_strtolower($n), 'équipement'))) > 0,
            'Devrait proposer des avantages IT (télétravail, formation, ou équipement)'
        );
    }

    public function testSuggestAvantagesForFinancePost(): void
    {
        $suggestions = $this->service->suggestAvantages('Analyste financier', 'CDI', 'ON_SITE', 'Finance');
        $this->assertNotEmpty($suggestions);

        $noms = array_column($suggestions, 'nom');
        $this->assertTrue(
            count(array_filter($noms, fn($n) => str_contains(mb_strtolower($n), 'bonus') || str_contains(mb_strtolower($n), 'assurance') || str_contains(mb_strtolower($n), 'épargne'))) > 0,
            'Devrait proposer des avantages finance'
        );
    }

    public function testSuggestAvantagesExcludesExisting(): void
    {
        $suggestions = $this->service->suggestAvantages(
            'Développeur PHP',
            'CDI',
            null,
            null,
            null,
            ['Télétravail flexible', 'Budget formation']
        );

        $noms = array_map('mb_strtolower', array_column($suggestions, 'nom'));
        $this->assertNotContains('télétravail flexible', $noms);
        $this->assertNotContains('budget formation', $noms);
    }

    public function testSuggestAvantagesForHighSalary(): void
    {
        $suggestions = $this->service->suggestAvantages('Manager', 'CDI', 'ON_SITE', null, 5000);
        $noms = array_column($suggestions, 'nom');
        $this->assertContains('Voiture de fonction', $noms);
    }

    public function testSuggestAvantagesForLowSalary(): void
    {
        $suggestions = $this->service->suggestAvantages('Assistant administratif', 'CDD', 'ON_SITE', null, 800);
        $noms = array_map('mb_strtolower', array_column($suggestions, 'nom'));
        $this->assertTrue(
            in_array('aide au transport', $noms) || in_array('transport pris en charge', $noms),
            'Devrait proposer un avantage transport pour les bas salaires'
        );
    }

    public function testSuggestAvantagesForRemote(): void
    {
        $suggestions = $this->service->suggestAvantages('Développeur', null, 'REMOTE');
        $noms = array_map('mb_strtolower', array_column($suggestions, 'nom'));
        $this->assertTrue(
            in_array('indemnité télétravail', $noms) || in_array('équipement bureau à domicile', $noms),
            'Devrait proposer des avantages Remote'
        );
    }

    public function testSuggestAvantagesForStage(): void
    {
        $suggestions = $this->service->suggestAvantages('Stagiaire Marketing', 'Stage');
        $noms = array_column($suggestions, 'nom');
        $this->assertTrue(
            in_array('Mentorat dédié', $noms) || in_array('Possibilité d\'embauche', $noms),
            'Devrait proposer des avantages Stage'
        );
    }

    public function testSuggestAvantagesHasRaison(): void
    {
        $suggestions = $this->service->suggestAvantages('Développeur Java');
        foreach ($suggestions as $s) {
            $this->assertArrayHasKey('raison', $s);
            $this->assertNotEmpty($s['raison']);
        }
    }

    public function testSuggestAvantagesMaxEight(): void
    {
        $suggestions = $this->service->suggestAvantages('Manager', 'CDI', 'REMOTE', 'informatique', 6000);
        $this->assertLessThanOrEqual(8, count($suggestions));
    }

    // --- improveOffreDescription ---

    public function testImproveOffreDescriptionContainsTitre(): void
    {
        $result = $this->service->improveOffreDescription('Développeur PHP Senior');
        $this->assertStringContainsString('Développeur PHP Senior', $result);
    }

    public function testImproveOffreDescriptionContainsCompetences(): void
    {
        $result = $this->service->improveOffreDescription('Développeur PHP Symfony');
        $this->assertStringContainsString('PHP', $result);
        $this->assertStringContainsString('Profil recherché', $result);
    }

    public function testImproveOffreDescriptionIncludesExistingDescription(): void
    {
        $result = $this->service->improveOffreDescription('Dev', 'Poste intéressant dans une startup');
        $this->assertStringContainsString('Poste intéressant dans une startup', $result);
    }

    public function testImproveOffreDescriptionIncludesContractInfo(): void
    {
        $result = $this->service->improveOffreDescription('Dev', null, 'CDI', 'Tunis', 'REMOTE');
        $this->assertStringContainsString('CDI', $result);
        $this->assertStringContainsString('Tunis', $result);
        $this->assertStringContainsString('Télétravail', $result);
    }

    // --- improveAvantageDescription ---

    public function testImproveAvantageDescriptionContainsNom(): void
    {
        $result = $this->service->improveAvantageDescription('Ticket Restaurant');
        $this->assertStringContainsString('Ticket Restaurant', $result);
    }

    public function testImproveAvantageDescriptionWithType(): void
    {
        $result = $this->service->improveAvantageDescription('Prime annuelle', null, 'Financier');
        $this->assertStringContainsString('💰', $result);
    }

    public function testImproveAvantageDescriptionWithExistingDesc(): void
    {
        $result = $this->service->improveAvantageDescription('Mutuelle', 'Couverture santé complète', 'Bien-être');
        $this->assertStringContainsString('Couverture santé complète', $result);
        $this->assertStringContainsString('🌟', $result);
    }

    public function testImproveAvantageDescriptionEnrichesTicket(): void
    {
        $result = $this->service->improveAvantageDescription('Ticket restaurant', null, 'Financier');
        $this->assertStringContainsString('Détails', $result);
    }
}
