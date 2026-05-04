<?php

namespace App\Controller;

use App\Service\CurrencyConverterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/currency')]
class CurrencyApiController extends AbstractController
{
    public function __construct(private CurrencyConverterService $converter)
    {
    }

    /**
     * GET /api/currency/rates
     * Liste des devises supportées.
     */
    #[Route('/rates', name: 'api_currency_rates', methods: ['GET'])]
    public function rates(): JsonResponse
    {
        return $this->json([
            'base' => 'TND',
            'currencies' => $this->converter->getSupportedCurrencies(),
        ]);
    }

    /**
     * GET /api/currency/convert?amount=3000&to=EUR
     * Convertir un montant DT vers une devise.
     */
    #[Route('/convert', name: 'api_currency_convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        $amount = $request->query->get('amount');
        $to = $request->query->get('to');

        if ($amount === null || !is_numeric($amount) || (float) $amount < 0) {
            return $this->json(['error' => 'Paramètre "amount" invalide.'], 400);
        }

        if (!$to) {
            return $this->json(['error' => 'Paramètre "to" requis.'], 400);
        }

        try {
            $result = $this->converter->convert((float) $amount, $to);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($result);
    }

    /**
     * GET /api/currency/convert-range?min=2000&max=4000&to=EUR
     * Convertir une fourchette salariale DT vers une devise.
     */
    #[Route('/convert-range', name: 'api_currency_convert_range', methods: ['GET'])]
    public function convertRange(Request $request): JsonResponse
    {
        $min = $request->query->get('min');
        $max = $request->query->get('max');
        $to = $request->query->get('to');

        if (!$to) {
            return $this->json(['error' => 'Paramètre "to" requis.'], 400);
        }

        $minVal = ($min !== null && is_numeric($min)) ? (float) $min : null;
        $maxVal = ($max !== null && is_numeric($max)) ? (float) $max : null;

        if ($minVal === null && $maxVal === null) {
            return $this->json(['error' => 'Au moins un paramètre "min" ou "max" requis.'], 400);
        }

        try {
            $result = $this->converter->convertRange($minVal, $maxVal, $to);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        return $this->json($result);
    }
}
