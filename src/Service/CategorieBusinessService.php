<?php

namespace App\Service;

use App\Entity\Categorie;

class CategorieBusinessService
{
    /**
     * Popularité de la catégorie selon le nombre d'offres.
     */
    public function getPopularite(Categorie $categorie): string
    {
        $nbOffres = $categorie->getOffres()->count();
        if ($nbOffres >= 10) {
            return 'Très populaire';
        } elseif ($nbOffres >= 5) {
            return 'Populaire';
        } elseif ($nbOffres >= 1) {
            return 'Peu populaire';
        }
        return 'Vide';
    }

    /**
     * Taux d'offres actives dans cette catégorie.
     */
    public function getTauxActivite(Categorie $categorie): string
    {
        $offres = $categorie->getOffres();
        $total = $offres->count();

        if ($total === 0) {
            return 'Aucune offre';
        }

        $actives = $offres->filter(fn($o) => $o->isActive())->count();
        $pourcentage = round(($actives / $total) * 100);

        return $pourcentage . '% actives';
    }

    /**
     * Vérifie si le profil de la catégorie est complet.
     */
    public function isComplete(Categorie $categorie): bool
    {
        return !empty($categorie->getNom())
            && !empty($categorie->getDescription());
    }
}
