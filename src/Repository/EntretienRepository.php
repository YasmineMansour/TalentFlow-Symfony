<?php

namespace App\Repository;

use App\Entity\Entretien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entretien>
 */
class EntretienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entretien::class);
    }

    public function search(string $search = '', string $statut = '', string $type = '', $entreprise = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.decisionFinale', 'd')
            ->addSelect('d')
            ->orderBy('e.dateHeure', 'DESC');

        // Join candidature via candidatureId (pas une relation Doctrine)
        $qb->leftJoin('App\Entity\Candidature', 'c', 'WITH', 'c.id = e.candidatureId')
            ->leftJoin('c.offre', 'o')
            ->leftJoin('o.entreprise', 'ent');

        if ($search !== '') {
            if (ctype_digit($search)) {
                $qb->andWhere('e.id = :exact OR e.candidatureId = :exact')->setParameter('exact', (int) $search);
            } else {
                $qb->andWhere('e.type LIKE :query OR e.statut LIKE :query OR e.lieu LIKE :query OR e.lien LIKE :query OR e.commentaire LIKE :query')
                    ->setParameter('query', '%' . $search . '%');
            }
        }

        if ($statut !== '') {
            $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut);
        }

        if ($type !== '') {
            $qb->andWhere('e.type = :type')->setParameter('type', $type);
        }

        if ($entreprise !== null) {
            $qb->andWhere('ent.id = :entrepriseId')->setParameter('entrepriseId', $entreprise->getId());
        }

        return $qb->getQuery()->getResult();
    }

    public function existsConflictAtDateHeure(\DateTimeInterface $dateHeure, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.dateHeure = :dateHeure')
            ->andWhere('e.statut != :annule')
            ->setParameter('dateHeure', $dateHeure)
            ->setParameter('annule', 'ANNULE');

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countToday(): int
    {
        $start = new \DateTimeImmutable('today midnight');
        $end = new \DateTimeImmutable('tomorrow midnight');

        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.dateHeure >= :start')
            ->andWhere('e.dateHeure < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function createAvailableForDecisionQueryBuilder(?int $currentEntretienId = null)
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.decisionFinale', 'd')
            ->andWhere('e.statut = :statut')
            ->setParameter('statut', 'REALISE')
            ->orderBy('e.dateHeure', 'DESC');

        if ($currentEntretienId !== null) {
            $qb->andWhere('d.id IS NULL OR e.id = :currentId')->setParameter('currentId', $currentEntretienId);
        } else {
            $qb->andWhere('d.id IS NULL');
        }

        return $qb;
    }

    public function findRealisedWithoutDecision(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.decisionFinale', 'd')
            ->andWhere('e.statut = :statut')
            ->andWhere('d.id IS NULL')
            ->setParameter('statut', 'REALISE')
            ->orderBy('e.dateHeure', 'DESC')
            ->getQuery()
            ->getResult();
    }
}