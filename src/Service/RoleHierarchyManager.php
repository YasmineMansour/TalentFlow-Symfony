<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Gestionnaire de Hiérarchie des Rôles
 * 
 * Remplace les simples IF/ELSE par un système de permissions complexes :
 * - Un Admin peut tout faire
 * - Un RH peut modifier un Candidat mais PAS un Admin
 * - Un Candidat ne peut modifier que son propre profil
 * 
 * Matrice de permissions :
 * ┌─────────────┬──────────┬──────────┬──────────────┐
 * │ Action      │ ADMIN    │ RH       │ CANDIDAT     │
 * ├─────────────┼──────────┼──────────┼──────────────┤
 * │ Voir users  │ ✅ Tous  │ ✅ Cand. │ ❌           │
 * │ Créer user  │ ✅ Tous  │ ✅ Cand. │ ❌           │
 * │ Modifier    │ ✅ Tous  │ ✅ Cand. │ ✅ Soi-même  │
 * │ Supprimer   │ ✅ Tous  │ ✅ Cand. │ ❌           │
 * │ Changer rôle│ ✅ Tous  │ ❌       │ ❌           │
 * │ Audit logs  │ ✅       │ ❌       │ ❌           │
 * └─────────────┴──────────┴──────────┴──────────────┘
 */
class RoleHierarchyManager
{
    /**
     * Poids des rôles pour la hiérarchie (plus le nombre est haut, plus le rôle est puissant).
     */
    private const ROLE_WEIGHTS = [
        'ROLE_USER'     => 0,
        'ROLE_CANDIDAT' => 1,
        'ROLE_RH'       => 2,
        'ROLE_ADMIN'    => 3,
    ];

    public function __construct(
        private AuthorizationCheckerInterface $authChecker,
    ) {
    }

    /**
     * Vérifie si l'utilisateur courant peut modifier un utilisateur cible.
     */
    public function canEdit(User $currentUser, User $targetUser): bool
    {
        // Un utilisateur peut toujours modifier son propre profil
        if ($currentUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Les admins peuvent tout modifier
        if ($this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return true;
        }

        // Un RH peut modifier un Candidat uniquement
        if ($this->hasRole($currentUser, 'ROLE_RH') && $this->hasHighestRole($targetUser, 'ROLE_CANDIDAT')) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur courant peut supprimer un utilisateur cible.
     */
    public function canDelete(User $currentUser, User $targetUser): bool
    {
        // On ne peut jamais se supprimer soi-même
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        // Les admins peuvent supprimer tout le monde sauf eux-mêmes
        if ($this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return true;
        }

        // Un RH peut supprimer un Candidat uniquement
        if ($this->hasRole($currentUser, 'ROLE_RH') && $this->hasHighestRole($targetUser, 'ROLE_CANDIDAT')) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur courant peut créer un utilisateur avec un rôle donné.
     */
    public function canCreateWithRole(User $currentUser, string $role): bool
    {
        // Seuls les admins peuvent créer des RH et des admins
        if (in_array($role, ['ROLE_ADMIN', 'ROLE_RH'])) {
            return $this->hasRole($currentUser, 'ROLE_ADMIN');
        }

        // Un RH peut créer des candidats
        if ($role === 'ROLE_CANDIDAT') {
            return $this->hasRole($currentUser, 'ROLE_RH') || $this->hasRole($currentUser, 'ROLE_ADMIN');
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur courant peut changer le rôle d'un autre utilisateur.
     */
    public function canChangeRole(User $currentUser, User $targetUser): bool
    {
        // Seuls les admins peuvent changer les rôles
        if (!$this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return false;
        }

        // Un admin ne peut pas changer son propre rôle (protection)
        if ($currentUser->getId() === $targetUser->getId()) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si l'utilisateur courant peut voir les logs d'audit.
     */
    public function canViewAuditLogs(User $currentUser): bool
    {
        return $this->hasRole($currentUser, 'ROLE_ADMIN');
    }

    /**
     * Retourne le rôle le plus élevé d'un utilisateur.
     */
    public function getHighestRole(User $user): string
    {
        $highestWeight = -1;
        $highestRole = 'ROLE_USER';

        foreach ($user->getRoles() as $role) {
            $weight = self::ROLE_WEIGHTS[$role] ?? 0;
            if ($weight > $highestWeight) {
                $highestWeight = $weight;
                $highestRole = $role;
            }
        }

        return $highestRole;
    }

    /**
     * Compare le niveau de deux utilisateurs.
     * Retourne > 0 si user1 est supérieur, < 0 si inférieur, 0 si égal.
     */
    public function compareLevel(User $user1, User $user2): int
    {
        $weight1 = self::ROLE_WEIGHTS[$this->getHighestRole($user1)] ?? 0;
        $weight2 = self::ROLE_WEIGHTS[$this->getHighestRole($user2)] ?? 0;

        return $weight1 - $weight2;
    }

    /**
     * Retourne les rôles disponibles pour l'utilisateur courant lors de la création/modification.
     */
    public function getAvailableRolesForUser(User $currentUser): array
    {
        if ($this->hasRole($currentUser, 'ROLE_ADMIN')) {
            return [
                'Candidat'       => 'ROLE_CANDIDAT',
                'Recruteur RH'   => 'ROLE_RH',
                'Administrateur' => 'ROLE_ADMIN',
            ];
        }

        if ($this->hasRole($currentUser, 'ROLE_RH')) {
            return [
                'Candidat' => 'ROLE_CANDIDAT',
            ];
        }

        return [];
    }

    /**
     * Vérifie si un utilisateur possède un rôle spécifique.
     */
    private function hasRole(User $user, string $role): bool
    {
        return in_array($role, $user->getRoles());
    }

    /**
     * Vérifie si le rôle le plus élevé d'un utilisateur est exactement le rôle donné.
     */
    private function hasHighestRole(User $user, string $role): bool
    {
        return $this->getHighestRole($user) === $role;
    }
}
