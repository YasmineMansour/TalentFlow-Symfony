<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Service\CandidatureAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/candidatures')]
#[IsGranted('ROLE_USER')]
class CandidatureAnalysisApiController extends AbstractController
{
    #[Route('/{id}/analyse', name: 'api_candidature_analyse', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function analyse(
        int $id,
        CandidatureRepository $candidatureRepository,
        CandidatureAnalysisService $analysisService,
    ): JsonResponse {
        $candidature = $candidatureRepository->find($id);
        if (!$candidature instanceof Candidature) {
            return $this->json([
                'success' => false,
                'message' => 'Candidature introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->canAccessCandidature($user, $candidature)) {
            return $this->json([
                'success' => false,
                'message' => 'Accès interdit à cette candidature.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $analysisService->analyze($candidature),
        ]);
    }

    private function canAccessCandidature(User $user, Candidature $candidature): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if (in_array('ROLE_RH', $user->getRoles(), true)) {
            $rhEntrepriseId = $user->getEntreprise()?->getId();
            $candidatureEntrepriseId = $candidature->getOffre()?->getEntreprise()?->getId();

            if ($rhEntrepriseId === null || $candidatureEntrepriseId === null) {
                return false;
            }

            return $rhEntrepriseId === $candidatureEntrepriseId;
        }

        if (in_array('ROLE_CANDIDAT', $user->getRoles(), true)) {
            return $candidature->getCandidat()?->getId() === $user->getId();
        }

        return false;
    }
}
