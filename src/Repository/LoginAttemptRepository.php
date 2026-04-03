<?php

namespace App\Repository;

use App\Entity\LoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * Compte les tentatives échouées récentes pour un email donné.
     */
    public function countRecentFailedAttempts(string $email, int $minutes = 15): int
    {
        $since = new \DateTimeImmutable("-{$minutes} minutes");

        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.email = :email')
            ->andWhere('la.successful = false')
            ->andWhere('la.attemptedAt >= :since')
            ->setParameter('email', $email)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les tentatives échouées récentes pour une IP donnée.
     */
    public function countRecentFailedAttemptsByIp(string $ip, int $minutes = 15): int
    {
        $since = new \DateTimeImmutable("-{$minutes} minutes");

        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.ipAddress = :ip')
            ->andWhere('la.successful = false')
            ->andWhere('la.attemptedAt >= :since')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les dernières tentatives pour un email (pour le dashboard admin).
     *
     * @return LoginAttempt[]
     */
    public function findRecentByEmail(string $email, int $limit = 10): array
    {
        return $this->createQueryBuilder('la')
            ->where('la.email = :email')
            ->setParameter('email', $email)
            ->orderBy('la.attemptedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les tentatives récentes (pour l'audit admin).
     *
     * @return LoginAttempt[]
     */
    public function findAllRecent(int $hours = 24): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return $this->createQueryBuilder('la')
            ->where('la.attemptedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('la.attemptedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nettoie les anciennes tentatives (> 30 jours).
     */
    public function purgeOldAttempts(int $days = 30): int
    {
        $before = new \DateTimeImmutable("-{$days} days");

        return (int) $this->createQueryBuilder('la')
            ->delete()
            ->where('la.attemptedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
