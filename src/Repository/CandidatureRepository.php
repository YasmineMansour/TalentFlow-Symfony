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
        string $sortDir = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('c');

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
}
