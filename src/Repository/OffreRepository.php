<?php

namespace App\Repository;

use App\Entity\Offre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OffreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offre::class);
    }

    public function findActiveOffers(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.active = :active')
            ->setParameter('active', true)
            ->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function searchByTitre(string $query): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.titre LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche + tri + filtrage combinés.
     */
    public function findByFilters(
        string $search = '',
        ?int $categorieId = null,
        ?int $entrepriseId = null,
        string $tri = 'id',
        string $ordre = 'DESC',
        $entreprise = null // Peut être un objet Entreprise ou null
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.entreprise', 'e')
            ->leftJoin('o.categorie', 'c')
            ->addSelect('e', 'c');

        if ($search !== '') {
            $qb->andWhere('o.titre LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($categorieId !== null) {
            $qb->andWhere('c.id = :categorieId')
               ->setParameter('categorieId', $categorieId);
        }

        if ($entrepriseId !== null) {
            $qb->andWhere('e.id = :entrepriseId')
               ->setParameter('entrepriseId', $entrepriseId);
        }

        if ($entreprise !== null) {
            $qb->andWhere('o.entreprise = :entrepriseObj')
               ->setParameter('entrepriseObj', $entreprise);
        }

        $allowedTri = ['id', 'titre', 'salaireMin', 'salaireMax'];
        $triColumn = in_array($tri, $allowedTri, true) ? $tri : 'id';
        $ordreDir = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('o.' . $triColumn, $ordreDir);

        return $qb->getQuery()->getResult();
    }

    /**
     * Public: recherche offres actives avec filtres pour visiteurs.
     */
    public function findPublicByFilters(
        string $search = '',
        ?int $categorieId = null,
        string $typeContrat = '',
        string $localisation = '',
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.entreprise', 'e')
            ->leftJoin('o.categorie', 'c')
            ->addSelect('e', 'c')
            ->andWhere('o.active = :active')
            ->setParameter('active', true)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults($limit);

        if ($search !== '') {
            $qb->andWhere('o.titre LIKE :search OR o.description LIKE :search OR o.localisation LIKE :search OR e.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($categorieId !== null) {
            $qb->andWhere('c.id = :categorieId')
               ->setParameter('categorieId', $categorieId);
        }

        if ($typeContrat !== '') {
            $qb->andWhere('o.typeContrat = :typeContrat')
               ->setParameter('typeContrat', $typeContrat);
        }

        if ($localisation !== '') {
            $qb->andWhere('o.localisation LIKE :loc')
               ->setParameter('loc', '%' . $localisation . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
