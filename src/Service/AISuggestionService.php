<?php

namespace App\Service;

class AISuggestionService
{
    /**
     * Base de connaissances : avantages typiques par secteur / type de poste.
     */
    private const AVANTAGES_PAR_SECTEUR = [
        'informatique' => [
            ['nom' => 'Télétravail flexible', 'type' => 'Bien-être', 'description' => 'Possibilité de travailler à distance 2 à 3 jours par semaine'],
            ['nom' => 'Budget formation', 'type' => 'Matériel', 'description' => 'Budget annuel pour certifications et formations techniques'],
            ['nom' => 'Équipement premium', 'type' => 'Matériel', 'description' => 'MacBook Pro / PC performant + double écran fournis'],
            ['nom' => 'Prime de performance', 'type' => 'Financier', 'description' => 'Prime trimestrielle basée sur les objectifs atteints'],
            ['nom' => 'Abonnement coworking', 'type' => 'Matériel', 'description' => 'Accès à un espace de coworking proche du domicile'],
        ],
        'finance' => [
            ['nom' => 'Bonus annuel', 'type' => 'Financier', 'description' => 'Bonus de performance annuel pouvant atteindre 2 mois de salaire'],
            ['nom' => 'Assurance santé premium', 'type' => 'Bien-être', 'description' => 'Couverture santé complète pour le salarié et sa famille'],
            ['nom' => 'Plan d\'épargne entreprise', 'type' => 'Financier', 'description' => 'PEE avec abondement de l\'entreprise'],
            ['nom' => 'Parking réservé', 'type' => 'Matériel', 'description' => 'Place de parking gratuite dans l\'immeuble'],
        ],
        'marketing' => [
            ['nom' => 'Horaires flexibles', 'type' => 'Bien-être', 'description' => 'Aménagement libre des horaires avec plages de présence obligatoires'],
            ['nom' => 'Budget événements', 'type' => 'Matériel', 'description' => 'Budget pour participer à des conférences et salons professionnels'],
            ['nom' => 'Abonnement créatif', 'type' => 'Matériel', 'description' => 'Accès aux outils Adobe Creative Cloud et Canva Pro'],
            ['nom' => 'Team building mensuel', 'type' => 'Bien-être', 'description' => 'Activité d\'équipe organisée chaque mois'],
        ],
        'default' => [
            ['nom' => 'Ticket restaurant', 'type' => 'Financier', 'description' => 'Tickets restaurant d\'une valeur de 8 DT par jour travaillé'],
            ['nom' => 'Transport pris en charge', 'type' => 'Financier', 'description' => 'Remboursement de 50% de l\'abonnement transport en commun'],
            ['nom' => 'Mutuelle santé', 'type' => 'Bien-être', 'description' => 'Mutuelle santé prise en charge à 60% par l\'entreprise'],
            ['nom' => 'Congés supplémentaires', 'type' => 'Bien-être', 'description' => 'Jours de congés supplémentaires après 2 ans d\'ancienneté'],
            ['nom' => 'Prime d\'ancienneté', 'type' => 'Financier', 'description' => 'Revalorisation salariale annuelle selon l\'ancienneté'],
        ],
    ];

    private const AVANTAGES_PAR_CONTRAT = [
        'CDI' => [
            ['nom' => 'Mutuelle entreprise', 'type' => 'Bien-être', 'description' => 'Couverture santé complète dès l\'embauche'],
            ['nom' => 'Intéressement', 'type' => 'Financier', 'description' => 'Participation aux bénéfices de l\'entreprise'],
        ],
        'Stage' => [
            ['nom' => 'Gratification compétitive', 'type' => 'Financier', 'description' => 'Gratification supérieure au minimum légal'],
            ['nom' => 'Mentorat dédié', 'type' => 'Bien-être', 'description' => 'Accompagnement par un mentor expérimenté'],
            ['nom' => 'Possibilité d\'embauche', 'type' => 'Bien-être', 'description' => 'Possibilité de CDI à l\'issue du stage'],
        ],
        'Alternance' => [
            ['nom' => 'Prise en charge formation', 'type' => 'Financier', 'description' => 'Frais de scolarité intégralement pris en charge'],
            ['nom' => 'Tuteur entreprise', 'type' => 'Bien-être', 'description' => 'Accompagnement personnalisé par un tuteur dédié'],
        ],
        'Freelance' => [
            ['nom' => 'TJM compétitif', 'type' => 'Financier', 'description' => 'Taux journalier attractif selon l\'expérience'],
            ['nom' => 'Flexibilité totale', 'type' => 'Bien-être', 'description' => 'Liberté totale dans l\'organisation du travail'],
        ],
    ];

