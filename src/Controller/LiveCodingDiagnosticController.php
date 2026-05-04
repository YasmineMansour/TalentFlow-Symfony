<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entretien;
use App\Service\LiveCodingTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LiveCodingDiagnosticController extends AbstractController
{
    #[Route('/live-coding/test', name: 'app_live_coding_diagnostic', methods: ['GET'])]
    public function test(LiveCodingTokenService $tokenService, Request $request): JsonResponse
    {
        // Endpoint public pour diagnostiquer la disponibilité de l'API
        $scheme = $request->isSecure() ? 'wss' : 'ws';
        $hostWithPort = $request->getHost();
        // Ensure port 8081 is added for WebSocket
        if (strpos($hostWithPort, ':') === false) {
            $hostWithPort = $hostWithPort . ':8081';
        }
        
        return $this->json([
            'ok' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'service' => 'Live Coding API',
            'status' => 'operational',
            'websocket_url' => sprintf('%s://%s', $scheme, $hostWithPort),
            'endpoints' => [
                'room' => '/mon-entretien/{id}/live-coding (requires auth)',
                'popup' => '/live-coding/popup/{id}',
                'token' => '/mon-entretien/{id}/live-coding/token (requires auth)',
                'run' => '/mon-entretien/{id}/live-coding/run (requires auth)',
                'test' => '/live-coding/test (public)',
            ],
            'sample_token' => [
                'token' => $tokenService->createToken(1, 1),
                'entretienId' => 1,
                'userId' => 1,
            ],
        ]);
    }

    #[Route('/live-coding/popup/{id}', name: 'app_live_coding_popup', methods: ['GET'])]
    public function popup(int $id, Request $request): Response
    {
        // Public endpoint for opening live coding in a popup
        // Can be opened from external applications like Teams
        return $this->render('live_coding/popup.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/live-coding/join/{id}', name: 'app_live_coding_join', methods: ['GET'])]
    public function join(int $id, Request $request): Response
    {
        // Public page for candidates to join live coding (with authentication check)
        // If not logged in, they will be prompted to authenticate
        return $this->render('live_coding/join.html.twig', [
            'id' => $id,
            'baseUrl' => $request->getSchemeAndHttpHost(),
        ]);
    }
}

