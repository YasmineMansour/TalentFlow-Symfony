<?php

namespace App\Repository;

use App\Entity\DecisionFinale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DecisionFinale>
 */
class DecisionFinaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DecisionFinale::class);
    }

    public function search(string $search = '', string $decision = '', $entreprise = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.entretien', 'e')
            ->addSelect('e')
            ->leftJoin('App\Entity\Candidature', 'c', 'WITH', 'c.id = e.candidatureId')
            ->leftJoin('c.offre', 'o')
            ->leftJoin('o.entreprise', 'ent')
            ->orderBy('d.dateDecision', 'DESC');

        if ($search !== '') {
            if (ctype_digit($search)) {
                $qb->andWhere('d.id = :exact OR e.id = :exact OR e.candidatureId = :exact')->setParameter('exact', (int) $search);
            } else {
                $qb->andWhere('d.decision LIKE :query OR d.motif LIKE :query')->setParameter('query', '%' . $search . '%');
            }
        }

        if ($decision !== '') {
            $qb->andWhere('d.decision = :decision')->setParameter('decision', $decision);
        }

        if ($entreprise !== null) {
            $qb->andWhere('ent.id = :entrepriseId')->setParameter('entrepriseId', $entreprise->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function countByDecision(string $decision): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.decision = :decision')
            ->setParameter('decision', $decision)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPending(): int
    {
        return $this->countByDecision('EN_ATTENTE');
    }

    public function findPendingWithScore(): array
    {
        return $this->createQueryBuilder('d')
            ->join('d.entretien', 'e')
            ->addSelect('e')
            ->andWhere('d.decision = :decision')
            ->andWhere('d.score IS NOT NULL')
            ->setParameter('decision', 'EN_ATTENTE')
            ->orderBy('d.score', 'DESC')
            ->getQuery()
            ->getResult();
    }
}