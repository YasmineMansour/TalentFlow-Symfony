<?php

namespace App\Repository;

use App\Entity\Avantage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AvantageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avantage::class);
    }

    public function findByOffre(int $offreId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.offre = :offreId')
            ->setParameter('offreId', $offreId)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilters(
        string $search = '',
        ?string $type = null,
        ?int $offreId = null,
        string $tri = 'id',
        string $ordre = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.offre', 'o')
            ->addSelect('o');

        if ($search !== '') {
            $qb->andWhere('a.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type !== null && $type !== '') {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }

        if ($offreId !== null) {
            $qb->andWhere('o.id = :offreId')
               ->setParameter('offreId', $offreId);
        }

        $allowedTri = ['id', 'nom', 'type'];
        $triColumn = in_array($tri, $allowedTri, true) ? $tri : 'id';
        $ordreDir = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('a.' . $triColumn, $ordreDir);

        return $qb->getQuery()->getResult();
    }
}