    private const AVANTAGES_PAR_MODE = [
        'REMOTE' => [
            ['nom' => 'Indemnité télétravail', 'type' => 'Financier', 'description' => 'Allocation mensuelle pour couvrir les frais de télétravail (internet, électricité)'],
            ['nom' => 'Équipement bureau à domicile', 'type' => 'Matériel', 'description' => 'Bureau ergonomique et chaise de qualité fournis pour le domicile'],
        ],
        'HYBRID' => [
            ['nom' => 'Jours de télétravail au choix', 'type' => 'Bien-être', 'description' => 'Choix libre des jours de présence au bureau'],
        ],
    ];

    /**
     * Suggérer des avantages pour une offre selon son contexte.
     *
     * @return array<array{nom: string, type: string, description: string, raison: string}>
     */
    public function suggestAvantages(
        string $titre,
        ?string $typeContrat = null,
        ?string $modeTravail = null,
        ?string $categorie = null,
        ?float $salaireMax = null,
        array $avantagesExistants = []
    ): array {
        $suggestions = [];
        $existingNames = array_map('mb_strtolower', $avantagesExistants);

        // 1. Suggestions par catégorie/secteur
        $secteur = $this->detectSecteur($titre, $categorie);
        $secteurAvantages = self::AVANTAGES_PAR_SECTEUR[$secteur] ?? self::AVANTAGES_PAR_SECTEUR['default'];
        foreach ($secteurAvantages as $av) {
            $av['raison'] = "Recommandé pour le secteur « $secteur »";
            $suggestions[] = $av;
        }

        // 2. Suggestions par type de contrat
        if ($typeContrat && isset(self::AVANTAGES_PAR_CONTRAT[$typeContrat])) {
            foreach (self::AVANTAGES_PAR_CONTRAT[$typeContrat] as $av) {
                $av['raison'] = "Adapté au contrat $typeContrat";
                $suggestions[] = $av;
            }
        }

        // 3. Suggestions par mode de travail
        if ($modeTravail && isset(self::AVANTAGES_PAR_MODE[$modeTravail])) {
            foreach (self::AVANTAGES_PAR_MODE[$modeTravail] as $av) {
                $av['raison'] = "Pertinent pour le mode $modeTravail";
                $suggestions[] = $av;
            }
        }

        // 4. Suggestions salariales
        if ($salaireMax !== null) {
            if ($salaireMax >= 4000) {
                $suggestions[] = [
                    'nom' => 'Voiture de fonction',
                    'type' => 'Matériel',
                    'description' => 'Véhicule de fonction ou budget mobilité mensuel',
                    'raison' => 'Avantage courant pour les postes à haute rémunération',
                ];
            }
            if ($salaireMax < 2000) {
                $suggestions[] = [
                    'nom' => 'Aide au transport',
                    'type' => 'Financier',
                    'description' => 'Prise en charge intégrale des frais de transport',
                    'raison' => 'Avantage apprécié pour compenser un salaire d\'entrée',
                ];
            }
        }

        // Ajouter les génériques si peu de suggestions
        if (count($suggestions) < 4) {
            foreach (self::AVANTAGES_PAR_SECTEUR['default'] as $av) {
                $av['raison'] = 'Avantage standard recommandé';
                $suggestions[] = $av;
            }
        }

        // Dédupliquer et exclure les existants
        $seen = [];
        $filtered = [];
        foreach ($suggestions as $s) {
            $key = mb_strtolower($s['nom']);
            if (!in_array($key, $seen) && !in_array($key, $existingNames)) {
                $seen[] = $key;
                $filtered[] = $s;
            }
        }

        return array_slice($filtered, 0, 8);
    }

    /**
     * Améliorer la description d'une offre.
     */
    public function improveOffreDescription(
        string $titre,
        ?string $description = null,
        ?string $typeContrat = null,
        ?string $localisation = null,
        ?string $modeTravail = null
    ): string {
        $parts = [];

        // Introduction
        $parts[] = "🚀 Rejoignez notre équipe en tant que $titre !";
        $parts[] = '';

        // Description existante enrichie
        if ($description && strlen($description) > 10) {
            $parts[] = '📋 **À propos du poste :**';
            $parts[] = $description;
            $parts[] = '';
        }

        // Profil recherché généré
        $parts[] = '🎯 **Profil recherché :**';
        $competences = $this->generateCompetences($titre);
        foreach ($competences as $c) {
            $parts[] = "• $c";
        }
        $parts[] = '';

        // Infos contrat
        $parts[] = '📌 **Détails du poste :**';
        if ($typeContrat) {
            $parts[] = "• Type de contrat : $typeContrat";
        }
        if ($localisation) {
            $parts[] = "• Localisation : $localisation";
        }
        if ($modeTravail) {
            $modeLabels = ['ON_SITE' => 'Sur site', 'REMOTE' => 'Télétravail', 'HYBRID' => 'Hybride'];
            $parts[] = '• Mode de travail : ' . ($modeLabels[$modeTravail] ?? $modeTravail);
        }
        $parts[] = '';

        // Appel à l'action
        $parts[] = '✉️ Postulez dès maintenant et donnez un nouvel élan à votre carrière !';

        return implode("\n", $parts);
    }

