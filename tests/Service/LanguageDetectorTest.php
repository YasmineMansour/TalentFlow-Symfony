<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\LanguageDetector;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service LanguageDetector.
 *
 * Matrice de couverture :
 * ┌─────────────────────────────────────────────┬──────────────────────────────┐
 * │ Test                                        │ Ce qui est vérifié           │
 * ├─────────────────────────────────────────────┼──────────────────────────────┤
 * │ testDetectsArabicText                       │ Texte arabe → 'ar'           │
 * │ testDetectsFrenchText                       │ Texte FR avec accents → 'fr' │
 * │ testDetectsEnglishText                      │ Texte EN uniquement → 'en'   │
 * │ testEmptyTextDefaultsFrench                 │ Vide → 'fr' (défaut)         │
 * │ testGetLabelForKnownLanguages               │ Labels corrects fr/en/ar     │
 * │ testGetLabelForUnknownFallsBackToCode       │ Code inconnu → retourné tel  │
 * │ testMixedFrenchEnglishPrefersMostScored     │ Mélange → langue dominante   │
 * └─────────────────────────────────────────────┴──────────────────────────────┘
 */
final class LanguageDetectorTest extends TestCase
{
    private LanguageDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new LanguageDetector();
    }

    public function testDetectsArabicText(): void
    {
        $this->assertSame('ar', $this->detector->detect('مرحباً بكم في منصة تالنت فلو للتوظيف'));
    }

    public function testDetectsFrenchText(): void
    {
        // Text with FR accent chars and common FR words
        $this->assertSame('fr', $this->detector->detect(
            'Bonjour, je suis développeur et je cherche un emploi en informatique.'
        ));
    }

    public function testDetectsEnglishText(): void
    {
        $this->assertSame('en', $this->detector->detect(
            'Hello, I am a software engineer looking for a job in web development.'
        ));
    }

    public function testEmptyTextDefaultsFrench(): void
    {
        $this->assertSame('fr', $this->detector->detect(''));
        $this->assertSame('fr', $this->detector->detect('   '));
    }

    public function testGetLabelForKnownLanguages(): void
    {
        $this->assertSame('Français', $this->detector->getLabel('fr'));
        $this->assertSame('English', $this->detector->getLabel('en'));
        $this->assertSame('العربية', $this->detector->getLabel('ar'));
    }

    public function testGetLabelForUnknownFallsBackToCode(): void
    {
        $this->assertSame('de', $this->detector->getLabel('de'));
    }

    public function testMixedFrenchEnglishPrefersMostScored(): void
    {
        // Heavily French text should return 'fr'
        $fr = 'Le candidat est très motivé et il cherche un poste en développement logiciel. Il a de l\'expérience.';
        $this->assertSame('fr', $this->detector->detect($fr));

        // Heavily English text
        $en = 'The candidate is very motivated and is looking for a software development position. They have experience.';
        $this->assertSame('en', $this->detector->detect($en));
    }
}
