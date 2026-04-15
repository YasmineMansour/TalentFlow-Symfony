<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande RGPD : Purge des comptes utilisateurs inactifs.
 *
 * Critères de suppression (configurables via options) :
 *   - Comptes créés il y a plus de X jours mais dont le mot de passe n'a jamais été changé
 *     ET qui n'ont jamais été connectés (comptes "zombie" non confirmés).
 *   - OU comptes dont la dernière connexion date de plus de 2 ans.
 *
 * Utilisation :
 *   php bin/console app:purge-inactive-users
 *   php bin/console app:purge-inactive-users --dry-run
 *   php bin/console app:purge-inactive-users --inactive-days=365
 */
#[AsCommand(
    name: 'app:purge-inactive-users',
    description: 'RGPD — Supprime les comptes inactifs ou jamais confirmés selon les seuils configurés.',
)]
class PurgeInactiveUsersCommand extends Command
{
    /** Jours sans connexion avant considération comme "inactif" */
    private const DEFAULT_INACTIVE_DAYS = 730; // 2 ans

    /** Jours après création d'un compte jamais utilisé avant suppression */
    private const DEFAULT_NEVER_LOGGED_DAYS = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserLogRepository $userLogRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la purge sans rien supprimer')
            ->addOption('inactive-days', null, InputOption::VALUE_REQUIRED, 'Jours d\'inactivité avant purge', self::DEFAULT_INACTIVE_DAYS)
            ->addOption('never-logged-days', null, InputOption::VALUE_REQUIRED, 'Jours depuis création sans connexion', self::DEFAULT_NEVER_LOGGED_DAYS)
            ->setHelp(<<<HELP
La commande <info>app:purge-inactive-users</info> supprime les comptes :

  1. <comment>Jamais utilisés</comment> : créés il y a plus de --never-logged-days jours
     et dont la valeur de last_login_at est NULL.

  2. <comment>Inactifs depuis longtemps</comment> : dont la dernière connexion
     date de plus de --inactive-days jours (par défaut 2 ans, conformité RGPD).

Utilisez <info>--dry-run</info> pour voir les comptes qui seraient supprimés
sans effectuer aucune suppression réelle.

  <info>php bin/console app:purge-inactive-users --dry-run</info>
  <info>php bin/console app:purge-inactive-users --inactive-days=365</info>
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $inactiveDays = (int) $input->getOption('inactive-days');
        $neverLoggedDays = (int) $input->getOption('never-logged-days');

        $io->title('🧹 Purge des comptes inactifs — TalentFlow RGPD');

        if ($dryRun) {
            $io->warning('MODE DRY-RUN : aucune suppression ne sera effectuée.');
        }

        $now = new \DateTimeImmutable();
        $inactiveSince = $now->modify("-{$inactiveDays} days");
        $neverLoggedSince = $now->modify("-{$neverLoggedDays} days");

        $userRepo = $this->entityManager->getRepository(User::class);

        /** @var User[] $allUsers */
        $allUsers = $userRepo->findAll();

        $toDelete = [];
        $reasons  = [];

        foreach ($allUsers as $user) {
            // Ne jamais purger les admins
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                continue;
            }

            $reason = null;

            // Cas 1 : Compte jamais utilisé depuis X jours
            if ($user->getLastLoginAt() === null
                && $user->getCreatedAt() !== null
                && $user->getCreatedAt() < $neverLoggedSince) {
                $reason = sprintf(
                    'Jamais connecté depuis %d jours (créé le %s)',
                    $neverLoggedDays,
                    $user->getCreatedAt()->format('d/m/Y')
                );
            }

            // Cas 2 : Inactif depuis plus de X jours
            if ($reason === null
                && $user->getLastLoginAt() !== null
                && $user->getLastLoginAt() < $inactiveSince) {
                $reason = sprintf(
                    'Inactif depuis %d jours (dernière connexion le %s)',
                    $inactiveDays,
                    $user->getLastLoginAt()->format('d/m/Y')
                );
            }

            if ($reason !== null) {
                $toDelete[] = $user;
                $reasons[$user->getEmail()] = $reason;
            }
        }

        if (empty($toDelete)) {
            $io->success('Aucun compte éligible à la purge. Base de données propre ✓');
            return Command::SUCCESS;
        }

        // Afficher le tableau des comptes concernés
        $rows = array_map(fn (User $u) => [
            $u->getId(),
            $u->getFullName(),
            $u->getEmail(),
            $u->getRoleLabel(),
            $reasons[$u->getEmail()],
        ], $toDelete);

        $io->table(['ID', 'Nom', 'Email', 'Rôle', 'Raison'], $rows);
        $io->writeln(sprintf('<comment>%d compte(s) éligible(s) à la suppression.</comment>', count($toDelete)));

        if ($dryRun) {
            $io->note('Dry-run terminé. Aucun compte supprimé.');
            return Command::SUCCESS;
        }

        $io->ask('Confirmez-vous la suppression de ces comptes ? Tapez "CONFIRMER"', null, function (?string $value) use ($io, &$toDelete) {
            if ($value !== 'CONFIRMER') {
                $io->warning('Suppression annulée.');
                $toDelete = [];
            }
        });

        if (empty($toDelete)) {
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($toDelete as $user) {
            // Purge des logs UserLog anciens au passage (RGPD)
            $this->userLogRepository->purgeOlderThan(90);

            $this->entityManager->remove($user);
            $count++;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d compte(s) supprimé(s) avec succès.', $count));

        return Command::SUCCESS;
    }
}
