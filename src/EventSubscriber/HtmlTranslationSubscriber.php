<?php

namespace App\EventSubscriber;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class HtmlTranslationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = (string) $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $html = (string) $response->getContent();
        if ($html === '') {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->getLocale();

        $cookieLocale = $request->cookies->get('_locale');
        if (is_string($cookieLocale) && $cookieLocale !== '') {
            $locale = strtolower(substr($cookieLocale, 0, 2));
        }

        if (preg_match('/<html[^>]*\slang="([a-zA-Z_-]+)"/i', $html, $match) === 1) {
            $locale = strtolower(substr($match[1], 0, 2));
        }

        // Only auto-translate when English is selected.
        if ($locale !== 'en') {
            return;
        }

        $translated = $this->localeSwitcher->runWithLocale($locale, function () use ($html, $locale): ?string {
            return $this->translateHtml($html, $locale);
        });

        if ($translated !== null) {
            $response->setContent($translated);
        }
    }

    private function translateHtml(string $html, string $locale): ?string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            if (!$loaded) {
                return null;
            }

            $xpath = new DOMXPath($dom);
            $cache = [];

            $textNodes = $xpath->query('//text()[normalize-space() != "" and not(ancestor::script) and not(ancestor::style)]');
            if ($textNodes !== false) {
                foreach ($textNodes as $node) {
                    if (!$node instanceof DOMNode) {
                        continue;
                    }

                    $original = $node->nodeValue ?? '';
                    $node->nodeValue = $this->translatePreservingWhitespace($original, $locale, $cache);
                }
            }

            $attributeNodes = $xpath->query('//*[@placeholder or @title or @aria-label or @alt or @value or @content]');
            if ($attributeNodes !== false) {
                foreach ($attributeNodes as $node) {
                    if (!$node instanceof DOMElement) {
                        continue;
                    }

                    foreach (['placeholder', 'title', 'aria-label', 'alt', 'value', 'content'] as $attribute) {
                        if (!$node->hasAttribute($attribute)) {
                            continue;
                        }

                        $value = $node->getAttribute($attribute);
                        if ($value === '' || $this->looksLikeUrlOrCode($value)) {
                            continue;
                        }

                        $node->setAttribute($attribute, $this->translatePreservingWhitespace($value, $locale, $cache));
                    }
                }
            }

            $result = $dom->saveHTML();
            if ($result === false) {
                return null;
            }

            return preg_replace('/^<\?xml encoding="UTF-8"\?>/i', '', $result) ?? $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @param array<string, string> $cache
     */
    private function translatePreservingWhitespace(string $text, string $locale, array &$cache): string
    {
        if ($text === '' || $this->looksLikeUrlOrCode($text)) {
            return $text;
        }

        if (!preg_match('/\p{L}/u', $text)) {
            return $text;
        }

        preg_match('/^\s*/u', $text, $leadingMatch);
        preg_match('/\s*$/u', $text, $trailingMatch);

        $leading = $leadingMatch[0] ?? '';
        $trailing = $trailingMatch[0] ?? '';
        $core = trim($text);

        if ($core === '') {
            return $text;
        }

        $cacheKey = $locale . '|' . $core;
        if (!array_key_exists($cacheKey, $cache)) {
            $translated = $this->translator->trans($core, [], 'site', $locale);
            $cache[$cacheKey] = $translated;
        }

        return $leading . $cache[$cacheKey] . $trailing;
    }

    private function looksLikeUrlOrCode(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return true;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, '/')) {
            return true;
        }

        if (str_contains($trimmed, '{{') || str_contains($trimmed, '}}') || str_contains($trimmed, '{%')) {
            return true;
        }

        if (preg_match('/^(fa[srlbd]?\s+fa-|btn-|tf-|col-|row|d-)/i', $trimmed)) {
            return true;
        }

        return false;
    }
}
