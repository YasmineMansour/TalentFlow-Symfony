<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * Voter d'accès granulaire pour la gestion des utilisateurs.
 *
 * Règles métier :
 * - ROLE_ADMIN  → peut tout faire sur tous les utilisateurs.
 * - ROLE_RH     → peut voir/modifier uniquement les CANDIDATS de sa propre entreprise.
 *                 Ne peut PAS toucher les comptes Admin ou RH.
 * - ROLE_CANDIDAT → peut uniquement voir/modifier son propre profil.
 *
 * Attributs supportés :
 *   USER_VIEW   — voir le profil d'un utilisateur
 *   USER_EDIT   — modifier un utilisateur
 *   USER_DELETE — supprimer un utilisateur
 */
class UserVoter extends Voter
{
    public const VIEW   = 'USER_VIEW';
    public const EDIT   = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';

    public function __construct(
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var User $currentUser */
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Admin peut tout faire
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        // RH : accès aux candidats de sa propre entreprise uniquement
        if ($this->accessDecisionManager->decide($token, ['ROLE_RH'])) {
            return $this->rhCanAccess($attribute, $currentUser, $targetUser);
        }

        // Candidat : peut voir/modifier uniquement son propre profil
        if ($attribute === self::VIEW || $attribute === self::EDIT) {
            return $currentUser->getId() === $targetUser->getId();
        }

        return false;
    }

    private function rhCanAccess(string $attribute, User $rh, User $target): bool
    {
        // Le RH ne peut jamais supprimer ou modifier des comptes Admin/RH
        if (in_array('ROLE_ADMIN', $target->getRoles(), true) ||
            in_array('ROLE_RH', $target->getRoles(), true)) {
            return false;
        }

        // La cible doit être un CANDIDAT
        if (!in_array('ROLE_CANDIDAT', $target->getRoles(), true)) {
            return false;
        }

        // Doit appartenir à la même entreprise
        if ($rh->getEntreprise() === null || $target->getEntreprise() === null) {
            // Si aucune entreprise liée, le RH voit tous les candidats sans entreprise
            return $attribute === self::VIEW;
        }

        $sameEnterp = $rh->getEntreprise()->getId() === $target->getEntreprise()->getId();

        if (!$sameEnterp) {
            return false;
        }

        // VIEW et EDIT autorisés pour la même entreprise, DELETE interdit au RH
        return $attribute !== self::DELETE;
    }
}
