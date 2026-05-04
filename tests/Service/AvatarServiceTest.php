<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\AvatarService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AvatarServiceTest extends TestCase
{
    private UrlGeneratorInterface $urlGenerator;
    private AvatarService $service;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->service = new AvatarService($this->urlGenerator);
    }

    // --- getInitials ---

    public function testGetInitialsReturnsTwoUppercaseLetters(): void
    {
        $user = $this->makeUser('Alice', 'Dupont');
        $this->assertSame('AD', $this->service->getInitials($user));
    }

    public function testGetInitialsLowercaseInputIsUppercased(): void
    {
        $user = $this->makeUser('jean', 'martin');
        $this->assertSame('JM', $this->service->getInitials($user));
    }

    public function testGetInitialsWithAccentedCharacters(): void
    {
        $user = $this->makeUser('Élise', 'Übach');
        $this->assertSame('ÉÜ', $this->service->getInitials($user));
    }

    public function testGetInitialsEmptyNamesFallsBack(): void
    {
        $user = $this->makeUser('', '');
        // Both empty → empty string, then '?' fallback
        $result = $this->service->getInitials($user);
        $this->assertSame('?', $result);
    }

    public function testGetInitialsOnlyFirstName(): void
    {
        $user = $this->makeUser('Bob', '');
        $this->assertSame('B', $this->service->getInitials($user));
    }

    // --- buildSvg ---

    public function testBuildSvgReturnsSvgString(): void
    {
        $svg = $this->service->buildSvg('AB');
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function testBuildSvgContainsInitials(): void
    {
        $svg = $this->service->buildSvg('JD');
        $this->assertStringContainsString('JD', $svg);
    }

    public function testBuildSvgUsesGivenSize(): void
    {
        $svg = $this->service->buildSvg('AB', 120);
        $this->assertStringContainsString('width="120"', $svg);
        $this->assertStringContainsString('height="120"', $svg);
    }

    public function testBuildSvgDefaultSizeIs80(): void
    {
        $svg = $this->service->buildSvg('AB');
        $this->assertStringContainsString('width="80"', $svg);
    }

    public function testBuildSvgEscapesSpecialCharacters(): void
    {
        // '<' and '>' must be escaped to XML entities in the SVG text node
        $svg = $this->service->buildSvg('<>');
        $this->assertStringContainsString('&lt;&gt;', $svg);
        // Raw unescaped angle brackets must not appear inside the text node
        $this->assertDoesNotMatchRegularExpression('|<text[^>]*>[^<]*<[^/]|', $svg);
    }

    public function testBuildSvgIsDeterministicForSameInitials(): void
    {
        $svg1 = $this->service->buildSvg('MK');
        $svg2 = $this->service->buildSvg('MK');
        $this->assertSame($svg1, $svg2);
    }

    // --- generateInitialsSvg ---

    public function testGenerateInitialsSvgUsesUserInitials(): void
    {
        $user = $this->makeUser('Marie', 'Curie');
        $svg = $this->service->generateInitialsSvg($user);
        $this->assertStringContainsString('MC', $svg);
    }

    // --- getAvatarUrl ---

    public function testGetAvatarUrlReturnsGravatarUrl(): void
    {
        $user = $this->makeUser('Test', 'User');
        $user->setEmail('test@example.com');

        $this->urlGenerator
            ->method('generate')
            ->willReturn('http://localhost/avatar/initials/TU');

        $url = $this->service->getAvatarUrl($user);

        $this->assertStringStartsWith('https://www.gravatar.com/avatar/', $url);
        $this->assertStringContainsString('gravatar.com', $url);
    }

    public function testGetAvatarUrlContainsSizeParameter(): void
    {
        $user = $this->makeUser('Test', 'User');
        $user->setEmail('test@example.com');

        $this->urlGenerator
            ->method('generate')
            ->willReturn('http://localhost/avatar/initials/TU');

        $url = $this->service->getAvatarUrl($user, 64);
        $this->assertStringContainsString('s=64', $url);
    }

    // --- helpers ---

    private function makeUser(string $prenom, string $nom): User
    {
        $user = new User();
        $user->setPrenom($prenom);
        $user->setNom($nom);
        $user->setEmail('avatar-test@example.com');
        $user->setPassword('x');
        return $user;
    }
}
