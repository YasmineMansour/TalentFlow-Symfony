<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\EntretienRepository;
use App\Repository\OffreRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ChatbotService
{
    public function __construct(
        private OffreRepository $offreRepository,
        private CandidatureRepository $candidatureRepository,
        private EntretienRepository $entretienRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function handleMessage(string $message, ?User $user): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['response' => 'Veuillez saisir un message.', 'type' => 'error'];
        }

        $lower = mb_strtolower($message, 'UTF-8');
        $normalized = $this->normalizeText($message);

        // Determine role
        $role = 'visitor';
        if ($user) {
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                $role = 'admin';
            } elseif (in_array('ROLE_RH', $user->getRoles())) {
                $role = 'rh';
            } else {
                $role = 'candidat';
            }
        }

        // Try to match intent
        $response = $this->matchIntent($lower, $normalized, $role, $user);

        return ['response' => $response, 'type' => 'success', 'role' => $role];
    }

    private function matchIntent(string $lower, string $normalized, string $role, ?User $user): string
    {
        // ── Greetings ──
        if (preg_match('/^(bonjour|salut|hello|hi|hey|bonsoir|coucou)/u', $lower)) {
            return $this->greet($role, $user);
        }

        // ── Role-based guided journey ──
        if ($this->containsIntent($normalized, ['guide', 'parcours', 'commencer', 'debuter', 'etapes', 'etape', 'workflow', 'role', 'roles'])) {
            return $this->roleBasedGuidance($role);
        }

        // ── Help ──
        if ($this->containsIntent($normalized, ['aide', 'help', 'comment', 'fonctionnalites'])) {
            return $this->showHelp($role);
        }

        // ── Search for offers / domains ──
        if ($this->containsIntent($normalized, ['offre', 'offres', 'emploi', 'job', 'poste', 'travail', 'cherche', 'recherche', 'domaine', 'stage', 'cdi', 'cdd', 'freelance', 'alternance'])) {
            return $this->handleOffreSearch($lower, $role);
        }

        // ── Candidatures ──
        if ($this->containsIntent($normalized, ['candidature', 'candidatures', 'candidat', 'postuler', 'postule', 'cv', 'lettre', 'dossier'])) {
            return $this->handleCandidatureQuery($lower, $role, $user);
        }

        // ── Interviews ──
        if ($this->containsIntent($normalized, ['entretien', 'entretiens', 'interview', 'reunion', 'rendezvous', 'rdv'])) {
            return $this->handleEntretienQuery($lower, $role, $user);
        }

        // ── Users (admin only) ──
        if ($this->containsIntent($normalized, ['utilisateur', 'utilisateurs', 'user', 'compte', 'membre', 'inscription'])) {
            return $this->handleUserQuery($lower, $role);
        }

        // ── Statistics (admin/rh) ──
        if ($this->containsIntent($normalized, ['statistique', 'statistiques', 'stats', 'chiffre', 'chiffres', 'nombre', 'combien', 'total', 'dashboard'])) {
            return $this->handleStatsQuery($lower, $role);
        }

        // ── About the site ──
        if ($this->containsIntent($normalized, ['talentflow', 'site', 'plateforme', 'cestquoi', 'present', 'about'])) {
            return $this->aboutSite($role);
        }

        // ── Navigation guide ──
        if ($this->containsIntent($normalized, ['naviguer', 'page', 'aller', 'acceder', 'ou', 'menu', 'section'])) {
            return $this->navigationGuide($role);
        }

        // ── Forum ──
        if ($this->containsIntent($normalized, ['forum', 'post', 'discussion', 'communaute'])) {
            return $this->handleForumInfo($role);
        }

        // ── Messages ──
        if ($this->containsIntent($normalized, ['message', 'messages', 'messagerie', 'contact', 'ecrire', 'envoyer'])) {
            return "💬 **Messagerie** : Vous pouvez envoyer des messages privés depuis la section **Messages** accessible dans votre tableau de bord après connexion.";
        }

        // ── Default fallback ──
        return $this->fallback($role);
    }

    private function greet(string $role, ?User $user): string
    {
        $name = $user ? $user->getPrenom() : 'visiteur';
        $greeting = "👋 Bonjour **$name** ! Je suis l'assistant TalentFlow.";

        return match ($role) {
            'admin' => "$greeting\n\nEn tant qu'administrateur, je peux vous aider avec :\n• 📊 Statistiques globales du site\n• 👥 Gestion des utilisateurs\n• 📋 Pilotage offres, candidatures, entretiens, décisions finales\n• 🔧 Navigation complète du back-office\n\n👉 Tapez **parcours admin** pour un guidage étape par étape.",
            'rh' => "$greeting\n\nEn tant que recruteur RH, je peux vous aider avec :\n• 💼 Gestion des offres\n• 📋 Suivi des candidatures\n• 🗓️ Organisation des entretiens\n• ✅ Décisions finales de recrutement\n\n👉 Tapez **parcours RH** pour un guidage de vos tâches RH.",
            'candidat' => "$greeting\n\nJe peux vous aider à :\n• 🔍 Rechercher des offres d'emploi\n• 📄 Suivre vos candidatures\n• 🗓️ Consulter vos entretiens\n• 💡 Découvrir les fonctionnalités\n\n👉 Tapez **parcours** pour un guide personnalisé.",
            default => "$greeting\n\nJe peux vous aider à :\n• 🔍 Trouver des offres d'emploi\n• 📝 Comprendre comment s'inscrire\n• 📄 Savoir comment postuler\n• 📚 Découvrir le fonctionnement de TalentFlow\n\n👉 Tapez **parcours visiteur** pour être guidé pas à pas.",
        };
    }

    private function showHelp(string $role): string
    {
        $base = "🤖 **Voici ce que je peux faire :**\n\n";

        return match ($role) {
            'admin' => $base . "• Tapez **« parcours admin »** pour un guidage global du site\n• Tapez **« statistiques »** pour voir les chiffres clés\n• Tapez **« utilisateurs »** pour la gestion des comptes\n• Tapez **« offres »** pour consulter les offres\n• Tapez **« candidatures »** pour le suivi des candidatures\n• Tapez **« entretiens »** pour les entretiens planifiés",
            'rh' => $base . "• Tapez **« parcours RH »** pour le workflow RH complet\n• Tapez **« offres »** pour gérer les offres\n• Tapez **« candidatures »** pour le suivi des dossiers\n• Tapez **« entretiens »** pour les entretiens à venir\n• Tapez **« stats »** pour des statistiques",
            'candidat' => $base . "• Tapez **« parcours »** pour un guide personnalisé\n• Tapez **« offres »** ou un domaine (ex: « développeur », « marketing »)\n• Tapez **« mes candidatures »** pour suivre vos dossiers\n• Tapez **« entretiens »** pour vos rendez-vous",
            default => $base . "• Tapez **« parcours visiteur »** pour savoir comment démarrer\n• Tapez un **domaine** (ex: « informatique », « marketing », « finance »)\n• Tapez **« offres »** pour voir les postes disponibles\n• Tapez **« comment postuler »** pour les étapes d'inscription\n• Tapez **« TalentFlow »** pour en savoir plus sur la plateforme",
        };
    }

    private function roleBasedGuidance(string $role): string
    {
        return match ($role) {
            'admin' => "🧭 **Parcours Admin TalentFlow (vue complète)** :\n\n"
                . "1. **Tableau de bord** → [**/dashboard**](/dashboard) pour le suivi global\n"
                . "2. **Utilisateurs** → [**/user**](/user) pour gérer rôles et activations\n"
                . "3. **Offres** → [**/offre**](/offre) pour superviser les publications\n"
                . "4. **Candidatures** → [**/candidature**](/candidature) pour contrôler le pipeline\n"
                . "5. **Entretiens** → [**/entretiens**](/entretiens) pour le pilotage opérationnel\n"
                . "6. **Décisions finales** → [**/decisions-finales**](/decisions-finales) pour valider les issues\n"
                . "7. **Messages / Forum** → [**/message**](/message) et [**/forum**](/forum) pour la communication\n\n"
                . "💡 Astuce : demandez **statistiques**, **utilisateurs** ou **candidatures** pour un résumé immédiat.",

            'rh' => "🧭 **Parcours RH TalentFlow (workflow recrutement)** :\n\n"
                . "1. **Créer / mettre à jour les offres** → [**/offre**](/offre)\n"
                . "2. **Suivre les candidatures** → [**/candidature**](/candidature)\n"
                . "3. **Planifier les entretiens** → [**/entretiens**](/entretiens)\n"
                . "4. **Finaliser les décisions** → [**/decisions-finales**](/decisions-finales)\n"
                . "5. **Communiquer avec candidats/équipe** → [**/message**](/message)\n\n"
                . "💡 Je peux aussi vous donner des conseils si vous tapez **entretiens** ou **candidatures**.",

            'candidat' => "🧭 **Parcours Candidat TalentFlow** :\n\n"
                . "1. Rechercher des postes sur [**/offres**](/offres)\n"
                . "2. Ouvrir une offre et postuler avec votre dossier\n"
                . "3. Suivre l'état de vos candidatures depuis votre tableau de bord\n"
                . "4. Consulter vos entretiens via votre espace personnel\n"
                . "5. Utiliser la messagerie pour répondre rapidement aux RH\n\n"
                . "💡 Tapez **mes candidatures** ou **entretiens** pour une aide ciblée.",

            default => "🧭 **Parcours Visiteur TalentFlow** :\n\n"
                . "1. Découvrir les offres sur [**/offres**](/offres)\n"
                . "2. Créer un compte sur [**/register**](/register)\n"
                . "3. Se connecter via [**/login**](/login)\n"
                . "4. Compléter votre profil candidat (CV, infos)\n"
                . "5. Postuler aux offres qui vous intéressent\n"
                . "6. Suivre vos candidatures et entretiens depuis le tableau de bord\n\n"
                . "💡 Si vous voulez, je peux aussi vous proposer des offres par domaine (ex: développeur, marketing).",
        };
    }

    private function handleOffreSearch(string $lower, string $role): string
    {
        // Extract search keywords
        $searchTerms = preg_replace('/(offre|emploi|job|poste|travail|cherche|recherche|domaine|stage|cdi|cdd|freelance|alternance|en|de|du|la|le|les|des|un|une|pour|dans|avec|je|tu|il|est|sur)\s*/u', '', $lower);
        $searchTerms = trim($searchTerms);

        // Filter by contract type
        $typeContrat = '';
        if (str_contains($lower, 'stage')) $typeContrat = 'Stage';
        elseif (str_contains($lower, 'cdi')) $typeContrat = 'CDI';
        elseif (str_contains($lower, 'cdd')) $typeContrat = 'CDD';
        elseif (str_contains($lower, 'freelance')) $typeContrat = 'Freelance';
        elseif (str_contains($lower, 'alternance')) $typeContrat = 'Alternance';

        $offres = $this->offreRepository->findPublicByFilters(
            search: $searchTerms,
            typeContrat: $typeContrat,
            limit: 5
        );

        if (empty($offres)) {
            $total = count($this->offreRepository->findActiveOffers());
            $msg = "🔍 Aucune offre trouvée pour cette recherche.";
            if ($total > 0) {
                $msg .= "\n\nMais nous avons **$total offre(s) active(s)** au total ! Essayez avec d'autres mots-clés ou consultez la page [**Offres**](/offres).";
            } else {
                $msg .= "\n\nAucune offre n'est disponible pour le moment. Revenez bientôt !";
            }
            return $msg;
        }

        $msg = "💼 **Offres trouvées** (" . count($offres) . " résultat(s)) :\n\n";
        foreach ($offres as $offre) {
            $salary = '';
            if ($offre->getSalaireMin() > 0 || $offre->getSalaireMax() > 0) {
                $salary = " | 💰 " . number_format($offre->getSalaireMin(), 0, ',', ' ') . " - " . number_format($offre->getSalaireMax(), 0, ',', ' ') . " TND";
            }
            $loc = $offre->getLocalisation() ? " 📍 " . $offre->getLocalisation() : '';
            $msg .= "• **" . $offre->getTitre() . "** — " . $offre->getTypeContrat() . $loc . $salary . "\n";
        }

        $msg .= "\n👉 Consultez toutes les offres sur la page [**Offres**](/offres).";
        if ($role === 'visitor') {
            $msg .= "\n📝 **Créez un compte** pour postuler directement !";
        }

        return $msg;
    }

    private function handleCandidatureQuery(string $lower, string $role, ?User $user): string
    {
        if ($role === 'visitor') {
            return "📄 **Postuler en tant que visiteur** :\n\n"
                . "1. Créez votre compte → [**S'inscrire**](/register)\n"
                . "2. Connectez-vous → [**Se connecter**](/login)\n"
                . "3. Explorez les annonces → [**Offres**](/offres)\n"
                . "4. Ouvrez une offre et envoyez votre candidature\n\n"
                . "💡 Tapez **parcours visiteur** pour avoir le guide complet.";
        }

        if ($role === 'admin') {
            $stats = $this->candidatureRepository->countByStatut();
            $total = 0;
            $msg = "📊 **Vue globale des candidatures :**\n\n";
            foreach ($stats as $stat) {
                $total += $stat['total'];
                $msg .= "• **" . $this->formatStatut($stat['statut']) . "** : " . $stat['total'] . "\n";
            }
            $msg = "📋 **Total des candidatures : $total**\n\n" . $msg;

            $byType = $this->candidatureRepository->countByTypeContrat();
            if (!empty($byType)) {
                $msg .= "\n📌 **Par type de contrat :**\n";
                foreach ($byType as $t) {
                    $msg .= "• " . ($t['typeContrat'] ?: 'Non spécifié') . " : " . $t['total'] . "\n";
                }
            }
            return $msg;
        }

        if ($role === 'rh') {
            $stats = $this->candidatureRepository->countByStatut();
            $total = 0;
            $msg = "📋 **Suivi des candidatures :**\n\n";
            foreach ($stats as $stat) {
                $total += $stat['total'];
                $msg .= "• **" . $this->formatStatut($stat['statut']) . "** : " . $stat['total'] . "\n";
            }
            $msg .= "\n📊 Total : **$total candidature(s)**";
            $msg .= "\n\n💡 **Conseils RH :**\n• Analysez les scores de matching pour prioriser\n• Relancez les candidatures en attente depuis plus de 7 jours\n• Vérifiez les CV joints pour les postes techniques";
            return $msg;
        }

        // Candidat
        if ($user) {
            $candidatures = $this->candidatureRepository->findFiltered(candidat: $user);
            if (empty($candidatures)) {
                return "📭 Vous n'avez pas encore de candidature. Consultez les [**offres disponibles**](/offres) pour postuler !";
            }
            $msg = "📄 **Vos candidatures** (" . count($candidatures) . ") :\n\n";
            foreach (array_slice($candidatures, 0, 5) as $c) {
                $msg .= "• **" . $c->getTitrePoste() . "** (" . $c->getEntreprise() . ") — " . $this->formatStatut($c->getStatut()) . "\n";
            }
            if (count($candidatures) > 5) {
                $msg .= "\n... et " . (count($candidatures) - 5) . " autre(s).";
            }
            $msg .= "\n\n👉 Accédez à toutes vos candidatures dans votre **tableau de bord**.";
            return $msg;
        }

        return "📄 Connectez-vous pour voir vos candidatures.";
    }

    private function handleEntretienQuery(string $lower, string $role, ?User $user): string
    {
        if ($role === 'visitor') {
            return "🗓️ **Entretiens** : Les entretiens sont planifiés une fois votre candidature retenue. Créez un compte et postulez pour commencer !";
        }

        if ($role === 'admin') {
            $today = $this->entretienRepository->countToday();
            $planifie = $this->entretienRepository->countByStatut('PLANIFIE');
            $realise = $this->entretienRepository->countByStatut('REALISE');
            $annule = $this->entretienRepository->countByStatut('ANNULE');
            $pending = $this->entretienRepository->findRealisedWithoutDecision();

            $msg = "🗓️ **Tableau de bord des entretiens :**\n\n";
            $msg .= "• 📅 Aujourd'hui : **$today** entretien(s)\n";
            $msg .= "• ⏳ Planifiés : **$planifie**\n";
            $msg .= "• ✅ Réalisés : **$realise**\n";
            $msg .= "• ❌ Annulés : **$annule**\n";
            $msg .= "• 🔔 En attente de décision : **" . count($pending) . "**\n";
            return $msg;
        }

        if ($role === 'rh') {
            $today = $this->entretienRepository->countToday();
            $planifie = $this->entretienRepository->countByStatut('PLANIFIE');
            $pending = $this->entretienRepository->findRealisedWithoutDecision();

            $msg = "🗓️ **Vos entretiens :**\n\n";
            $msg .= "• 📅 Aujourd'hui : **$today** entretien(s)\n";
            $msg .= "• ⏳ Planifiés : **$planifie**\n";
            $msg .= "• 🔔 En attente de décision : **" . count($pending) . "**\n";
            $msg .= "\n💡 **Conseils entretien :**\n• Préparez les questions techniques en amont\n• Vérifiez le lien vidéo avant l'heure\n• Prenez des notes pour la décision finale";
            return $msg;
        }

        return "🗓️ **Vos entretiens** : Consultez la section **Mon Entretien** dans votre tableau de bord pour voir vos rendez-vous planifiés.";
    }

    private function handleUserQuery(string $lower, string $role): string
    {
        if ($role !== 'admin') {
            return "👤 **Votre compte** : Vous pouvez gérer votre profil depuis le tableau de bord. Pour modifier vos informations, contactez un administrateur.";
        }

        $allUsers = $this->userRepository->findAllOrdered();
        $total = count($allUsers);
        $admins = count(array_filter($allUsers, fn($u) => in_array('ROLE_ADMIN', $u->getRoles())));
        $rhs = count(array_filter($allUsers, fn($u) => in_array('ROLE_RH', $u->getRoles())));
        $candidats = count(array_filter($allUsers, fn($u) => in_array('ROLE_CANDIDAT', $u->getRoles())));
        $actifs = count(array_filter($allUsers, fn($u) => $u->isActive()));
        $inactifs = $total - $actifs;

        $msg = "👥 **Gestion des utilisateurs :**\n\n";
        $msg .= "• 📊 Total : **$total** utilisateur(s)\n";
        $msg .= "• 👑 Administrateurs : **$admins**\n";
        $msg .= "• 👔 Recruteurs RH : **$rhs**\n";
        $msg .= "• 👤 Candidats : **$candidats**\n";
        $msg .= "• ✅ Comptes actifs : **$actifs**\n";
        $msg .= "• 🚫 Comptes désactivés : **$inactifs**\n";

        // Recent registrations
        $recent = array_slice($allUsers, 0, 3);
        if (!empty($recent)) {
            $msg .= "\n📝 **Dernières inscriptions :**\n";
            foreach ($recent as $u) {
                $msg .= "• " . $u->getFullName() . " (" . $u->getRoleLabel() . ") — " . $u->getCreatedAt()->format('d/m/Y') . "\n";
            }
        }

        return $msg;
    }

    private function handleStatsQuery(string $lower, string $role): string
    {
        if ($role === 'visitor') {
            $offresCount = count($this->offreRepository->findActiveOffers());
            return "📊 **TalentFlow en chiffres :**\n\n• 💼 **$offresCount** offre(s) d'emploi active(s)\n\n👉 Créez un compte pour postuler et accéder à plus de fonctionnalités !";
        }

        $offresCount = count($this->offreRepository->findActiveOffers());
        $candidatureStats = $this->candidatureRepository->countByStatut();
        $totalCandidatures = 0;
        foreach ($candidatureStats as $s) $totalCandidatures += $s['total'];

        $todayEntretiens = $this->entretienRepository->countToday();
        $planifieEntretiens = $this->entretienRepository->countByStatut('PLANIFIE');

        $msg = "📊 **Statistiques TalentFlow :**\n\n";
        $msg .= "• 💼 Offres actives : **$offresCount**\n";
        $msg .= "• 📄 Candidatures totales : **$totalCandidatures**\n";
        $msg .= "• 🗓️ Entretiens aujourd'hui : **$todayEntretiens**\n";
        $msg .= "• ⏳ Entretiens planifiés : **$planifieEntretiens**\n";

        if ($role === 'admin') {
            $totalUsers = count($this->userRepository->findAllOrdered());
            $msg .= "• 👥 Utilisateurs inscrits : **$totalUsers**\n";

            $pending = $this->entretienRepository->findRealisedWithoutDecision();
            $msg .= "• 🔔 Décisions en attente : **" . count($pending) . "**\n";
        }

        return $msg;
    }

    private function aboutSite(string $role): string
    {
        $msg = "🌟 **TalentFlow** est une plateforme de recrutement moderne qui connecte les **entreprises** avec les **talents**.\n\n";
        $msg .= "**Fonctionnalités principales :**\n";
        $msg .= "• 💼 Publication et recherche d'offres d'emploi\n";
        $msg .= "• 📄 Gestion complète des candidatures\n";
        $msg .= "• 🗓️ Planification d'entretiens (vidéo incluse)\n";
        $msg .= "• 💬 Messagerie privée\n";
        $msg .= "• 📊 Forum communautaire\n";
        $msg .= "• 🔒 Sécurité renforcée (2FA, protection brute-force)\n";

        if ($role === 'visitor') {
            $msg .= "\n👉 [**Voir les offres**](/offres) | [**S'inscrire**](/register) | [**Forum**](/forum)";
        }

        return $msg;
    }

    private function navigationGuide(string $role): string
    {
        return match ($role) {
            'admin' => "🧭 **Navigation Admin :**\n\n• **Tableau de bord** → /dashboard\n• **Utilisateurs** → /user\n• **Offres** → /offre\n• **Candidatures** → /candidature\n• **Entretiens** → /entretiens\n• **Décisions finales** → /decisions-finales\n• **Forum** → /forum\n• **Messages** → /message\n\nUtilisez le menu latéral pour naviguer rapidement.",
            'rh' => "🧭 **Navigation RH :**\n\n• **Tableau de bord** → /dashboard\n• **Offres** → /offre\n• **Candidatures** → /candidature\n• **Entretiens** → /entretiens\n• **Décisions finales** → /decisions-finales\n• **Messages** → /message\n• **Forum** → /forum",
            'candidat' => "🧭 **Navigation :**\n\n• **Tableau de bord** → /dashboard\n• **Offres d'emploi** → /offres\n• **Mon entretien** → /mon-entretien\n• **Messages** → /message\n• **Forum** → /forum",
            default => "🧭 **Pages accessibles :**\n\n• **Accueil** → /\n• **Offres d'emploi** → /offres\n• **Forum** → /forum\n• **Connexion** → /login\n• **Inscription** → /register",
        };
    }

    private function handleForumInfo(string $role): string
    {
        return "💬 **Forum TalentFlow** : Échangez avec la communauté, posez des questions et partagez vos expériences.\n\n👉 Accédez au forum via [**/forum**](/forum)" .
            ($role === 'visitor' ? "\n\n📝 Connectez-vous pour publier et commenter." : ".");
    }

    private function fallback(string $role): string
    {
        $msg = "🤔 Je n'ai pas bien compris votre demande.\n\n**Essayez par exemple :**\n";
        return match ($role) {
            'admin' => $msg . "• « parcours admin » — Guide complet du site\n• « statistiques » — Chiffres clés\n• « utilisateurs » — Gestion des comptes\n• « candidatures » — Suivi des dossiers\n• « entretiens » — Planning des entretiens",
            'rh' => $msg . "• « parcours RH » — Workflow RH guidé\n• « offres » — Gestion des offres\n• « candidatures » — Suivi des candidatures\n• « entretiens » — Planning des entretiens\n• « stats » — Statistiques",
            'candidat' => $msg . "• « parcours » — Guide personnalisé\n• « offres » ou un domaine (ex: « développeur »)\n• « mes candidatures » — Suivi de vos dossiers\n• « entretiens » — Vos rendez-vous",
            default => $msg . "• « parcours visiteur » — Démarrer pas à pas\n• Un domaine : « informatique », « marketing », « finance »...\n• « offres » — Offres disponibles\n• « comment postuler » — Guide d'inscription",
        };
    }

    private function formatStatut(string $statut): string
    {
        return match ($statut) {
            'soumise' => '📩 Soumise',
            'en_revision' => '🔍 En révision',
            'entretien' => '🗓️ Entretien',
            'acceptee' => '✅ Acceptée',
            'refusee' => '❌ Refusée',
            'retiree' => '↩️ Retirée',
            default => ucfirst($statut),
        };
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }

        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);

        return str_replace(
            ['statiqtiques', 'stastistiques', 'statitistiques', 'utilisateurs', 'canditatures', 'entretienss'],
            ['statistiques', 'statistiques', 'statistiques', 'utilisateurs', 'candidatures', 'entretiens'],
            $text
        );
    }

    /**
     * @param string[] $keywords
     */
    private function containsIntent(string $normalized, array $keywords): bool
    {
        if ($normalized === '') {
            return false;
        }

        $words = explode(' ', $normalized);

        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }

                if (levenshtein($word, $keyword) <= 2) {
                    return true;
                }
            }
        }

        return false;
    }
}
