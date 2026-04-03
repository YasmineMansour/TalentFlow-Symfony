<?php

namespace App\Service;

/**
 * Service d'intégration Have I Been Pwned (HIBP)
 * 
 * Vérifie en temps réel si un mot de passe a déjà fuité dans une faille de données
 * publique, en utilisant l'API k-anonymity de haveibeenpwned.com.
 * 
 * Fonctionnement (k-anonymity — préserve la vie privée) :
 * 1. On hash le mot de passe en SHA-1
 * 2. On envoie UNIQUEMENT les 5 premiers caractères du hash à l'API
 * 3. L'API retourne tous les hash qui commencent par ces 5 caractères
 * 4. On compare localement si notre hash complet est dans la liste
 * 
 * → Le mot de passe en clair n'est JAMAIS envoyé sur le réseau.
 */
class HaveIBeenPwnedService
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';

    /**
     * Vérifie si un mot de passe a été compromis dans une fuite de données.
     * 
     * @return int Le nombre de fois que ce mot de passe a fuité (0 = jamais)
     */
    public function checkPassword(string $password): int
    {
        $sha1 = strtoupper(sha1($password));
        $prefix = substr($sha1, 0, 5);
        $suffix = substr($sha1, 5);

        try {
            $response = $this->callApi($prefix);

            if ($response === null) {
                // En cas d'erreur API, on ne bloque pas l'utilisateur
                return 0;
            }

            // Parcourir les résultats pour trouver notre suffix
            $lines = explode("\n", $response);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                [$hashSuffix, $count] = explode(':', $line, 2);

                if (strtoupper($hashSuffix) === $suffix) {
                    return (int) $count;
                }
            }
        } catch (\Exception $e) {
            // En cas d'erreur, ne pas bloquer l'utilisateur
            return 0;
        }

        return 0;
    }

    /**
     * Vérifie si un mot de passe est compromis (retourne true/false).
     */
    public function isPasswordCompromised(string $password): bool
    {
        return $this->checkPassword($password) > 0;
    }

    /**
     * Retourne un message d'avertissement formaté si le mot de passe est compromis.
     */
    public function getWarningMessage(string $password): ?string
    {
        $count = $this->checkPassword($password);

        if ($count === 0) {
            return null;
        }

        return sprintf(
            '⚠️ Ce mot de passe a été trouvé %s fois dans des fuites de données publiques. '
            . 'Il est fortement recommandé d\'en choisir un autre.',
            number_format($count, 0, ',', ' ')
        );
    }

    /**
     * Appel à l'API Have I Been Pwned via cURL.
     */
    private function callApi(string $prefix): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::API_URL . $prefix,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER => [
                'User-Agent: TalentFlow-Symfony-Security',
                'Add-Padding: true',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return $response;
    }
}
