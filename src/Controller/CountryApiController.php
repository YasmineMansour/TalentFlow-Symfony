<?php

namespace App\Controller;

use App\Service\CountryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/country')]
class CountryApiController extends AbstractController
{
    public function __construct(private CountryService $countryService)
    {
    }

    /**
     * GET /api/country/detect?localisation=Tunis
     * Détecte le pays à partir d'une localisation et retourne les infos.
     */
    #[Route('/detect', name: 'api_country_detect', methods: ['GET'])]
    public function detect(Request $request): JsonResponse
    {
        $localisation = $request->query->get('localisation', '');

        if (!$localisation) {
            return $this->json(['error' => 'Paramètre "localisation" requis.'], 400);
        }

        $info = $this->countryService->getCountryFromLocalisation($localisation);

        if (!$info) {
            return $this->json(['error' => 'Pays non détecté pour cette localisation.'], 404);
        }

        return $this->json($info);
    }

    /**
     * GET /api/country/info?code=TN
     * Retourne les infos d'un pays par code ISO alpha2.
     */
    #[Route('/info', name: 'api_country_info', methods: ['GET'])]
    public function info(Request $request): JsonResponse
    {
        $code = $request->query->get('code', '');

        if (!$code || strlen($code) !== 2) {
            return $this->json(['error' => 'Paramètre "code" invalide (code ISO alpha2 requis).'], 400);
        }

        $info = $this->countryService->getCountryInfo($code);

        if (!$info) {
            return $this->json(['error' => 'Pays non trouvé.'], 404);
        }

        return $this->json($info);
    }
}
