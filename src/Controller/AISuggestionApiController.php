<?php

namespace App\Controller;

use App\Service\AISuggestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ai')]
#[IsGranted('ROLE_RH')]
class AISuggestionApiController extends AbstractController
{
    public function __construct(private AISuggestionService $ai)
    {
    }

    /**
     * POST /api/ai/suggest-avantages
     * Body JSON: { titre, typeContrat?, modeTravail?, categorie?, salaireMax?, avantagesExistants?: string[] }
     */
    #[Route('/suggest-avantages', name: 'api_ai_suggest_avantages', methods: ['POST'])]
    public function suggestAvantages(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $titre = $data['titre'] ?? '';
        if (!$titre) {
            return $this->json(['error' => 'Le champ "titre" est requis.'], 400);
        }

        $suggestions = $this->ai->suggestAvantages(
            $titre,
            $data['typeContrat'] ?? null,
            $data['modeTravail'] ?? null,
            $data['categorie'] ?? null,
            isset($data['salaireMax']) ? (float) $data['salaireMax'] : null,
            $data['avantagesExistants'] ?? []
        );

        return $this->json(['suggestions' => $suggestions]);
    }

    /**
     * POST /api/ai/improve-offre-description
     * Body JSON: { titre, description?, typeContrat?, localisation?, modeTravail? }
     */
    #[Route('/improve-offre-description', name: 'api_ai_improve_offre_description', methods: ['POST'])]
    public function improveOffreDescription(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $titre = $data['titre'] ?? '';
        if (!$titre) {
            return $this->json(['error' => 'Le champ "titre" est requis.'], 400);
        }

        $improved = $this->ai->improveOffreDescription(
            $titre,
            $data['description'] ?? null,
            $data['typeContrat'] ?? null,
            $data['localisation'] ?? null,
            $data['modeTravail'] ?? null
        );

        return $this->json(['description' => $improved]);
    }

    /**
     * POST /api/ai/suggest-avantage-nom
     * Body JSON: { description?, type? }
     */
    #[Route('/suggest-avantage-nom', name: 'api_ai_suggest_avantage_nom', methods: ['POST'])]
    public function suggestAvantageNom(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $nom = $this->ai->suggestAvantageNom(
            $data['description'] ?? null,
            $data['type'] ?? null
        );

        return $this->json(['nom' => $nom]);
    }
}
