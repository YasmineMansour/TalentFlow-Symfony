<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Entretien;
use App\Repository\CandidatureRepository;
use App\Service\LiveCodingRunnerService;
use App\Service\LiveCodingSnapshotService;
use App\Service\LiveCodingTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LiveCodingController extends AbstractController
{
    #[Route('/mon-entretien/{id}/live-coding', name: 'app_live_coding_room', methods: ['GET'])]
    public function room(Entretien $entretien, CandidatureRepository $candidatureRepository, Request $request): Response
    {
        $this->assertCanAccessEntretien($entretien, $candidatureRepository);

        $scheme = $request->isSecure() ? 'wss' : 'ws';
        $wsUrl = sprintf('%s://%s:8081', $scheme, $request->getHost());

        return $this->render('live_coding/room.html.twig', [
            'entretien' => $entretien,
            'wsUrl' => $wsUrl,
        ]);
    }

    #[Route('/mon-entretien/{id}/live-coding/token', name: 'app_live_coding_token', methods: ['GET'])]
    public function token(
        Entretien $entretien,
        CandidatureRepository $candidatureRepository,
        LiveCodingTokenService $tokenService,
        LiveCodingSnapshotService $snapshotService,
        Request $request
    ): JsonResponse {
        $this->assertCanAccessEntretien($entretien, $candidatureRepository);

        $user = $this->getUser();
        if (!method_exists($user, 'getId')) {
            return $this->json(['error' => 'Utilisateur invalide'], 403);
        }

        $token = $tokenService->createToken($entretien->getId() ?? 0, (int) $user->getId());
        $scheme = $request->isSecure() ? 'wss' : 'ws';

        $userName = method_exists($user, 'getFullName') ? trim((string) $user->getFullName()) : '';
        if ($userName === '' && method_exists($user, 'getUserIdentifier')) {
            $userName = (string) $user->getUserIdentifier();
        }

        $roleLabel = method_exists($user, 'getRoleLabel') ? (string) $user->getRoleLabel() : 'Participant';

        return $this->json([
            'token' => $token,
            'entretienId' => $entretien->getId(),
            'wsUrl' => sprintf('%s://%s:8081', $scheme, $request->getHost()),
            'initialCode' => $snapshotService->loadCode($entretien->getId() ?? 0),
            'userName' => $userName !== '' ? $userName : 'Participant',
            'roleLabel' => $roleLabel,
        ]);
    }

    #[Route('/mon-entretien/{id}/live-coding/run', name: 'app_live_coding_run', methods: ['POST'])]
    public function run(
        Entretien $entretien,
        CandidatureRepository $candidatureRepository,
        Request $request,
        LiveCodingRunnerService $runnerService
    ): JsonResponse {
        $this->assertCanAccessEntretien($entretien, $candidatureRepository);

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'Payload invalide'], 400);
        }

        $language = strtolower(trim((string) ($payload['language'] ?? '')));
        $code = (string) ($payload['code'] ?? '');
        $stdin = (string) ($payload['stdin'] ?? '');

        if (!in_array($language, ['php', 'python', 'javascript'], true)) {
            return $this->json(['ok' => false, 'error' => 'Langage non supporte'], 400);
        }

        if ($code === '' || mb_strlen($code) > 50000) {
            return $this->json(['ok' => false, 'error' => 'Code vide ou trop volumineux'], 400);
        }

        return $this->json($runnerService->execute($language, $code, $stdin));
    }

    private function assertCanAccessEntretien(Entretien $entretien, CandidatureRepository $candidatureRepository): void
    {
        if ($entretien->getType() !== 'EN_LIGNE') {
            throw $this->createAccessDeniedException('Live coding disponible uniquement pour entretien en ligne.');
        }

        if ($this->isGranted('ROLE_RH')) {
            return;
        }

        $user = $this->getUser();
        if (!method_exists($user, 'getId')) {
            throw $this->createAccessDeniedException('Utilisateur invalide.');
        }

        $candidature = $candidatureRepository->find($entretien->getCandidatureId() ?? 0);
        if ($candidature === null || $candidature->getCandidat()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Cet entretien ne vous appartient pas.');
        }
    }
}
