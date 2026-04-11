<?php

namespace App\Command;

use App\Entity\Candidature;
use App\Entity\Entreprise;
use App\Entity\Offre;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:load-test-data', description: 'Charge des données de test pour vérifier les droits d\'accès')]
class LoadTestDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // --- Entreprises ---
        $entreprise1 = new Entreprise();
        $entreprise1->setNom('TechCorp Tunisia');
        $entreprise1->setSecteur('Informatique');
        $entreprise1->setAdresse('Tunis, Les Berges du Lac');
        $entreprise1->setEmail('contact@techcorp.tn');
        $entreprise1->setTelephone('+21671000001');
        $entreprise1->setDescription('Entreprise spécialisée dans le développement logiciel et les solutions cloud.');
        $this->em->persist($entreprise1);

        $entreprise2 = new Entreprise();
        $entreprise2->setNom('FinancePlus');
        $entreprise2->setSecteur('Finance');
        $entreprise2->setAdresse('Sfax, Centre Ville');
        $entreprise2->setEmail('contact@financeplus.tn');
        $entreprise2->setTelephone('+21674000002');
        $entreprise2->setDescription('Cabinet de conseil en finance et gestion de patrimoine.');
        $this->em->persist($entreprise2);

        // --- Admin ---
        $admin = new User();
        $admin->setNom('ADMIN');
        $admin->setPrenom('Super');
        $admin->setEmail('admin@talentflow.tn');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123456!@'));
        $admin->setEntreprise($entreprise1);
        $this->em->persist($admin);

        // --- RH Entreprise 1 ---
        $rh1 = new User();
        $rh1->setNom('BENALI');
        $rh1->setPrenom('Sami');
        $rh1->setEmail('rh1@techcorp.tn');
        $rh1->setRoles(['ROLE_RH']);
        $rh1->setPassword($this->passwordHasher->hashPassword($rh1, 'Rh123456789!@'));
        $rh1->setEntreprise($entreprise1);
        $this->em->persist($rh1);

        // --- RH Entreprise 2 ---
        $rh2 = new User();
        $rh2->setNom('TRABELSI');
        $rh2->setPrenom('Amira');
        $rh2->setEmail('rh2@financeplus.tn');
        $rh2->setRoles(['ROLE_RH']);
        $rh2->setPassword($this->passwordHasher->hashPassword($rh2, 'Rh123456789!@'));
        $rh2->setEntreprise($entreprise2);
        $this->em->persist($rh2);

        // --- Candidat 1 ---
        $candidat1 = new User();
        $candidat1->setNom('MANSOUR');
        $candidat1->setPrenom('Yasmine');
        $candidat1->setEmail('candidat1@gmail.com');
        $candidat1->setRoles(['ROLE_CANDIDAT']);
        $candidat1->setPassword($this->passwordHasher->hashPassword($candidat1, 'Cand123456!@'));
        $this->em->persist($candidat1);

        // --- Candidat 2 ---
        $candidat2 = new User();
        $candidat2->setNom('BOUAZIZI');
        $candidat2->setPrenom('Mohamed');
        $candidat2->setEmail('candidat2@gmail.com');
        $candidat2->setRoles(['ROLE_CANDIDAT']);
        $candidat2->setPassword($this->passwordHasher->hashPassword($candidat2, 'Cand123456!@'));
        $this->em->persist($candidat2);

        // --- Offres Entreprise 1 (TechCorp) ---
        $offre1 = new Offre();
        $offre1->setTitre('Développeur Symfony Senior');
        $offre1->setDescription('Nous recherchons un développeur Symfony expérimenté pour rejoindre notre équipe technique.');
        $offre1->setLocalisation('Tunis');
        $offre1->setTypeContrat('CDI');
        $offre1->setModeTravail('HYBRID');
        $offre1->setSalaireMin(3000);
        $offre1->setSalaireMax(5000);
        $offre1->setActive(true);
        $offre1->setEntreprise($entreprise1);
        $this->em->persist($offre1);

        $offre2 = new Offre();
        $offre2->setTitre('Stagiaire DevOps');
        $offre2->setDescription('Stage de 6 mois en DevOps, CI/CD, Docker, Kubernetes.');
        $offre2->setLocalisation('Tunis');
        $offre2->setTypeContrat('Stage');
        $offre2->setModeTravail('ON_SITE');
        $offre2->setSalaireMin(800);
        $offre2->setSalaireMax(1200);
        $offre2->setActive(true);
        $offre2->setEntreprise($entreprise1);
        $this->em->persist($offre2);

        // --- Offres Entreprise 2 (FinancePlus) ---
        $offre3 = new Offre();
        $offre3->setTitre('Analyste Financier');
        $offre3->setDescription('Poste d\'analyste financier pour accompagner nos clients dans leur stratégie d\'investissement.');
        $offre3->setLocalisation('Sfax');
        $offre3->setTypeContrat('CDI');
        $offre3->setModeTravail('ON_SITE');
        $offre3->setSalaireMin(2500);
        $offre3->setSalaireMax(4000);
        $offre3->setActive(true);
        $offre3->setEntreprise($entreprise2);
        $this->em->persist($offre3);

        $offre4 = new Offre();
        $offre4->setTitre('Comptable Junior');
        $offre4->setDescription('Poste de comptable junior en CDD de 12 mois.');
        $offre4->setLocalisation('Sfax');
        $offre4->setTypeContrat('CDD');
        $offre4->setModeTravail('ON_SITE');
        $offre4->setSalaireMin(1500);
        $offre4->setSalaireMax(2000);
        $offre4->setActive(true);
        $offre4->setEntreprise($entreprise2);
        $this->em->persist($offre4);

        // --- Candidatures ---
        $cand1 = new Candidature();
        $cand1->setTitrePoste('Développeur Symfony Senior');
        $cand1->setEntreprise('TechCorp Tunisia');
        $cand1->setTypeContrat('CDI');
        $cand1->setDescription('Je postule pour le poste de développeur Symfony.');
        $cand1->setDateCandidature(new \DateTimeImmutable());
        $cand1->setStatut('En attente');
        $cand1->setOffre($offre1);
        $cand1->setCandidat($candidat1);
        $this->em->persist($cand1);

        $cand2 = new Candidature();
        $cand2->setTitrePoste('Analyste Financier');
        $cand2->setEntreprise('FinancePlus');
        $cand2->setTypeContrat('CDI');
        $cand2->setDescription('Je postule pour le poste d\'analyste financier.');
        $cand2->setDateCandidature(new \DateTimeImmutable());
        $cand2->setStatut('En attente');
        $cand2->setOffre($offre3);
        $cand2->setCandidat($candidat2);
        $this->em->persist($cand2);

        $this->em->flush();

        $io->success('Données de test chargées avec succès !');
        $io->table(
            ['Rôle', 'Email', 'Mot de passe', 'Entreprise'],
            [
                ['ADMIN', 'admin@talentflow.tn', 'Admin123456!@', 'TechCorp Tunisia'],
                ['RH', 'rh1@techcorp.tn', 'Rh123456789!@', 'TechCorp Tunisia'],
                ['RH', 'rh2@financeplus.tn', 'Rh123456789!@', 'FinancePlus'],
                ['CANDIDAT', 'candidat1@gmail.com', 'Cand123456!@', '-'],
                ['CANDIDAT', 'candidat2@gmail.com', 'Cand123456!@', '-'],
            ]
        );

        return Command::SUCCESS;
    }
}
