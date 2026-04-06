<?php

namespace App\Service;

use App\Entity\Avantage;

class AvantageBusinessService
{
    /**
     * Importance de l'avantage selon son type.
     */
    public function getImportance(Avantage $avantage): string
    {
        return match ($avantage->getType()) {
            'Financier' => 'Élevée',
            'Bien-être' => 'Moyenne',
            'Matériel' => 'Standard',
            default => 'Basique',
        };
    }

    /**
     * Qualité de la description selon sa longueur.
     */
    public function getQualiteDescription(Avantage $avantage): string
    {
        $desc = $avantage->getDescription();

        if (empty($desc)) {
            return 'Non renseignée';
        }

        $len = mb_strlen($desc);

        if ($len >= 100) {
            return 'Détaillée';
        } elseif ($len >= 30) {
            return 'Correcte';
        }

        return 'Insuffisante';
    }

    /**
     * Vérifie si le profil de l'avantage est complet.
     */
    public function isComplete(Avantage $avantage): bool
    {
        return !empty($avantage->getNom())
            && !empty($avantage->getDescription())
            && !empty($avantage->getType())
            && $avantage->getOffre() !== null;
    }
}
