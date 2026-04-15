<?php

namespace App\Controller;

use App\Service\AvatarService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur qui sert des avatars SVG avec les initiales.
 * Appelé en fallback par Gravatar ou directement dans les templates.
 */
class AvatarController extends AbstractController
{
    #[Route('/avatar/initials/{initials}', name: 'app_avatar_initials', methods: ['GET'])]
    public function initials(string $initials, AvatarService $avatarService): Response
    {
        // Limiter à 2 caractères alphanumériques
        $initials = mb_strtoupper(preg_replace('/[^a-zA-ZÀ-ÿ]/u', '', $initials), 'UTF-8');
        $initials = mb_substr($initials, 0, 2, 'UTF-8') ?: '?';

        $svg = $avatarService->buildSvg($initials);

        return new Response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
