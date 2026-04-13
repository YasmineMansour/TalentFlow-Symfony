<?php

namespace App\Service;

use App\Repository\EntretienRepository;
use Doctrine\ORM\EntityManagerInterface;

class EntretienStatusService
{
    public function __construct(
        private readonly EntretienRepository $entretienRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Marque automatiquement les entretiens avec une date/heure dans le passé comme REALISE.
     */
    public function markPastEntretiensAsRealised(): int
    {
        $now = new \DateTime();
        $pastEntretiens = $this->entretienRepository->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->andWhere('e.dateHeure < :now')
            ->setParameter('statut', 'PLANIFIE')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if (count($pastEntretiens) === 0) {
            return 0;
        }

        foreach ($pastEntretiens as $entretien) {
            $entretien->setStatut('REALISE');
        }

        $this->entityManager->flush();

        return count($pastEntretiens);
    }
}
