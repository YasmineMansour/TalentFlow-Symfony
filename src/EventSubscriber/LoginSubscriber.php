<?php

namespace App\EventSubscriber;

use App\Service\LoginTrackerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Subscriber qui intercepte les événements de login Symfony Security
 * pour tracer les tentatives et appliquer la protection Brute Force.
 */
class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoginTrackerService $loginTracker,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', 2048], // Priorité haute : vérifie le blocage AVANT l'auth
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    /**
     * Vérifie si le compte ou l'IP est bloqué AVANT de tenter l'authentification.
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $passport = $event->getPassport();
        $email = $passport->getUser()->getUserIdentifier();
        $ip = $request->getClientIp() ?? '0.0.0.0';

        // Vérification du blocage par email
        if ($this->loginTracker->isBlocked($email)) {
            $minutes = $this->loginTracker->getLockoutRemainingMinutes($email);
            throw new CustomUserMessageAuthenticationException(
                "Compte temporairement bloqué après trop de tentatives échouées. Réessayez dans {$minutes} minute(s)."
            );
        }

        // Vérification du blocage par IP
        if ($this->loginTracker->isIpBlocked($ip)) {
            throw new CustomUserMessageAuthenticationException(
                'Trop de tentatives depuis cette adresse IP. Veuillez patienter.'
            );
        }
    }

    /**
     * Enregistre une connexion réussie.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();

        $this->loginTracker->recordAttempt(
            email: $user->getUserIdentifier(),
            ipAddress: $request->getClientIp() ?? '0.0.0.0',
            successful: true,
            userAgent: $request->headers->get('User-Agent'),
        );
    }

    /**
     * Enregistre une tentative de connexion échouée.
     */
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = $request->request->get('_username', 'unknown');
        $exception = $event->getException();

        $this->loginTracker->recordAttempt(
            email: $email,
            ipAddress: $request->getClientIp() ?? '0.0.0.0',
            successful: false,
            userAgent: $request->headers->get('User-Agent'),
            failureReason: mb_substr($exception->getMessage(), 0, 50),
        );
    }
}
