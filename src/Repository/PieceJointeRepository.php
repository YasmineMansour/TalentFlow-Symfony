<?php

namespace App\Repository;

use App\Entity\PieceJointe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PieceJointe>
 */
class PieceJointeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PieceJointe::class);
    }

    /**
     * @return PieceJointe[]
     */
    public function findByCandidature(int $candidatureId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.candidature = :id')
            ->setParameter('id', $candidatureId)
            ->orderBy('p.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PieceJointe[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.typeDocument = :type')
            ->setParameter('type', $type)
            ->orderBy('p.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
