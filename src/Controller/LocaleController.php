<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/switch-locale/{locale}', name: 'app_switch_locale', methods: ['GET'])]
    public function switchLocale(string $locale, Request $request): Response
    {
        $allowedLocales = ['fr', 'en'];

        if (!in_array($locale, $allowedLocales, true)) {
            $locale = 'fr';
        }

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }

        $request->setLocale($locale);

        // Redirect back to the previous page on the same host, or fallback.
        $referer = $request->headers->get('referer');
        $fallback = $this->generateUrl('public_home');

        if (is_string($referer) && $referer !== '') {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost === null || $refererHost === $request->getHost()) {
                $fallback = $referer;
            }
        }

        $response = $this->redirect($fallback);
        $response->headers->setCookie(
            Cookie::create('_locale')
                ->withValue($locale)
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        return $response;
    }
}
