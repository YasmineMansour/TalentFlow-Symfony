<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_LOCALES = ['fr', 'en'];

    private string $defaultLocale;
    private LocaleSwitcher $localeSwitcher;

    public function __construct(LocaleSwitcher $localeSwitcher, string $defaultLocale = 'fr')
    {
        $this->localeSwitcher = $localeSwitcher;
        $this->defaultLocale = $defaultLocale;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run before Symfony LocaleListener (16) and LocaleAwareListener (15).
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $locale = $request->attributes->get('locale');
        if (!is_string($locale) || !in_array($locale, self::ALLOWED_LOCALES, true)) {
            $locale = null;
        }

        if ($locale === null) {
            $cookieLocale = $request->cookies->get('_locale');
            if (is_string($cookieLocale) && in_array($cookieLocale, self::ALLOWED_LOCALES, true)) {
                $locale = $cookieLocale;
            }
        }

        if ($locale === null && $request->hasSession()) {
            $sessionLocale = $request->getSession()->get('_locale');
            if (is_string($sessionLocale) && in_array($sessionLocale, self::ALLOWED_LOCALES, true)) {
                $locale = $sessionLocale;
            }
        }

        if ($locale === null) {
            $locale = $this->defaultLocale;
        }

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }

        // Keep Symfony's internal locale attribute consistent for downstream listeners.
        $request->attributes->set('_locale', $locale);
        $this->localeSwitcher->setLocale($locale);
        $request->setLocale($locale);
    }
}
