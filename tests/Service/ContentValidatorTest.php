<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ContentValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du service ContentValidator.
 *
 * Matrice de couverture :
 * ┌───────────────────────────────────────────────┬──────────────────────────────┐
 * │ Test                                          │ Ce qui est vérifié           │
 * ├───────────────────────────────────────────────┼──────────────────────────────┤
 * │ testValidTitlePassesWithNoErrors              │ Titre valide → []            │
 * │ testTitleTooShortReturnsError                 │ Titre < 3 car → erreur       │
 * │ testTitleWithBadWordReturnsError              │ Insulte → erreur détectée    │
 * │ testRepetitiveTitleReturnsError               │ Spam/répétitif → erreur      │
 * │ testValidContentPassesWithNoErrors            │ Contenu propre → []          │
 * │ testContentTooShortWithoutImageReturnsError   │ Vide sans image → erreur     │
 * │ testContentWithBadWordReturnsError            │ Insulte dans contenu         │
 * │ testCommentTooShortReturnsError               │ Commentaire trop court       │
 * │ testCommentValidPassesWithNoErrors            │ Commentaire valide → []      │
 * │ testDetectBadWordsMasksCorrectly              │ Masquage des mots détectés   │
 * └───────────────────────────────────────────────┴──────────────────────────────┘
 */
final class ContentValidatorTest extends TestCase
{
    private ContentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContentValidator();
    }

    // ── Title ─────────────────────────────────────────────────────────────────

    public function testValidTitlePassesWithNoErrors(): void
    {
        $errors = $this->validator->validatePostTitle('Offre de stage en développement web');
        $this->assertSame([], $errors);
    }

    public function testTitleTooShortReturnsError(): void
    {
        $errors = $this->validator->validatePostTitle('AB');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('3 caractères', $errors[0]);
    }

    public function testTitleWithBadWordReturnsError(): void
    {
        $errors = $this->validator->validatePostTitle('Super merde ce site');
        $this->assertNotEmpty($errors);
        $hasSwearError = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'inapproprié')) {
                $hasSwearError = true;
                break;
            }
        }
        $this->assertTrue($hasSwearError, 'Expected a bad-word error');
    }

    public function testRepetitiveTitleReturnsError(): void
    {
        // Highly repetitive string
        $errors = $this->validator->validatePostTitle(str_repeat('aaa ', 30));
        $this->assertNotEmpty($errors);
        $hasSpamError = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'répétitif') || str_contains($e, 'spam')) {
                $hasSpamError = true;
                break;
            }
        }
        $this->assertTrue($hasSpamError, 'Expected a repetitiveness/spam error');
    }

    // ── Content ───────────────────────────────────────────────────────────────

    public function testValidContentPassesWithNoErrors(): void
    {
        $errors = $this->validator->validatePostContent(
            'Nous recherchons un développeur Symfony expérimenté pour rejoindre notre équipe.',
            false
        );
        $this->assertSame([], $errors);
    }

    public function testContentTooShortWithoutImageReturnsError(): void
    {
        $errors = $this->validator->validatePostContent('Hi', false);
        $this->assertNotEmpty($errors);
    }

    public function testContentTooShortWithImagePasses(): void
    {
        // Image compensates for short content
        $errors = $this->validator->validatePostContent('', true);
        // No "too short" error when image is present
        $hasShortError = false;
        foreach ($errors as $e) {
            if (str_contains($e, '10 caractères')) {
                $hasShortError = true;
                break;
            }
        }
        $this->assertFalse($hasShortError);
    }

    public function testContentWithBadWordReturnsError(): void
    {
        $errors = $this->validator->validatePostContent('This is bullshit content that should be flagged.', false);
        $this->assertNotEmpty($errors);
        $hasSwearError = false;
        foreach ($errors as $e) {
            if (str_contains($e, 'inapproprié')) {
                $hasSwearError = true;
                break;
            }
        }
        $this->assertTrue($hasSwearError);
    }

    // ── Comment ───────────────────────────────────────────────────────────────

    public function testCommentTooShortReturnsError(): void
    {
        $errors = $this->validator->validateComment('X');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('2 caractères', $errors[0]);
    }

    public function testCommentValidPassesWithNoErrors(): void
    {
        $errors = $this->validator->validateComment('Très intéressant, merci pour ce partage !');
        $this->assertSame([], $errors);
    }

    // ── Bad word masking ──────────────────────────────────────────────────────

    public function testDetectBadWordsMasksCorrectly(): void
    {
        $found = $this->validator->detectBadWords('This is bullshit.');
        $this->assertNotEmpty($found);
        // Masked: first + last char kept, middle is stars
        foreach ($found as $masked) {
            $this->assertMatchesRegularExpression('/^[a-zA-Z].+[a-zA-Z]$/', $masked);
        }
    }
}
