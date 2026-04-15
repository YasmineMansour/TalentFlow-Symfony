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
            ->orderBy('d.dateDecision', 'DESC');

        if ($search !== '') {
            if (ctype_digit($search)) {
                $qb->andWhere('d.id = :exact')->setParameter('exact', (int) $search);
            } else {
                $qb->andWhere('d.decision LIKE :query OR d.motif LIKE :query')->setParameter('query', '%' . $search . '%');
            }
        }

        if ($decision !== '') {
            $qb->andWhere('d.decision = :decision')->setParameter('decision', $decision);
        }

        /** @var DecisionFinale[] $results */
        $results = $qb->getQuery()->getResult();

        // Filtre entreprise en PHP
        if ($entreprise !== null) {
            $em = $this->getEntityManager();
            $results = array_values(array_filter($results, function (DecisionFinale $d) use ($entreprise, $em) {
                $entretien = $d->getEntretien();
                if ($entretien === null) return false;
                $candidature = $em->getRepository(\App\Entity\Candidature::class)
                    ->find($entretien->getCandidatureId() ?? 0);
                if ($candidature === null) return false;
                $offre = $candidature->getOffre();
                return $offre !== null && $offre->getEntreprise()?->getId() === $entreprise->getId();
            }));
        }

        return $results;
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
            ->andWhere('d.decision = :decision')
            ->andWhere('d.score IS NOT NULL')
            ->setParameter('decision', 'EN_ATTENTE')
            ->orderBy('d.score', 'DESC')
            ->getQuery()
            ->getResult();
    }
}