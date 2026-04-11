<?php

namespace App\Repository;

use App\Entity\Candidature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Candidature>
 */
class CandidatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidature::class);
    }

    /**
     * @return Candidature[]
     */
    public function findAllOrdered(string $sortBy = 'createdAt', string $sortDir = 'DESC'): array
    {
        $allowed = ['titrePoste', 'entreprise', 'typeContrat', 'statut', 'dateCandidature', 'createdAt'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'createdAt';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        return $this->createQueryBuilder('c')
            ->orderBy('c.' . $sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Candidature[]
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.titrePoste LIKE :q')
            ->orWhere('c.entreprise LIKE :q')
            ->orWhere('c.lieu LIKE :q')
            ->orWhere('c.description LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Advanced filter: search + typeContrat + statut + sort.
     *
     * @return Candidature[]
     */
    public function findFiltered(
        string $search = '',
        string $typeContrat = '',
        string $statut = '',
        string $sortBy = 'createdAt',
        string $sortDir = 'DESC',
        $entreprise = null,
        $candidat = null
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.offre', 'o')
            ->leftJoin('o.entreprise', 'e');

        if (!empty($search)) {
            $qb->andWhere('c.titrePoste LIKE :search OR c.entreprise LIKE :search OR c.lieu LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if (!empty($typeContrat)) {
            $qb->andWhere('c.typeContrat = :type')
               ->setParameter('type', $typeContrat);
        }

        if (!empty($statut)) {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if ($entreprise !== null) {
            $qb->andWhere('e.id = :entrepriseId')
               ->setParameter('entrepriseId', $entreprise->getId());
        }

        if ($candidat !== null) {
            $qb->andWhere('c.candidat = :candidat')
               ->setParameter('candidat', $candidat);
        }

        $allowed = ['titrePoste', 'entreprise', 'typeContrat', 'statut', 'dateCandidature', 'createdAt'];
        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'createdAt';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('c.' . $sortBy, $sortDir);

        return $qb->getQuery()->getResult();
    }

    public function countByStatut(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as total')
            ->groupBy('c.statut')
            ->getQuery()
            ->getResult();
    }

    public function countByTypeContrat(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.typeContrat, COUNT(c.id) as total')
            ->groupBy('c.typeContrat')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $ids
     * @return array<int, string>
     */
    public function findEmailsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $candidatures = $this->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', array_unique(array_filter($ids)))
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($candidatures as $c) {
            $map[$c->getId()] = $c->getTitrePoste() . ' - ' . $c->getEntreprise();
        }

        return $map;
    }
}
