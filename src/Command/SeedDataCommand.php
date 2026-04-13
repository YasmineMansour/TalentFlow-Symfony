<?php

namespace App\Command;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-data',
    description: 'Seed users, posts, comments and votes for testing',
)]
class SeedDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Fetch existing users
        $allUsers = $this->em->getRepository(User::class)->findAll();
        $existingEmails = array_map(fn(User $u) => $u->getEmail(), $allUsers);

        // New users to create
        $newUsersData = [
            [
                'prenom' => 'Karim',
                'nom' => 'Trabelsi',
                'email' => 'karim.trabelsi@talentflow.com',
                'password' => 'Karim2026!',
                'role' => 'ROLE_RH',
                'telephone' => '+21652000111',
            ],
            [
                'prenom' => 'Amira',
                'nom' => 'Jaziri',
                'email' => 'amira.jaziri@talentflow.com',
                'password' => 'Amira2026!',
                'role' => 'ROLE_CANDIDAT',
                'telephone' => '+21653000222',
            ],
        ];

        $createdUsers = [];
        foreach ($newUsersData as $data) {
            if (in_array($data['email'], $existingEmails)) {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
                $createdUsers[] = $user;
                $io->note("User {$data['email']} already exists, skipping creation.");
                continue;
            }
            $user = new User();
            $user->setPrenom($data['prenom']);
            $user->setNom($data['nom']);
            $user->setEmail($data['email']);
            $user->setRoles([$data['role']]);
            $user->setTelephone($data['telephone']);
            $user->setPassword($this->hasher->hashPassword($user, $data['password']));
            $this->em->persist($user);
            $createdUsers[] = $user;
        }
        $this->em->flush();

        // Collect all users (existing + new)
        $allUsers = $this->em->getRepository(User::class)->findAll();

        // Posts data
        $postsData = [
            [
                'title' => 'Les tendances du recrutement en 2026',
                'content' => "Le marché du recrutement évolue rapidement avec l'IA et l'automatisation. Les entreprises adoptent de plus en plus les entretiens asynchrones et les évaluations basées sur les compétences plutôt que les diplômes. Les soft skills comme la communication et l'adaptabilité sont désormais aussi importants que les compétences techniques. Voici les 5 tendances majeures à surveiller cette année.",
                'daysAgo' => 0,
            ],
            [
                'title' => 'Comment réussir son entretien technique ?',
                'content' => "Après avoir passé des dizaines d'entretiens techniques, voici mes conseils : 1) Pratiquez sur LeetCode au moins 30 min par jour. 2) Ne vous précipitez pas — prenez le temps de comprendre le problème. 3) Communiquez votre raisonnement à voix haute. 4) N'hésitez pas à poser des questions de clarification. 5) Révisez les fondamentaux : structures de données, algorithmes, et design patterns.",
                'daysAgo' => 1,
            ],
            [
                'title' => 'Retour d\'expérience : stage en développement web',
                'content' => "Je viens de terminer mon stage de 6 mois en tant que développeur web full-stack. J'ai travaillé avec Symfony, React et PostgreSQL. La chose la plus importante que j'ai apprise n'est pas technique — c'est la communication en équipe. Les code reviews m'ont énormément fait progresser. Si vous êtes en recherche de stage, n'hésitez pas à me contacter !",
                'daysAgo' => 2,
            ],
            [
                'title' => 'Symfony 7.4 : les nouveautés à ne pas manquer',
                'content' => "La dernière version de Symfony apporte des améliorations significatives : le nouveau composant Scheduler, les améliorations de performance du serializer, et le support natif de TypedProperty. Le DX (Developer Experience) continue de s'améliorer avec le profiler web redesigné. Qui a déjà migré vers la 7.4 ? Partagez vos retours !",
                'daysAgo' => 3,
            ],
            [
                'title' => 'L\'importance du portfolio pour les développeurs juniors',
                'content' => "En tant que recruteur RH spécialisé tech, je regarde toujours le GitHub et le portfolio avant le CV. Un projet personnel bien documenté vaut plus qu'une liste de technologies sur un CV. Montrez ce que vous savez faire, pas ce que vous prétendez savoir. Quelques idées de projets : une API REST, un clone simplifié d'une app connue, ou une contribution open source.",
                'daysAgo' => 4,
            ],
            [
                'title' => 'Freelance vs CDI : quel statut choisir en Tunisie ?',
                'content' => "Après 3 ans en CDI et 2 ans en freelance, voici mon analyse. Le CDI offre la stabilité et les avantages sociaux (CNSS, congés payés). Le freelance offre la liberté et souvent un meilleur revenu, mais demande une discipline financière et une capacité à gérer l'incertitude. Pour les juniors, je recommande de commencer en CDI pour acquérir l'expérience, puis de considérer le freelance après 3-4 ans.",
                'daysAgo' => 5,
            ],
            [
                'title' => 'Offre de stage : Développeur PHP/Symfony',
                'content' => "Notre entreprise recrute un(e) stagiaire développeur PHP/Symfony pour une mission de 4 à 6 mois. Profil recherché : étudiant en informatique, connaissances en PHP 8+, notions de Symfony, SQL, et Git. Avantages : encadrement par un tech lead senior, possibilité d'embauche, et une vraie exposition aux projets clients. Envoyez votre CV à recrutement@talentflow.com.",
                'daysAgo' => 6,
            ],
            [
                'title' => 'Docker pour les débutants : par où commencer ?',
                'content' => "Docker peut sembler intimidant au début, mais c'est essentiel en 2026. Commencez par comprendre les concepts de base : image, container, volume, et network. Ensuite, dockerisez un projet simple avec un Dockerfile. Puis passez à docker-compose pour orchestrer plusieurs services (PHP, MySQL, Nginx). Un bon exercice : dockerisez votre projet Symfony actuel !",
                'daysAgo' => 7,
            ],
            [
                'title' => 'Les certifications IT : valent-elles le coup ?',
                'content' => "AWS Solutions Architect, Symfony Certified Developer, Google Cloud... Les certifications techniques sont de plus en plus demandées. Mais attention : une certification sans expérience pratique n'a pas beaucoup de valeur. Mon conseil : pratiquez d'abord, puis certifiez-vous pour valider et formaliser vos compétences. Les recruteurs apprécient la combinaison expérience + certification.",
                'daysAgo' => 10,
            ],
            [
                'title' => 'Meetup Développeurs Tunis — Prochaine édition',
                'content' => "Le prochain meetup des développeurs de Tunis aura lieu le 25 avril à la Cité des Sciences. Au programme : une conférence sur l'architecture hexagonale en PHP, un atelier pratique sur les tests automatisés, et un networking autour d'un café. Inscription gratuite mais places limitées ! Qui sera présent ? 🙋‍♂️",
                'daysAgo' => 12,
            ],
        ];

        // Comments data
        $commentsPool = [
            "Excellent article, merci pour le partage !",
            "Je suis totalement d'accord avec ton analyse.",
            "Intéressant, je n'avais pas pensé à ça.",
            "Merci pour ces conseils, c'est très utile pour les débutants.",
            "Est-ce que tu pourrais détailler un peu plus le point 3 ?",
            "J'ai vécu la même expérience, ça résonne beaucoup.",
            "Super retour d'expérience, bravo !",
            "Je ne suis pas d'accord sur la partie freelance, mais le reste est top.",
            "Quelqu'un a un retour d'expérience similaire ?",
            "C'est exactement ce que les juniors ont besoin d'entendre.",
            "Je recommande aussi de participer à des hackathons pour se faire remarquer.",
            "Merci pour l'info, je vais postuler !",
            "Docker + Symfony c'est vraiment un game changer.",
            "Je serai au meetup, hâte d'y être !",
            "Les certifications m'ont vraiment aidé dans ma carrière.",
            "Bon récap, j'aurais aimé lire ça quand j'ai débuté.",
            "Très bon point sur les soft skills, souvent sous-estimés.",
            "Tu recommandes quoi comme formation en ligne pour Symfony ?",
            "Le marché tunisien évolue vite, c'est encourageant.",
            "J'aimerais voir plus de contenu comme celui-ci sur la plateforme.",
        ];

        $posts = [];
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

        // Add comments (2-5 per post)
        $commentIndex = 0;
        foreach ($posts as $post) {
            $numComments = rand(2, 5);
            for ($c = 0; $c < $numComments; $c++) {
                $comment = new Comment();
                $comment->setPost($post);
                $comment->setAuthor($allUsers[array_rand($allUsers)]);
                $comment->setContent($commentsPool[$commentIndex % count($commentsPool)]);
                $hoursAgo = rand(1, $post->getCreatedAt()->diff(new \DateTimeImmutable())->days * 24 ?: 1);
                $comment->setCreatedAt(new \DateTimeImmutable("-{$hoursAgo} hours"));
                $this->em->persist($comment);
                $commentIndex++;
            }
        }
        $this->em->flush();

        // Add votes (each user votes on most posts)
        foreach ($posts as $post) {
            $voters = $allUsers;
            shuffle($voters);
            $numVoters = rand(intdiv(count($voters), 2), count($voters));
            $upvoteCount = 0;

            for ($v = 0; $v < $numVoters; $v++) {
                $voter = $voters[$v];
                // Skip if author votes on own post sometimes
                if ($voter === $post->getAuthor() && rand(0, 1)) continue;

                $existing = $this->em->getRepository(Vote::class)->findOneBy([
                    'user' => $voter,
                    'post' => $post,
                ]);
                if ($existing) continue;

                $type = rand(1, 10) <= 7 ? Vote::TYPE_UP : Vote::TYPE_DOWN; // 70% upvote
                $vote = new Vote();
                $vote->setUser($voter);
                $vote->setPost($post);
                $vote->setType($type);
                $this->em->persist($vote);

                if ($type === Vote::TYPE_UP) {
                    $upvoteCount++;
                } else {
                    $upvoteCount--;
                }
            }

            $post->setUpvotes($upvoteCount);
        }
        $this->em->flush();

        $io->success('Data seeded successfully!');

        $io->section('New Users');
        $rows = [];
        foreach ($newUsersData as $data) {
            $rows[] = [$data['prenom'] . ' ' . $data['nom'], $data['email'], $data['password'], $data['role']];
        }
        $io->table(['Nom', 'Email', 'Mot de passe', 'Rôle'], $rows);

        $io->section('Summary');
        $io->listing([
            count($posts) . ' posts created',
            $commentIndex . ' comments created',
            'Votes distributed across all users',
        ]);

        return Command::SUCCESS;
    }
}
