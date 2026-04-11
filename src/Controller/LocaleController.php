<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        $request->getSession()->set('_locale', $locale);

        // Redirect back to the previous page, or dashboard if no referer
        $referer = $request->headers->get('referer');

        return $this->redirect($referer ?: $this->generateUrl('app_dashboard'));
    }
}
