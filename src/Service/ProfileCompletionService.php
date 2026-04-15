<?php

namespace App\Service;

use App\Entity\User;

/**
 * Calcule le "score de complétude" du profil d'un utilisateur (0 → 100 %).
 *
 * Chaque champ rempli contribue pour un certain nombre de points.
 * Utilisé sur le dashboard pour inciter l'utilisateur à finaliser son profil.
 */
class ProfileCompletionService
{
    /**
     * Poids (en points) de chaque champ.
     * Total max = 100.
     */
    private const FIELDS = [
        'nom'          => 15,
        'prenom'       => 15,
        'email'        => 20,
        'telephone'    => 10,
        'titrePoste'   => 15,
        'bio'          => 15,
        'entreprise'   => 10,
    ];

    /**
     * Retourne le score de 0 à 100.
     */
    public function getScore(User $user): int
    {
        $total = array_sum(self::FIELDS);
        $earned = 0;

        foreach (self::FIELDS as $field => $points) {
            if ($this->isFieldFilled($user, $field)) {
                $earned += $points;
            }
        }

        return (int) round(($earned / $total) * 100);
    }

    /**
     * Retourne la liste des champs manquants avec leur libellé.
     *
     * @return array<string, string> clé = champ, valeur = label
     */
    public function getMissingFields(User $user): array
    {
        $missing = [];
        $labels = [
            'nom'        => 'Nom de famille',
            'prenom'     => 'Prénom',
            'email'      => 'Adresse email',
            'telephone'  => 'Numéro de téléphone',
            'titrePoste' => 'Titre professionnel',
            'bio'        => 'Biographie / Présentation',
            'entreprise' => 'Entreprise liée',
        ];

        foreach (self::FIELDS as $field => $_) {
            if (!$this->isFieldFilled($user, $field)) {
                $missing[$field] = $labels[$field] ?? $field;
            }
        }

        return $missing;
    }

    /**
     * Retourne la couleur Bootstrap associée au score.
     */
    public function getScoreColor(int $score): string
    {
        if ($score >= 80) return 'success';
        if ($score >= 50) return 'warning';
        return 'danger';
    }

    private function isFieldFilled(User $user, string $field): bool
    {
        return match ($field) {
            'nom'        => !empty(trim((string) $user->getNom())),
            'prenom'     => !empty(trim((string) $user->getPrenom())),
            'email'      => !empty($user->getEmail()),
            'telephone'  => !empty($user->getTelephone()),
            'titrePoste' => !empty($user->getTitrePoste()),
            'bio'        => !empty($user->getBio()),
            'entreprise' => $user->getEntreprise() !== null,
            default      => false,
        };
    }
}