    /**
     * Suggérer un nom d'avantage à partir de la description et du type.
     */
    public function suggestAvantageNom(?string $description = null, ?string $type = null): string
    {
        $suggestions = [
            'Financier' => [
                'Prime de performance',
                'Bonus annuel',
                'Ticket restaurant',
                'Prime de transport',
                'Indemnité télétravail',
                'Prime d\'ancienneté',
                'Participation aux bénéfices',
                'Chèques cadeaux',
            ],
            'Bien-être' => [
                'Mutuelle santé premium',
                'Abonnement salle de sport',
                'Congés supplémentaires',
                'Horaires flexibles',
                'Télétravail flexible',
                'Programme bien-être',
                'Séances de coaching',
                'Journée bien-être mensuelle',
            ],
            'Matériel' => [
                'Ordinateur portable',
                'Équipement ergonomique',
                'Téléphone professionnel',
                'Voiture de fonction',
                'Bureau ajustable',
                'Double écran',
                'Kit télétravail',
            ],
            'AUTRE' => [
                'Formation continue',
                'Événements d\'équipe',
                'Mentorat personnalisé',
                'Accès parking gratuit',
                'Réductions partenaires',
                'Programme de parrainage',
            ],
        ];

        // Si on a une description, essayer de détecter un mot-clé pour affiner
        if ($description) {
            $descLower = mb_strtolower($description);
            $keywordMap = [
                'transport' => 'Prime de transport',
                'restaurant' => 'Ticket restaurant',
                'repas' => 'Ticket restaurant',
                'santé' => 'Mutuelle santé premium',
                'mutuelle' => 'Mutuelle santé premium',
                'sport' => 'Abonnement salle de sport',
                'gym' => 'Abonnement salle de sport',
                'télétravail' => 'Indemnité télétravail',
                'remote' => 'Télétravail flexible',
                'formation' => 'Formation continue',
                'prime' => 'Prime de performance',
                'bonus' => 'Bonus annuel',
                'voiture' => 'Voiture de fonction',
                'parking' => 'Accès parking gratuit',
                'ordinateur' => 'Ordinateur portable',
                'laptop' => 'Ordinateur portable',
                'congé' => 'Congés supplémentaires',
                'vacances' => 'Congés supplémentaires',
                'horaire' => 'Horaires flexibles',
                'flexible' => 'Horaires flexibles',
            ];

            foreach ($keywordMap as $keyword => $nom) {
                if (str_contains($descLower, $keyword)) {
                    return $nom;
                }
            }
        }

        // Sinon, choisir aléatoirement selon le type
        $pool = $suggestions[$type] ?? array_merge(...array_values($suggestions));

        return $pool[array_rand($pool)];
    }

    /**
     * Améliorer la description d'un avantage.
     */
    public function improveAvantageDescription(string $nom, ?string $description = null, ?string $type = null): string
    {
        $improved = '';

        // Phrase d'accroche
        $typeEmojis = [
            'Financier' => '💰',
            'Bien-être' => '🌟',
            'Matériel' => '🛠️',
            'AUTRE' => '✨',
        ];
        $emoji = $typeEmojis[$type] ?? '✨';

        $improved .= "$emoji **$nom**\n\n";

        if ($description && strlen($description) > 5) {
            $improved .= "$description\n\n";
        }

        // Ajouter des détails selon le type
        $details = $this->getAvantageDetails($nom, $type);
        if ($details) {
            $improved .= "📌 Détails :\n";
            foreach ($details as $d) {
                $improved .= "• $d\n";
            }
        }

        return trim($improved);
    }

