<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur de test',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@talentflow.com']);
        if ($existing) {
            $io->warning('L\'utilisateur admin@talentflow.com existe déjà.');
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setNom('Admin');
        $user->setPrenom('Super');
        $user->setEmail('admin@talentflow.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setTelephone('+21600000000');

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'Admin@123');
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Utilisateur admin créé avec succès !');
        $io->table(
            ['Email', 'Mot de passe', 'Rôle'],
            [['admin@talentflow.com', 'Admin@123', 'ROLE_ADMIN']]
        );

        return Command::SUCCESS;
    }
}
