<?php

namespace App\Message;

/**
 * Message Messenger pour l'envoi asynchrone de l'email de bienvenue.
 *
 * Lorsqu'un utilisateur s'inscrit, ce message est dispatché dans le bus.
 * Le worker Symfony l'intercepte et envoie l'email en tâche de fond,
 * sans bloquer la réponse HTTP.
 */
final class WelcomeEmailMessage
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
