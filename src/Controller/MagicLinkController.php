<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Magic Link — Connexion sans mot de passe.
 *
 * Flux :
 *   1. GET  /magic-link         → affiche le formulaire de saisie d'email
 *   2. POST /magic-link         → génère un jeton et envoie le lien par email
 *   3. GET  /magic-link/verify/{token} → vérifie le jeton et connecte l'utilisateur
 */
#[Route('/magic-link')]
class MagicLinkController extends AbstractController
{
    #[Route('', name: 'app_magic_link', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UserRepository $userRepository,
        TokenService $tokenService,
        MailerInterface $mailer,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = null;
        $sent  = false;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('magic_link', $request->request->get('_token'))) {
                $error = 'Token de sécurité invalide.';
            } else {
                $email = trim((string) $request->request->get('email', ''));
                $user  = $userRepository->findOneBy(['email' => $email]);

                // Toujours afficher "email envoyé" même si l'utilisateur n'existe pas
                // (pour ne pas révéler quels emails sont enregistrés — sécurité)
                if ($user instanceof User && !$user->isBlocked()) {
                    // Magic link valid for 1 hour (TokenService expects int hours)
                    $plainToken = $tokenService->generateToken($user, 'magic_link', 1);
                    $this->sendMagicLinkEmail($mailer, $user, $plainToken, $request);
                }

                $sent = true;
            }
        }

        return $this->render('security/magic_link.html.twig', [
            'error' => $error,
            'sent'  => $sent,
        ]);
    }

    #[Route('/verify/{token}', name: 'app_magic_link_verify', methods: ['GET'])]
    public function verify(
        string $token,
        TokenService $tokenService,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        Request $request,
    ): Response {
        $resetToken = $tokenService->validateToken($token);

        if ($resetToken === null || $resetToken->getType() !== 'magic_link') {
            $this->addFlash('error', 'Lien invalide ou expiré. Demandez un nouveau lien.');
            return $this->redirectToRoute('app_magic_link');
        }

        $user = $resetToken->getUser();

        if (!$user instanceof User || $user->isBlocked()) {
            $this->addFlash('error', 'Ce compte est inaccessible.');
            return $this->redirectToRoute('app_magic_link');
        }

        // Consommer le token (usage unique)
        $tokenService->consumeToken($resetToken);

        // Mettre à jour last_login_at
        $user->setLastLoginAt(new \DateTimeImmutable());
        $em->flush();

        // Connecter l'utilisateur manuellement
        $authToken = new PostAuthenticationToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($authToken);
        $request->getSession()->set('_security_main', serialize($authToken));

        $this->addFlash('success', sprintf('Bienvenue, %s ! Connexion sans mot de passe réussie.', $user->getPrenom()));

        return $this->redirectToRoute('app_dashboard');
    }

    private function sendMagicLinkEmail(MailerInterface $mailer, User $user, string $token, Request $request): void
    {
        $magicUrl = $this->generateUrl(
            'app_magic_link_verify',
            ['token' => $token],
            \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(new Address('nouralouini004@gmail.com', 'TalentFlow'))
            ->to(new Address($user->getEmail(), $user->getFullName()))
            ->subject('🔗 Votre lien de connexion — TalentFlow')
            ->htmlTemplate('email/magic_link.html.twig')
            ->context([
                'user'     => $user,
                'magicUrl' => $magicUrl,
                'expiresIn' => '15 minutes',
            ]);

        $mailer->send($email);
    }
}
