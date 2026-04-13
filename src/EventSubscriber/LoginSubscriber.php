<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserLog;
use App\Repository\UserLogRepository;
use App\Service\LoginTrackerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Subscriber qui intercepte les événements de login Symfony Security
 * pour tracer les tentatives et appliquer la protection Brute Force.
 * Enregistre aussi l'historique de connexion (UserLog) et
 * alerte par email si une connexion provient d'une nouvelle IP inconnue.
 */
class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoginTrackerService $loginTracker,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
        private UserLogRepository $userLogRepository,
        private MailerInterface $mailer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', 2048],
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

        if ($this->loginTracker->isBlocked($email)) {
            $minutes = $this->loginTracker->getLockoutRemainingMinutes($email);
            throw new CustomUserMessageAuthenticationException(
                "Compte temporairement bloqué après trop de tentatives échouées. Réessayez dans {$minutes} minute(s)."
            );
        }

        if ($this->loginTracker->isIpBlocked($ip)) {
            throw new CustomUserMessageAuthenticationException(
                'Trop de tentatives depuis cette adresse IP. Veuillez patienter.'
            );
        }
    }

    /**
     * Enregistre une connexion réussie + crée un UserLog + alerte IP suspecte.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();
        $ip = $request->getClientIp() ?? '0.0.0.0';
        $userAgent = $request->headers->get('User-Agent');

        // 1. Enregistrement brute-force tracker
        $this->loginTracker->recordAttempt(
            email: $user->getUserIdentifier(),
            ipAddress: $ip,
            successful: true,
            userAgent: $userAgent,
        );

        if (!$user instanceof User) {
            return;
        }

        // 2. Détection nouvelle IP (alerte connexion suspecte)
        $isNewIp = !$this->userLogRepository->hasUserLoggedFromIp($user, $ip);

        // 3. Création du UserLog
        $log = new UserLog();
        $log->setUser($user);
        $log->setIpAddress($ip);
        $log->setUserAgent($userAgent ? mb_substr($userAgent, 0, 255) : null);
        $log->setBrowser(UserLog::detectBrowser($userAgent));
        $log->setSuccessful(true);
        $log->setEmail($user->getEmail());

        $this->entityManager->persist($log);

        // 4. Mise à jour last_login_at
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // 5. Alerte email si nouvelle IP détectée
        if ($isNewIp) {
            $this->sendSuspiciousLoginAlert($user, $ip, $userAgent ?? 'Inconnu');
        }
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

    private function sendSuspiciousLoginAlert(User $user, string $ip, string $userAgent): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address('nouralouini004@gmail.com', 'TalentFlow Sécurité'))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('⚠️ Nouvelle connexion détectée — TalentFlow')
                ->htmlTemplate('email/suspicious_login.html.twig')
                ->context([
                    'user' => $user,
                    'ip' => $ip,
                    'browser' => UserLog::detectBrowser($userAgent),
                    'loggedAt' => new \DateTimeImmutable(),
                ]);

            $this->mailer->send($email);
        } catch (\Throwable) {
            // Ne pas bloquer la connexion si l'email échoue
        }
    }
}
