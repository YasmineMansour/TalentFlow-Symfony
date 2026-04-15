<?php

namespace App\Mailer;

use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Mailer personnalisé pour l'envoi du code 2FA par email.
 * Utilise un template Twig stylisé Midnight Blue & Gold au lieu d'un simple texte brut.
 */
class TwoFactorAuthCodeMailer implements AuthCodeMailerInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        $authCode = $user->getEmailAuthCode();
        if (null === $authCode) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address('nouralouini004@gmail.com', 'TalentFlow'))
            ->to(new Address($user->getEmailAuthRecipient(), $user->getFullName()))
            ->subject('🔐 Code de vérification — TalentFlow')
            ->htmlTemplate('email/2fa_code.html.twig')
            ->context([
                'authCode' => $authCode,
            ]);

        $this->mailer->send($email);
    }
}
