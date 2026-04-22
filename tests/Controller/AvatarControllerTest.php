<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AvatarControllerTest extends WebTestCase
{
    // --- /avatar/initials/{initials} ---

    public function testInitialsEndpointReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/avatar/initials/AB');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(200);
    }

    public function testInitialsEndpointReturnsSvgContentType(): void
    {
        $client = static::createClient();
        $client->request('GET', '/avatar/initials/JD');

        $this->assertResponseHeaderSame('Content-Type', 'image/svg+xml');
    }

    public function testInitialsEndpointReturnsSvgBody(): void
    {
        $client = static::createClient();
        $client->request('GET', '/avatar/initials/MC');

        $content = $client->getResponse()->getContent();
        $this->assertStringStartsWith('<svg', $content);
        $this->assertStringContainsString('MC', $content);
    }

    public function testInitialsAreLimitedToTwoChars(): void
    {
        $client = static::createClient();
        $client->request('GET', '/avatar/initials/ABCDE');

        // Controller limits to 2 chars; result should only show the first 2
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('AB', $content);
        $this->assertStringNotContainsString('ABCDE', $content);
    }

    public function testInitialsAreUppercased(): void
    {
        $client = static::createClient();
        $client->request('GET', '/avatar/initials/ab');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('AB', $content);
    }

    public function testSpecialCharsAreStripped(): void
    {
        $client = static::createClient();
        // Numbers and symbols should be stripped, leaving '?'
        $client->request('GET', '/avatar/initials/123');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('?', $content);
    }

    public function testCacheControlHeaderIsSet(): void
    {
        $client = static::createClient();
        $client->request('GET', '/avatar/initials/TU');

        $this->assertResponseHasHeader('Cache-Control');
    }
}
