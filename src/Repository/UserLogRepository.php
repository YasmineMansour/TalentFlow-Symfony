<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserLog>
 */
class UserLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserLog::class);
    }

    /**
     * Retourne les N dernières connexions d'un utilisateur.
     *
     * @return UserLog[]
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('ul')
            ->where('ul.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ul.loggedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si une IP a déjà servi à se connecter pour cet utilisateur (succès).
     */
    public function hasUserLoggedFromIp(User $user, string $ip): bool
    {
        $count = (int) $this->createQueryBuilder('ul')
            ->select('COUNT(ul.id)')
            ->where('ul.user = :user')
            ->andWhere('ul.ipAddress = :ip')
            ->andWhere('ul.successful = true')
            ->setParameter('user', $user)
            ->setParameter('ip', $ip)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Supprime les logs plus anciens que $days jours (maintenance RGPD).
     */
    public function purgeOlderThan(int $days = 90): int
    {
        $since = new \DateTimeImmutable("-{$days} days");

        return (int) $this->createQueryBuilder('ul')
            ->delete()
            ->where('ul.loggedAt < :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->execute();
    }
}
