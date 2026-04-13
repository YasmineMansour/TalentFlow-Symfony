<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\WelcomeEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

/**
 * Handler de l'email de bienvenue — exécuté de façon asynchrone par le Worker Messenger.
 *
 * La page d'inscription se charge instantanément,
 * cet handler s'occupe de l'envoi en tâche de fond.
 */
#[AsMessageHandler]
final class WelcomeEmailMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(WelcomeEmailMessage $message): void
    {
        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($message->userId);

        if (!$user instanceof User) {
            return; // L'utilisateur a peut-être été supprimé entre-temps
        }

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@talentflow.app', 'TalentFlow'))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('🎉 Bienvenue sur TalentFlow !')
            ->htmlTemplate('email/welcome.html.twig')
            ->context(['user' => $user]);

        $this->mailer->send($email);
    }
}