    /**
     * Détecter le secteur à partir du titre et de la catégorie.
     */
    private function detectSecteur(string $titre, ?string $categorie): string
    {
        $text = mb_strtolower($titre . ' ' . ($categorie ?? ''));

        $keywords = [
            'informatique' => ['développeur', 'developer', 'dev ', 'php', 'java', 'python', 'react', 'angular', 'devops', 'fullstack', 'frontend', 'backend', 'data', 'cloud', 'sysadmin', 'informatique', 'web', 'mobile', 'logiciel', 'software', 'it ', 'tech'],
            'finance' => ['comptable', 'financier', 'finance', 'audit', 'banque', 'trésorier', 'contrôleur de gestion', 'analyste financier'],
            'marketing' => ['marketing', 'community manager', 'communication', 'brand', 'seo', 'sem', 'content', 'digital', 'social media', 'publicité'],
        ];

        foreach ($keywords as $secteur => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($text, $mot)) {
                    return $secteur;
                }
            }
        }

        return 'default';
    }

    /**
     * Générer des compétences attendues selon le titre du poste.
     */
    private function generateCompetences(string $titre): array
    {
        $text = mb_strtolower($titre);
        $competences = [];

        if (str_contains($text, 'php') || str_contains($text, 'symfony')) {
            $competences = ['Maîtrise de PHP 8+ et Symfony', 'Expérience avec les bases de données MySQL/PostgreSQL', 'Connaissance de Git et des méthodologies Agile', 'Capacité à écrire des tests unitaires et fonctionnels'];
        } elseif (str_contains($text, 'java')) {
            $competences = ['Maîtrise de Java et Spring Boot', 'Expérience avec les architectures microservices', 'Connaissance de Maven/Gradle', 'Bonnes pratiques de clean code'];
        } elseif (str_contains($text, 'react') || str_contains($text, 'angular') || str_contains($text, 'frontend') || str_contains($text, 'front')) {
            $competences = ['Maîtrise de JavaScript/TypeScript', 'Expérience avec un framework moderne (React, Angular, Vue)', 'Sensibilité UI/UX et responsive design', 'Connaissance des outils de build (Webpack, Vite)'];
        } elseif (str_contains($text, 'python') || str_contains($text, 'data')) {
            $competences = ['Maîtrise de Python et ses librairies (Pandas, NumPy)', 'Expérience en analyse de données ou ML', 'Connaissance de SQL et des bases NoSQL', 'Capacité à communiquer les résultats d\'analyses'];
        } elseif (str_contains($text, 'devops') || str_contains($text, 'cloud')) {
            $competences = ['Maîtrise de Docker et Kubernetes', 'Expérience avec CI/CD (GitLab CI, Jenkins)', 'Connaissance des services cloud (AWS, Azure, GCP)', 'Automatisation avec Terraform ou Ansible'];
        } elseif (str_contains($text, 'marketing') || str_contains($text, 'community')) {
            $competences = ['Excellente maîtrise des réseaux sociaux', 'Compétences en création de contenu et copywriting', 'Analyse de métriques et reporting', 'Créativité et sens de l\'innovation'];
        } else {
            $competences = ['Minimum 2 ans d\'expérience dans un poste similaire', 'Excellentes compétences en communication', 'Esprit d\'équipe et autonomie', 'Maîtrise du français et de l\'anglais'];
        }

        return $competences;
    }

    /**
     * Détails enrichis pour un avantage.
     */
    private function getAvantageDetails(string $nom, ?string $type): array
    {
        $nomLower = mb_strtolower($nom);

        if (str_contains($nomLower, 'ticket') || str_contains($nomLower, 'restaurant')) {
            return ['Valeur faciale de 8 à 10 DT par jour', 'Utilisable dans les restaurants partenaires', 'Pris en charge à 60% par l\'employeur'];
        }
        if (str_contains($nomLower, 'transport')) {
            return ['Remboursement mensuel sur justificatif', 'Couvre les transports en commun et covoiturage', 'Versé avec le salaire'];
        }
        if (str_contains($nomLower, 'mutuelle') || str_contains($nomLower, 'santé')) {
            return ['Couverture hospitalisation et soins courants', 'Extension possible au conjoint et enfants', 'Prise en charge à 50-70% par l\'entreprise'];
        }
        if (str_contains($nomLower, 'formation')) {
            return ['Budget annuel individuel dédié', 'Accès à des plateformes en ligne (Udemy, Pluralsight)', 'Temps de formation sur les heures de travail'];
        }
        if (str_contains($nomLower, 'télétravail') || str_contains($nomLower, 'remote')) {
            return ['Jours de télétravail au choix du salarié', 'Indemnité couvrant les frais domestiques', 'Équipement ergonomique fourni'];
        }
        if (str_contains($nomLower, 'prime')) {
            return ['Calculée sur des objectifs clairs et mesurables', 'Versée trimestriellement ou annuellement', 'Transparence totale sur les critères'];
        }

        if ($type === 'Financier') {
            return ['Versé selon les modalités convenues', 'Revalorisé annuellement'];
        }
        if ($type === 'Bien-être') {
            return ['Contribue à un meilleur équilibre vie pro/perso', 'Accessible dès le premier jour'];
        }

        return [];
    }
}
