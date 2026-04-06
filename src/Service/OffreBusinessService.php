<?php

namespace App\Service;

use App\Entity\Offre;

class OffreBusinessService
{
    /**
     * Classement Or / Argent / Bronze selon le salaire max.
     */
    public function getClassement(Offre $offre): string
    {
        $max = $offre->getSalaireMax();
        if ($max >= 5000) {
            return 'Or';
        } elseif ($max >= 2500) {
            return 'Argent';
        } elseif ($max > 0) {
            return 'Bronze';
        }
        return 'Non classé';
    }

    /**
     * Cohérence salariale : vérifie que min <= max et que l'écart est raisonnable.
     */
    public function getCoherence(Offre $offre): string
    {
        $min = $offre->getSalaireMin();
        $max = $offre->getSalaireMax();

        if ($min == 0 && $max == 0) {
            return 'Non renseigné';
        }
        if ($min > $max) {
            return 'Incohérent';
        }
        if ($max > 0 && $min / $max > 0.9) {
            return 'Très serré';
        }
        return 'Cohérent';
    }

    /**
     * Vérifie si une offre est complète (tous les champs importants remplis).
     */
    public function isComplete(Offre $offre): bool
    {
        return !empty($offre->getTitre())
            && !empty($offre->getDescription())
            && !empty($offre->getLocalisation())
            && $offre->getSalaireMax() > 0;
    }
}
