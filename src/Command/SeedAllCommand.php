<?php

namespace App\Command;

use App\Entity\Avantage;
use App\Entity\Candidature;
use App\Entity\Categorie;
use App\Entity\Comment;
use App\Entity\Conversation;
use App\Entity\DecisionFinale;
use App\Entity\Entreprise;
use App\Entity\Entretien;
use App\Entity\Message;
use App\Entity\Offre;
use App\Entity\Post;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-all',
    description: 'Seed the entire database: users, entreprises, categories, offres, candidatures, entretiens, decisions, posts, comments, votes, conversations, messages',
)]
class SeedAllCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fresh', null, InputOption::VALUE_NONE, 'Truncate all tables before seeding');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('fresh')) {
            $io->warning('Truncating all tables...');
            $conn = $this->em->getConnection();
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            foreach (['votes', 'comments', 'posts', 'messages', 'conversations', 'piece_jointe', 'decision_finale', 'entretien', 'candidature', 'avantage', 'offre', 'categorie', 'entreprise', 'login_attempt', 'reset_password_token', 'user'] as $table) {
                $conn->executeStatement("TRUNCATE TABLE `$table`");
            }
            $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            $this->em->clear();
        }

        // ═══════════════════════════════════════
        // 1. ENTREPRISES
        // ═══════════════════════════════════════
        $io->section('Entreprises');
        $entreprisesData = [
            ['nom' => 'TechCorp Tunisia', 'secteur' => 'Informatique', 'adresse' => 'Tunis, Les Berges du Lac', 'email' => 'contact@techcorp.tn', 'telephone' => '+21671000001', 'description' => 'Leader en développement logiciel, cloud et IA en Tunisie. Nous accompagnons les entreprises dans leur transformation digitale.'],
            ['nom' => 'FinancePlus', 'secteur' => 'Finance', 'adresse' => 'Sfax, Centre Ville', 'email' => 'contact@financeplus.tn', 'telephone' => '+21674000002', 'description' => 'Cabinet de conseil en finance, gestion de patrimoine et audit comptable.'],
            ['nom' => 'MedTech Solutions', 'secteur' => 'Santé & Tech', 'adresse' => 'Sousse, Sahloul', 'email' => 'contact@medtech.tn', 'telephone' => '+21673000003', 'description' => 'Startup innovante dans les solutions numériques pour le secteur médical.'],
            ['nom' => 'GreenEnergy TN', 'secteur' => 'Énergie renouvelable', 'adresse' => 'Tunis, Centre Urbain Nord', 'email' => 'rh@greenenergy.tn', 'telephone' => '+21671000004', 'description' => 'Spécialiste des solutions solaires et éoliennes pour particuliers et entreprises.'],
        ];
        $entreprises = [];
        foreach ($entreprisesData as $d) {
            $e = new Entreprise();
            $e->setNom($d['nom']);
            $e->setSecteur($d['secteur']);
            $e->setAdresse($d['adresse']);
            $e->setEmail($d['email']);
            $e->setTelephone($d['telephone']);
            $e->setDescription($d['description']);
            $this->em->persist($e);
            $entreprises[] = $e;
        }
        $this->em->flush();
        $io->text(count($entreprises) . ' entreprises created.');

        // ═══════════════════════════════════════
        // 2. CATEGORIES
        // ═══════════════════════════════════════
        $io->section('Categories');
        $categoriesData = [
            ['nom' => 'Développement Web', 'description' => 'Postes liés au développement web front-end, back-end et full-stack.'],
            ['nom' => 'DevOps & Cloud', 'description' => 'Infrastructure, CI/CD, conteneurisation et services cloud.'],
            ['nom' => 'Data & IA', 'description' => 'Data engineering, machine learning, deep learning et intelligence artificielle.'],
            ['nom' => 'Finance & Comptabilité', 'description' => 'Comptabilité, gestion financière, audit et analyse financière.'],
            ['nom' => 'Santé & Médical', 'description' => 'Postes dans le secteur médical et les technologies de santé.'],
            ['nom' => 'Marketing Digital', 'description' => 'SEO, SEM, community management, growth hacking.'],
            ['nom' => 'Énergie & Environnement', 'description' => 'Énergies renouvelables, transition énergétique et développement durable.'],
        ];
        $categories = [];
        foreach ($categoriesData as $d) {
            $c = new Categorie();
            $c->setNom($d['nom']);
            $c->setDescription($d['description']);
            $this->em->persist($c);
            $categories[] = $c;
        }
        $this->em->flush();
        $io->text(count($categories) . ' categories created.');

        // ═══════════════════════════════════════
        // 3. USERS
        // ═══════════════════════════════════════
        $io->section('Users');
        $usersData = [
            ['prenom' => 'Super', 'nom' => 'Admin', 'email' => 'admin@talentflow.com', 'password' => 'Admin123!', 'role' => 'ROLE_ADMIN', 'telephone' => '+21600000000', 'entreprise' => 0],
            ['prenom' => 'Sami', 'nom' => 'Benali', 'email' => 'sami.benali@techcorp.tn', 'password' => 'SamiRH2026!', 'role' => 'ROLE_RH', 'telephone' => '+21652111001', 'entreprise' => 0],
            ['prenom' => 'Amira', 'nom' => 'Trabelsi', 'email' => 'amira.trabelsi@financeplus.tn', 'password' => 'AmiraRH2026!', 'role' => 'ROLE_RH', 'telephone' => '+21652111002', 'entreprise' => 1],
            ['prenom' => 'Nour', 'nom' => 'Khelifi', 'email' => 'nour.khelifi@medtech.tn', 'password' => 'NourRH2026!', 'role' => 'ROLE_RH', 'telephone' => '+21652111003', 'entreprise' => 2],
            ['prenom' => 'Yasmine', 'nom' => 'Mansour', 'email' => 'yasmine.mansour@gmail.com', 'password' => 'Yasmine2026!', 'role' => 'ROLE_CANDIDAT', 'telephone' => '+21653222001'],
            ['prenom' => 'Mohamed', 'nom' => 'Bouazizi', 'email' => 'mohamed.bouazizi@gmail.com', 'password' => 'Mohamed2026!', 'role' => 'ROLE_CANDIDAT', 'telephone' => '+21653222002'],
            ['prenom' => 'Salma', 'nom' => 'Gharbi', 'email' => 'salma.gharbi@gmail.com', 'password' => 'Salma2026!', 'role' => 'ROLE_CANDIDAT', 'telephone' => '+21653222003'],
            ['prenom' => 'Karim', 'nom' => 'Jaziri', 'email' => 'karim.jaziri@gmail.com', 'password' => 'Karim2026!', 'role' => 'ROLE_CANDIDAT', 'telephone' => '+21653222004'],
            ['prenom' => 'Ines', 'nom' => 'Hammami', 'email' => 'ines.hammami@gmail.com', 'password' => 'Ines2026!', 'role' => 'ROLE_CANDIDAT', 'telephone' => '+21653222005'],
            ['prenom' => 'Ahmed', 'nom' => 'Riahi', 'email' => 'ahmed.riahi@greenenergy.tn', 'password' => 'AhmedRH2026!', 'role' => 'ROLE_RH', 'telephone' => '+21652111004', 'entreprise' => 3],
        ];

        $users = [];
        $credentialsTable = [];
        foreach ($usersData as $d) {
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $d['email']]);
            if ($existing) {
                $users[] = $existing;
                $io->note("User {$d['email']} already exists, skipping.");
                continue;
            }
            $u = new User();
            $u->setPrenom($d['prenom']);
            $u->setNom($d['nom']);
            $u->setEmail($d['email']);
            $u->setRoles([$d['role']]);
            $u->setTelephone($d['telephone']);
            $u->setPassword($this->hasher->hashPassword($u, $d['password']));
            $u->setTwoFactorEnabled(false);
            if (isset($d['entreprise'])) {
                $u->setEntreprise($entreprises[$d['entreprise']]);
            }
            $this->em->persist($u);
            $users[] = $u;
            $credentialsTable[] = [$d['prenom'] . ' ' . $d['nom'], $d['email'], $d['password'], $d['role']];
        }
        $this->em->flush();
        $io->text(count($users) . ' users ready.');

        // Helper: get users by role
        $getByRole = fn(string $role) => array_filter($users, fn(User $u) => in_array($role, $u->getRoles()));
        $rhUsers = array_values($getByRole('ROLE_RH'));
        $candidats = array_values($getByRole('ROLE_CANDIDAT'));
        $allUsers = $users;

        // ═══════════════════════════════════════
        // 4. OFFRES
        // ═══════════════════════════════════════
        $io->section('Offres');
        $offresData = [
            ['titre' => 'Développeur Symfony Senior', 'description' => 'Nous recherchons un développeur Symfony expérimenté pour renforcer notre équipe technique. Vous travaillerez sur des projets variés avec une stack moderne: Symfony 6/7, API Platform, Docker, PostgreSQL.', 'localisation' => 'Tunis', 'typeContrat' => 'CDI', 'modeTravail' => 'HYBRID', 'salaireMin' => 3000, 'salaireMax' => 5000, 'entreprise' => 0, 'categorie' => 0],
            ['titre' => 'Stagiaire DevOps', 'description' => 'Stage de 6 mois en DevOps. Vous apprendrez Docker, Kubernetes, CI/CD avec GitLab et les services AWS.', 'localisation' => 'Tunis', 'typeContrat' => 'Stage', 'modeTravail' => 'ON_SITE', 'salaireMin' => 800, 'salaireMax' => 1200, 'entreprise' => 0, 'categorie' => 1],
            ['titre' => 'Développeur Full-Stack React/Node', 'description' => 'Poste full-stack pour développer des applications web avec React, Node.js et MongoDB.', 'localisation' => 'Tunis', 'typeContrat' => 'CDI', 'modeTravail' => 'REMOTE', 'salaireMin' => 2500, 'salaireMax' => 4500, 'entreprise' => 0, 'categorie' => 0],
            ['titre' => 'Analyste Financier', 'description' => "Poste d'analyste financier pour accompagner nos clients dans leur stratégie d'investissement. Maîtrise d'Excel, Power BI et modélisation financière requise.", 'localisation' => 'Sfax', 'typeContrat' => 'CDI', 'modeTravail' => 'ON_SITE', 'salaireMin' => 2500, 'salaireMax' => 4000, 'entreprise' => 1, 'categorie' => 3],
            ['titre' => 'Comptable Junior', 'description' => 'Poste de comptable junior en CDD. Gestion de la comptabilité générale, rapprochements bancaires et déclarations fiscales.', 'localisation' => 'Sfax', 'typeContrat' => 'CDD', 'modeTravail' => 'ON_SITE', 'salaireMin' => 1500, 'salaireMax' => 2000, 'entreprise' => 1, 'categorie' => 3],
            ['titre' => 'Data Scientist', 'description' => 'Rejoignez notre équipe data pour développer des modèles de Machine Learning appliqués à la santé. Python, TensorFlow, scikit-learn requis.', 'localisation' => 'Sousse', 'typeContrat' => 'CDI', 'modeTravail' => 'HYBRID', 'salaireMin' => 3500, 'salaireMax' => 6000, 'entreprise' => 2, 'categorie' => 2],
            ['titre' => 'Développeur Mobile Flutter', 'description' => 'Développement d\'applications mobiles cross-platform avec Flutter/Dart pour des solutions e-santé.', 'localisation' => 'Sousse', 'typeContrat' => 'CDI', 'modeTravail' => 'ON_SITE', 'salaireMin' => 2000, 'salaireMax' => 3500, 'entreprise' => 2, 'categorie' => 0],
            ['titre' => 'Ingénieur Énergie Solaire', 'description' => 'Conception et dimensionnement de systèmes photovoltaïques. Certification PV requise, expérience avec PVsyst un plus.', 'localisation' => 'Tunis', 'typeContrat' => 'CDI', 'modeTravail' => 'ON_SITE', 'salaireMin' => 2500, 'salaireMax' => 4000, 'entreprise' => 3, 'categorie' => 6],
            ['titre' => 'Technicien Maintenance Éolienne', 'description' => 'Maintenance préventive et corrective des éoliennes. Travail en hauteur, habilitations électriques requises.', 'localisation' => 'Bizerte', 'typeContrat' => 'CDD', 'modeTravail' => 'ON_SITE', 'salaireMin' => 1800, 'salaireMax' => 2800, 'entreprise' => 3, 'categorie' => 6],
            ['titre' => 'Community Manager', 'description' => 'Gestion des réseaux sociaux, création de contenu, analyse de performance et reporting.', 'localisation' => 'Tunis', 'typeContrat' => 'Freelance', 'modeTravail' => 'REMOTE', 'salaireMin' => 1200, 'salaireMax' => 2000, 'entreprise' => 0, 'categorie' => 5],
            ['titre' => 'Stagiaire Data Analyst', 'description' => 'Stage de 4 mois en analyse de données. SQL, Python, Power BI. Encadrement par un senior data analyst.', 'localisation' => 'Sfax', 'typeContrat' => 'Stage', 'modeTravail' => 'ON_SITE', 'salaireMin' => 600, 'salaireMax' => 900, 'entreprise' => 1, 'categorie' => 2],
            ['titre' => 'Chef de Projet IT', 'description' => 'Pilotage de projets IT, gestion d\'équipe Agile/Scrum, suivi budgétaire et reporting. Certification PMP appréciée.', 'localisation' => 'Tunis', 'typeContrat' => 'CDI', 'modeTravail' => 'HYBRID', 'salaireMin' => 4000, 'salaireMax' => 7000, 'entreprise' => 0, 'categorie' => 0],
        ];

        $offres = [];
        foreach ($offresData as $d) {
            $o = new Offre();
            $o->setTitre($d['titre']);
            $o->setDescription($d['description']);
            $o->setLocalisation($d['localisation']);
            $o->setTypeContrat($d['typeContrat']);
            $o->setModeTravail($d['modeTravail']);
            $o->setSalaireMin($d['salaireMin']);
            $o->setSalaireMax($d['salaireMax']);
            $o->setActive(true);
            $o->setEntreprise($entreprises[$d['entreprise']]);
            $o->setCategorie($categories[$d['categorie']]);
            $this->em->persist($o);
            $offres[] = $o;
        }
        $this->em->flush();
        $io->text(count($offres) . ' offres created.');

        // ═══════════════════════════════════════
        // 5. AVANTAGES (for offres)
        // ═══════════════════════════════════════
        $io->section('Avantages');
        $avantagesPool = [
            ['nom' => 'Assurance santé', 'type' => 'Bien-être', 'description' => 'Couverture santé complète pour vous et votre famille.'],
            ['nom' => 'Tickets restaurant', 'type' => 'Financier', 'description' => 'Tickets restaurant de 10 DT par jour ouvrable.'],
            ['nom' => 'PC portable', 'type' => 'Matériel', 'description' => 'MacBook Pro ou Dell XPS au choix.'],
            ['nom' => 'Prime annuelle', 'type' => 'Financier', 'description' => 'Prime de performance annuelle pouvant aller jusqu\'à 2 mois de salaire.'],
            ['nom' => 'Formation continue', 'type' => 'Bien-être', 'description' => 'Budget formation de 2000 DT/an pour conférences, certifications et formations.'],
            ['nom' => 'Télétravail flexible', 'type' => 'Bien-être', 'description' => 'Possibilité de télétravail 2 à 3 jours par semaine.'],
            ['nom' => 'Transport', 'type' => 'Financier', 'description' => 'Indemnité transport mensuelle.'],
        ];
        $avantageCount = 0;
        foreach ($offres as $offre) {
            $numAvantages = rand(2, 4);
            $keys = array_rand($avantagesPool, $numAvantages);
            if (!is_array($keys)) $keys = [$keys];
            foreach ($keys as $k) {
                $ad = $avantagesPool[$k];
                $a = new Avantage();
                $a->setNom($ad['nom']);
                $a->setType($ad['type']);
                $a->setDescription($ad['description']);
                $a->setOffre($offre);
                $this->em->persist($a);
                $avantageCount++;
            }
        }
        $this->em->flush();
        $io->text($avantageCount . ' avantages created.');

        // ═══════════════════════════════════════
        // 6. CANDIDATURES
        // ═══════════════════════════════════════
        $io->section('Candidatures');
        $contratTypes = ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'];
        $statuts = ['En attente', 'Acceptée', 'Refusée', 'Entretien'];
        $niveaux = ['Bac', 'Bac+2', 'Bac+3', 'Bac+5', 'Doctorat'];
        $competencesList = [
            'PHP, Symfony, MySQL, Docker, Git',
            'Python, TensorFlow, Pandas, scikit-learn',
            'React, Node.js, MongoDB, TypeScript',
            'Flutter, Dart, Firebase, REST API',
            'Excel, Power BI, SQL, VBA',
            'Java, Spring Boot, Microservices',
            'AWS, Terraform, Kubernetes, Jenkins',
        ];

        $candidatures = [];
        foreach ($candidats as $candidat) {
            // Each candidat applies to 2-4 offres
            $numApps = rand(2, 4);
            $shuffledOffres = $offres;
            shuffle($shuffledOffres);
            for ($i = 0; $i < min($numApps, count($shuffledOffres)); $i++) {
                $offre = $shuffledOffres[$i];
                $daysAgo = rand(1, 30);
                $statut = $statuts[array_rand($statuts)];

                $cand = new Candidature();
                $cand->setTitrePoste($offre->getTitre());
                $cand->setEntreprise($offre->getEntreprise()->getNom());
                $cand->setTypeContrat($offre->getTypeContrat());
                $cand->setDescription("Je postule pour le poste de {$offre->getTitre()}. Mon profil correspond aux exigences et je suis motivé(e) pour rejoindre votre équipe.");
                $cand->setDateCandidature(new \DateTimeImmutable("-{$daysAgo} days"));
                $cand->setStatut($statut);
                $cand->setOffre($offre);
                $cand->setCandidat($candidat);
                $cand->setLieu($offre->getLocalisation());
                $cand->setSalaireSouhaite(rand((int) $offre->getSalaireMin(), (int) $offre->getSalaireMax()));
                $cand->setCompetences($competencesList[array_rand($competencesList)]);
                $cand->setNiveauEtudes($niveaux[array_rand($niveaux)]);
                $cand->setAnneesExperience(rand(0, 8));
                $this->em->persist($cand);
                $candidatures[] = $cand;
            }
        }
        $this->em->flush();
        $io->text(count($candidatures) . ' candidatures created.');

        // ═══════════════════════════════════════
        // 7. ENTRETIENS
        // ═══════════════════════════════════════
        $io->section('Entretiens');
        $entretienTypes = Entretien::TYPES;
        $entretiens = [];

        // Create entretiens for candidatures with 'Entretien' status
        foreach ($candidatures as $cand) {
            if ($cand->getStatut() !== 'Entretien') continue;

            $type = $entretienTypes[array_rand($entretienTypes)];
            $daysFromNow = rand(-10, 15);
            $ent = new Entretien();
            $ent->setCandidatureId($cand->getId());
            $ent->setDateHeure(new \DateTime("$daysFromNow days 10:00"));
            $ent->setType($type);
            $ent->setStatut($daysFromNow < 0 ? 'REALISE' : 'PLANIFIE');

            if ($type === 'PRESENTIEL') {
                $ent->setLieu($cand->getLieu() ?? 'Tunis');
            } elseif ($type === 'EN_LIGNE') {
                $ent->setLien('https://meet.talentflow.tn/interview-' . $cand->getId());
            }

            if ($ent->getStatut() === 'REALISE') {
                $ent->setNoteTechnique(rand(8, 19));
                $ent->setNoteCommunication(rand(8, 18));
                $ent->setCommentaire('Le candidat a montré de bonnes compétences techniques et une bonne communication.');
            }

            $this->em->persist($ent);
            $entretiens[] = $ent;
        }

        // Also create some for Acceptée/Refusée candidatures (they already went through interviews)
        foreach ($candidatures as $cand) {
            if (!in_array($cand->getStatut(), ['Acceptée', 'Refusée'])) continue;

            $type = $entretienTypes[array_rand($entretienTypes)];
            $daysAgo = rand(5, 25);
            $ent = new Entretien();
            $ent->setCandidatureId($cand->getId());
            $ent->setDateHeure(new \DateTime("-$daysAgo days 14:00"));
            $ent->setType($type);
            $ent->setStatut('REALISE');

            if ($type === 'PRESENTIEL') {
                $ent->setLieu($cand->getLieu() ?? 'Sfax');
            } elseif ($type === 'EN_LIGNE') {
                $ent->setLien('https://meet.talentflow.tn/interview-' . $cand->getId());
            }

            $ent->setNoteTechnique(rand(6, 20));
            $ent->setNoteCommunication(rand(6, 19));
            $ent->setCommentaire($cand->getStatut() === 'Acceptée'
                ? 'Excellent candidat, profil validé par l\'équipe technique.'
                : 'Candidat intéressant mais le profil ne correspond pas entièrement aux attentes.');

            $this->em->persist($ent);
            $entretiens[] = $ent;
        }
        $this->em->flush();
        $io->text(count($entretiens) . ' entretiens created.');

        // ═══════════════════════════════════════
        // 8. DECISIONS FINALES
        // ═══════════════════════════════════════
        $io->section('Decisions finales');
        $decisionCount = 0;
        foreach ($entretiens as $ent) {
            if ($ent->getStatut() !== 'REALISE') continue;

            // Find the candidature to determine decision
            $candId = $ent->getCandidatureId();
            $relatedCand = null;
            foreach ($candidatures as $c) {
                if ($c->getId() === $candId) {
                    $relatedCand = $c;
                    break;
                }
            }

            $decision = match ($relatedCand?->getStatut()) {
                'Acceptée' => 'ACCEPTE',
                'Refusée' => 'REFUSE',
                default => 'EN_ATTENTE',
            };

            $df = new DecisionFinale();
            $df->setEntretien($ent);
            $df->setDecision($decision);
            $df->setDateDecision(new \DateTime());
            $score = ($ent->getNoteTechnique() ?? 10) * 0.7 + ($ent->getNoteCommunication() ?? 10) * 0.3;
            $df->setScore(round($score, 1));

            if ($decision === 'ACCEPTE') {
                $df->setMotif('Profil validé — compétences et motivation confirmées.');
            } elseif ($decision === 'REFUSE') {
                $df->setMotif('Profil ne correspondant pas aux exigences du poste.');
            }

            $this->em->persist($df);
            $decisionCount++;
        }
        $this->em->flush();
        $io->text($decisionCount . ' decisions finales created.');

        // ═══════════════════════════════════════
        // 9. POSTS
        // ═══════════════════════════════════════
        $io->section('Posts');
        $postsData = [
            ['title' => 'Les tendances du recrutement en 2026', 'content' => "Le marché du recrutement évolue rapidement avec l'IA et l'automatisation. Les entreprises adoptent de plus en plus les entretiens asynchrones et les évaluations basées sur les compétences plutôt que les diplômes. Les soft skills comme la communication et l'adaptabilité sont désormais aussi importants que les compétences techniques.", 'daysAgo' => 0],
            ['title' => 'Comment réussir son entretien technique ?', 'content' => "Après avoir passé des dizaines d'entretiens techniques, voici mes conseils : 1) Pratiquez sur LeetCode au moins 30 min par jour. 2) Ne vous précipitez pas — prenez le temps de comprendre le problème. 3) Communiquez votre raisonnement à voix haute. 4) N'hésitez pas à poser des questions de clarification.", 'daysAgo' => 1],
            ['title' => "Retour d'expérience : stage en développement web", 'content' => "Je viens de terminer mon stage de 6 mois en tant que développeur web full-stack. J'ai travaillé avec Symfony, React et PostgreSQL. La chose la plus importante que j'ai apprise n'est pas technique — c'est la communication en équipe.", 'daysAgo' => 2],
            ['title' => 'Symfony 7.4 : les nouveautés', 'content' => "La dernière version de Symfony apporte des améliorations significatives : le nouveau composant Scheduler, les améliorations du serializer, et le support natif de TypedProperty. Le DX continue de s'améliorer.", 'daysAgo' => 3],
            ['title' => "L'importance du portfolio pour les juniors", 'content' => "En tant que recruteur RH tech, je regarde toujours le GitHub et le portfolio avant le CV. Un projet personnel bien documenté vaut plus qu'une liste de technologies. Montrez ce que vous savez faire.", 'daysAgo' => 4],
            ['title' => 'Freelance vs CDI en Tunisie', 'content' => "Après 3 ans en CDI et 2 ans en freelance, voici mon analyse. Le CDI offre la stabilité et les avantages sociaux (CNSS, congés payés). Le freelance offre la liberté et souvent un meilleur revenu, mais demande une discipline financière.", 'daysAgo' => 5],
            ['title' => 'Offre de stage : Développeur PHP/Symfony', 'content' => "Notre entreprise recrute un(e) stagiaire développeur PHP/Symfony pour 4 à 6 mois. Profil recherché : étudiant en informatique, PHP 8+, notions Symfony, SQL et Git. Encadrement par un tech lead senior.", 'daysAgo' => 6],
            ['title' => 'Docker pour les débutants', 'content' => "Docker peut sembler intimidant au début, mais c'est essentiel en 2026. Commencez par comprendre image, container, volume et network. Ensuite dockerisez un projet simple. Un bon exercice : dockerisez votre projet Symfony !", 'daysAgo' => 7],
            ['title' => 'Les certifications IT valent-elles le coup ?', 'content' => "AWS Solutions Architect, Symfony Certified Developer... Les certifications sont de plus en plus demandées. Mais une certification sans expérience n'a pas beaucoup de valeur. Pratiquez d'abord, certifiez-vous ensuite.", 'daysAgo' => 10],
            ['title' => 'Meetup Développeurs Tunis — Prochaine édition', 'content' => "Le prochain meetup des développeurs de Tunis aura lieu le 25 avril à la Cité des Sciences. Au programme : architecture hexagonale en PHP, atelier tests automatisés, et networking autour d'un café. Inscription gratuite !", 'daysAgo' => 12],
            ['title' => 'Comment négocier son salaire en IT ?', 'content' => "La négociation salariale est un art. Faites vos recherches sur les salaires du marché, présentez vos réalisations concrètes et chiffrez votre impact. Ne donnez jamais votre salaire actuel en premier.", 'daysAgo' => 14],
            ['title' => 'Les meilleures pratiques Git en équipe', 'content' => "Après 5 ans de travail en équipe, voici mes règles d'or : branches courtes, commits atomiques, messages descriptifs, pull requests avec review, et JAMAIS de force push sur main.", 'daysAgo' => 15],
        ];

        // Check existing posts
        $existingPostCount = $this->em->getRepository(Post::class)->count([]);
        $posts = [];
        if ($existingPostCount > 0 && !$input->getOption('fresh')) {
            $posts = $this->em->getRepository(Post::class)->findAll();
            $io->note("$existingPostCount posts already exist, skipping.");
        } else {
            foreach ($postsData as $i => $pData) {
                $post = new Post();
                $post->setTitle($pData['title']);
                $post->setContent($pData['content']);
                $post->setAuthor($allUsers[$i % count($allUsers)]);
                $post->setCreatedAt(new \DateTimeImmutable("-{$pData['daysAgo']} days"));
                $post->setUpvotes(0);
                $this->em->persist($post);
                $posts[] = $post;
            }
            $this->em->flush();
            $io->text(count($posts) . ' posts created.');
        }

        // ═══════════════════════════════════════
        // 10. COMMENTS
        // ═══════════════════════════════════════
        $io->section('Comments');
        $existingCommentCount = $this->em->getRepository(Comment::class)->count([]);
        if ($existingCommentCount > 0 && !$input->getOption('fresh')) {
            $io->note("$existingCommentCount comments already exist, skipping.");
        } else {
            $commentsPool = [
                "Excellent article, merci pour le partage !",
                "Je suis totalement d'accord avec ton analyse.",
                "Intéressant, je n'avais pas pensé à ça.",
                "Merci pour ces conseils, très utile pour les débutants.",
                "Est-ce que tu pourrais détailler un peu plus ?",
                "J'ai vécu la même expérience, ça résonne beaucoup.",
                "Super retour d'expérience, bravo !",
                "Je ne suis pas d'accord sur la partie freelance, mais le reste est top.",
                "Quelqu'un a un retour d'expérience similaire ?",
                "C'est exactement ce que les juniors ont besoin d'entendre.",
                "Je recommande aussi les hackathons pour se faire remarquer.",
                "Merci pour l'info, je vais postuler !",
                "Docker + Symfony c'est vraiment un game changer.",
                "Je serai au meetup, hâte d'y être !",
                "Les certifications m'ont vraiment aidé dans ma carrière.",
                "Bon récap, j'aurais aimé lire ça quand j'ai débuté.",
                "Très bon point sur les soft skills, souvent sous-estimés.",
                "Tu recommandes quoi comme formation en ligne ?",
                "Le marché tunisien évolue vite, c'est encourageant.",
                "J'aimerais voir plus de contenu comme celui-ci !",
            ];
            $commentIdx = 0;
            foreach ($posts as $post) {
                $numComments = rand(2, 5);
                for ($c = 0; $c < $numComments; $c++) {
                    $comment = new Comment();
                    $comment->setPost($post);
                    $comment->setAuthor($allUsers[array_rand($allUsers)]);
                    $comment->setContent($commentsPool[$commentIdx % count($commentsPool)]);
                    $hoursAgo = rand(1, max(1, $post->getCreatedAt()->diff(new \DateTimeImmutable())->days * 24) ?: 1);
                    $comment->setCreatedAt(new \DateTimeImmutable("-{$hoursAgo} hours"));
                    $this->em->persist($comment);
                    $commentIdx++;
                }
            }
            $this->em->flush();
            $io->text($commentIdx . ' comments created.');
        }

        // ═══════════════════════════════════════
        // 11. VOTES
        // ═══════════════════════════════════════
        $io->section('Votes');
        $existingVoteCount = $this->em->getRepository(Vote::class)->count([]);
        if ($existingVoteCount > 0 && !$input->getOption('fresh')) {
            $io->note("$existingVoteCount votes already exist, skipping.");
        } else {
            $voteCount = 0;
            foreach ($posts as $post) {
                $shuffled = $allUsers;
                shuffle($shuffled);
                $numVoters = rand(intdiv(count($shuffled), 2), count($shuffled));
                $upvotes = 0;

                for ($v = 0; $v < $numVoters; $v++) {
                    $voter = $shuffled[$v];
                    if ($voter === $post->getAuthor() && rand(0, 1)) continue;

                    $existing = $this->em->getRepository(Vote::class)->findOneBy([
                        'user' => $voter,
                        'post' => $post,
                    ]);
                    if ($existing) continue;

                    $type = rand(1, 10) <= 7 ? Vote::TYPE_UP : Vote::TYPE_DOWN;
                    $vote = new Vote();
                    $vote->setUser($voter);
                    $vote->setPost($post);
                    $vote->setType($type);
                    $this->em->persist($vote);
                    $voteCount++;
                    $upvotes += ($type === Vote::TYPE_UP) ? 1 : -1;
                }
                $post->setUpvotes($upvotes);
            }
            $this->em->flush();
            $io->text($voteCount . ' votes created.');
        }

        // ═══════════════════════════════════════
        // 12. CONVERSATIONS & MESSAGES
        // ═══════════════════════════════════════
        $io->section('Conversations & Messages');
        $existingConvCount = $this->em->getRepository(Conversation::class)->count([]);
        if ($existingConvCount > 0 && !$input->getOption('fresh')) {
            $io->note("$existingConvCount conversations already exist, skipping.");
        } else {
            $dmData = [
                [0, 4, [
                    [0, "Bonjour Yasmine, j'ai vu votre candidature pour le poste Symfony. Votre profil est intéressant."],
                    [4, "Merci beaucoup ! Je suis très motivée pour ce poste. Quand puis-je passer un entretien ?"],
                    [0, "Nous pouvons planifier un entretien technique la semaine prochaine. Mardi ou jeudi vous convient ?"],
                    [4, "Mardi serait parfait pour moi. Merci !"],
                    [0, "C'est noté. Je vous envoie les détails par email. Bonne journée !"],
                ]],
                [1, 5, [
                    [1, "Bonjour Sami, j'aimerais postuler pour le poste d'analyste financier."],
                    [5, "Bonjour Mohamed ! Bien sûr, envoyez votre CV via la plateforme de candidature."],
                    [1, "C'est fait ! J'ai aussi ajouté une lettre de motivation."],
                    [5, "Parfait, nous reviendrons vers vous sous 48h."],
                ]],
                [2, 6, [
                    [2, "Salma, bienvenue sur TalentFlow ! N'hésitez pas si vous avez des questions."],
                    [6, "Merci Nour ! J'ai une question sur le poste Data Scientist chez MedTech."],
                    [2, "Bien sûr, que voulez-vous savoir ?"],
                    [6, "Est-ce que le poste est ouvert aux profils avec 2 ans d'expérience ?"],
                    [2, "Oui, nous cherchons des profils de 2 à 5 ans d'expérience. N'hésitez pas à postuler !"],
                    [6, "Super, je postule tout de suite. Merci pour l'info !"],
                ]],
                [4, 7, [
                    [4, "Salut Karim ! Tu as vu le meetup développeurs du 25 avril ?"],
                    [7, "Oui ! Je me suis déjà inscrit. Tu y vas aussi ?"],
                    [4, "Oui bien sûr, ça va être intéressant. On s'y retrouve ?"],
                    [7, "Avec plaisir ! On peut prendre un café avant."],
                ]],
                [0, 8, [
                    [0, "Bonjour Ines, votre profil nous intéresse pour un poste DevOps."],
                    [8, "Bonjour ! C'est super, j'adorerais en savoir plus sur les missions."],
                    [0, "Le poste implique CI/CD, Docker et Kubernetes. Êtes-vous disponible pour un appel ?"],
                    [8, "Oui, je suis disponible demain à 14h."],
                    [0, "Parfait, je vous appelle demain. À bientôt !"],
                ]],
                [3, 9, [
                    [3, "Bonjour Ahmed, j'ai une question sur le poste d'ingénieur solaire."],
                    [9, "Bonjour ! Allez-y, je vous écoute."],
                    [3, "Est-ce que la certification PV est vraiment obligatoire ?"],
                    [9, "C'est fortement recommandé mais pas éliminatoire. L'expérience terrain compte aussi."],
                ]],
            ];

            $msgCount = 0;
            foreach ($dmData as [$u1Idx, $u2Idx, $messages]) {
                if (!isset($allUsers[$u1Idx]) || !isset($allUsers[$u2Idx])) continue;

                $conv = new Conversation();
                $conv->setUserOne($allUsers[$u1Idx]);
                $conv->setUserTwo($allUsers[$u2Idx]);
                // createdAt is set by lifecycle callback
                $conv->setUpdatedAt(new \DateTimeImmutable());
                $this->em->persist($conv);
                $this->em->flush(); // flush to get conv ID

                foreach ($messages as $idx => [$senderIdx, $content]) {
                    $msg = new Message();
                    $msg->setConversation($conv);
                    $msg->setSender($allUsers[$senderIdx]);
                    $msg->setContent($content);
                    $minutesAgo = (count($messages) - $idx) * rand(15, 120);
                    $msg->setCreatedAt(new \DateTimeImmutable("-{$minutesAgo} minutes"));
                    $msg->setIsRead($idx < count($messages) - 1); // last message unread
                    $this->em->persist($msg);
                    $msgCount++;
                }
            }
            $this->em->flush();
            $io->text(count($dmData) . " conversations with $msgCount messages created.");
        }

        // ═══════════════════════════════════════
        // SUMMARY
        // ═══════════════════════════════════════
        $io->newLine();
        $io->success('All data seeded successfully!');

        if (!empty($credentialsTable)) {
            $io->section('User Credentials');
            $io->table(['Name', 'Email', 'Password', 'Role'], $credentialsTable);
        }

        $io->section('Data Summary');
        $io->table(['Module', 'Count'], [
            ['Entreprises', count($entreprises)],
            ['Categories', count($categories)],
            ['Users', count($users)],
            ['Offres', count($offres)],
            ['Avantages', $avantageCount],
            ['Candidatures', count($candidatures)],
            ['Entretiens', count($entretiens)],
            ['Decisions finales', $decisionCount],
            ['Posts', count($posts)],
            ['Conversations', count($dmData ?? [])],
        ]);

        return Command::SUCCESS;
    }
}
