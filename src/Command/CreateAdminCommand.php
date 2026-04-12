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

    protected function configure(): void
    {
        $this
            ->addArgument('email', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Email')
            ->addArgument('password', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Password')
            ->addArgument('prenom', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Prenom')
            ->addArgument('nom', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Nom')
            ->addArgument('role', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Role')
            ->addArgument('telephone', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Telephone');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email') ?? 'admin@talentflow.com';
        $pass = $input->getArgument('password') ?? 'Admin123!';
        $prenom = $input->getArgument('prenom') ?? 'Super';
        $nom = $input->getArgument('nom') ?? 'Admin';
        $role = $input->getArgument('role') ?? 'ROLE_ADMIN';
        $tel = $input->getArgument('telephone') ?? '+21600000000';

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $io->warning("L'utilisateur $email existe déjà !");
            return Command::FAILURE;
        }

        $user = new User();
        $user->setNom($nom);
        $user->setPrenom($prenom);
        $user->setEmail($email);
        $user->setRoles([$role]);
        $user->setTelephone($tel);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $pass);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Utilisateur créé avec succès !');
        $io->table(
            ['Email', 'Mot de passe', 'Rôle'],
            [[$email, $pass, $role]]
        );

        return Command::SUCCESS;
    }
}
