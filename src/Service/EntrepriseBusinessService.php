<?php

namespace App\Service;

use App\Entity\Entreprise;

class EntrepriseBusinessService
{
    /**
     * Taille de l'entreprise selon le nombre d'offres publiées.
     */
    public function getTaille(Entreprise $entreprise): string
    {
        $nbOffres = $entreprise->getOffres()->count();
        if ($nbOffres >= 10) {
            return 'Grande';
        } elseif ($nbOffres >= 5) {
            return 'Moyenne';
        } elseif ($nbOffres >= 1) {
            return 'Petite';
        }
        return 'Aucune offre';
    }

    /**
     * Niveau d'activité : évalue le ratio d'offres actives.
     */
    public function getNiveauActivite(Entreprise $entreprise): string
    {
        $offres = $entreprise->getOffres();
        $total = $offres->count();

        if ($total === 0) {
            return 'Inactive';
        }

        $actives = $offres->filter(fn($o) => $o->isActive())->count();
        $ratio = $actives / $total;

        if ($ratio >= 0.75) {
            return 'Très active';
        } elseif ($ratio >= 0.5) {
            return 'Active';
        } elseif ($ratio > 0) {
            return 'Peu active';
        }
        return 'Inactive';
    }

    /**
     * Vérifie si le profil de l'entreprise est complet.
     */
    public function isComplete(Entreprise $entreprise): bool
    {
        return !empty($entreprise->getNom())
            && !empty($entreprise->getSecteur())
            && !empty($entreprise->getAdresse())
            && !empty($entreprise->getEmail())
            && !empty($entreprise->getTelephone())
            && !empty($entreprise->getDescription());
    }
}
