<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\EntretienRepository;
use App\Service\RecruitmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-entretien-reminders',
    description: 'Envoie les rappels d\'entretiens T-24h et T-1h (sans doublon).',
)]
class SendEntretienRemindersCommand extends Command
{
    public function __construct(
        private readonly EntretienRepository $entretienRepository,
        private readonly RecruitmentService $recruitmentService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();

        // Fenetre de tolerance: commande prevue toutes les 5-15 min.
        $window24Start = $now->modify('+23 hours 45 minutes');
        $window24End = $now->modify('+24 hours 15 minutes');

        $window1Start = $now->modify('+45 minutes');
        $window1End = $now->modify('+1 hour 15 minutes');

        $list24h = $this->entretienRepository->findFor24hReminder($window24Start, $window24End);
        $list1h = $this->entretienRepository->findFor1hReminder($window1Start, $window1End);

        $sent24 = 0;
        $sent1 = 0;

        foreach ($list24h as $entretien) {
            try {
                $this->recruitmentService->sendEntretienReminder($entretien, '24h');
                $entretien->setReminder24hSentAt(new \DateTime());
                $sent24++;
            } catch (\Throwable) {
                // Non bloquant: continue sur les autres entretiens.
            }
        }

        foreach ($list1h as $entretien) {
            try {
                $this->recruitmentService->sendEntretienReminder($entretien, '1h');
                $entretien->setReminder1hSentAt(new \DateTime());
                $sent1++;
            } catch (\Throwable) {
                // Non bloquant: continue sur les autres entretiens.
            }
        }

        if ($sent24 > 0 || $sent1 > 0) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            'Rappels envoyes: %d (T-24h), %d (T-1h).',
            $sent24,
            $sent1
        ));

        return Command::SUCCESS;
    }
}
