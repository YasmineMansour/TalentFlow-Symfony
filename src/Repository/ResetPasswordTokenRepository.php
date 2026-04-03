<?php

namespace App\Repository;

use App\Entity\ResetPasswordToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResetPasswordToken>
 */
class ResetPasswordTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordToken::class);
    }

    /**
     * Trouve un token valide par son hash.
     */
    public function findValidByHash(string $hash): ?ResetPasswordToken
    {
        return $this->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')
            ->andWhere('t.used = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', $hash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalide tous les tokens existants d'un utilisateur pour un type donné.
     */
    public function invalidateAllForUser(User $user, string $type = 'password_reset'): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.used', 'true')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.used = false')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->execute();
    }

    /**
     * Nettoie les tokens expirés (maintenance).
     */
    public function purgeExpired(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
