<?php

namespace App\Command;

use App\Repository\EntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mark-past-entretiens-as-realised',
    description: 'Marque automatiquement les entretiens avec une date/heure dans le passé comme REALISE',
)]
class MarkPastEntretiensAsRealisedCommand extends Command
{
    public function __construct(
        private readonly EntretienRepository $entretienRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Récupère tous les entretiens PLANIFIE avec une date/heure dans le passé
        $now = new \DateTime();
        $pastEntretiens = $this->entretienRepository->createQueryBuilder('e')
            ->andWhere('e.statut = :statut')
            ->andWhere('e.dateHeure < :now')
            ->setParameter('statut', 'PLANIFIE')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        if (count($pastEntretiens) === 0) {
            $io->info('Aucun entretien à marquer comme réalisé.');

            return Command::SUCCESS;
        }

        foreach ($pastEntretiens as $entretien) {
            $entretien->setStatut('REALISE');
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d entretien(s) marqué(s) comme réalisé(s).', count($pastEntretiens)));

        return Command::SUCCESS;
    }
}
