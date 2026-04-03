<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\HaveIBeenPwnedService;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de Réinitialisation de Mot de Passe
 *
 * Flux complet :
 * 1. L'utilisateur demande un lien de réinitialisation (formulaire email)
 * 2. Un token cryptographique à durée limitée est généré
 * 3. Un email contenant le lien sécurisé est envoyé via Gmail SMTP
 * 4. L'utilisateur clique sur le lien et saisit son nouveau mot de passe
 * 5. Le token est consommé et le mot de passe est mis à jour
 */
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private TokenService $tokenService,
        private HaveIBeenPwnedService $haveIBeenPwnedService,
        private MailerInterface $mailer,
    ) {
    }

    /**
     * Étape 1 : Formulaire de demande de réinitialisation (saisie email).
     */
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, UserRepository $userRepository): Response
    {
        // Si déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $csrfToken = $request->request->get('_csrf_token');

            if (!$this->isCsrfTokenValid('forgot_password', $csrfToken)) {
                $this->addFlash('error', 'Jeton CSRF invalide. Veuillez réessayer.');
                return $this->redirectToRoute('app_forgot_password');
            }

            if (empty($email)) {
                $this->addFlash('error', 'Veuillez saisir votre adresse email.');
                return $this->render('security/forgot_password.html.twig');
            }

            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                // Générer un token sécurisé
                $plainToken = $this->tokenService->generateToken($user, 'password_reset', 1);

                // Construire l'URL de réinitialisation
                $resetUrl = $request->getSchemeAndHttpHost()
                    . $this->generateUrl('app_reset_password', ['token' => $plainToken]);

                // Envoyer l'email de réinitialisation via Gmail SMTP
                try {
                    $email = (new TemplatedEmail())
                        ->from(new Address('yasminemansour912@gmail.com', 'TalentFlow'))
                        ->to(new Address($user->getEmail(), $user->getFullName()))
                        ->subject('🔐 Réinitialisation de votre mot de passe — TalentFlow')
                        ->htmlTemplate('email/reset_password.html.twig')
                        ->context([
                            'user' => $user,
                            'resetUrl' => $resetUrl,
                        ]);

                    $this->mailer->send($email);
                } catch (\Exception $e) {
                    // Log silencieux — ne jamais révéler les erreurs d'envoi à l'utilisateur
                }
            }

            // Message identique dans tous les cas (sécurité : ne pas révéler l'existence du compte)
            $this->addFlash('success',
                'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé. Vérifiez votre boîte de réception (et vos spams).'
            );

            return $this->render('security/forgot_password.html.twig', ['email_sent' => true]);
        }

        return $this->render('security/forgot_password.html.twig');
    }

    /**
     * Étape 2 : Formulaire de nouveau mot de passe (via token).
     */
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Si déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Valider le token
        $resetToken = $this->tokenService->validateToken($token);

        if (!$resetToken) {
            $this->addFlash('error', 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez en demander un nouveau.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $resetToken->getUser();

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('reset_password', $csrfToken)) {
                $this->addFlash('error', 'Jeton CSRF invalide. Veuillez réessayer.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            $newPassword = $request->request->get('password', '');
            $confirmPassword = $request->request->get('password_confirm', '');

            // Validation du nouveau mot de passe
            if (empty($newPassword)) {
                $this->addFlash('error', 'Veuillez saisir un nouveau mot de passe.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            // Validation regex : 12 chars, 1 upper, 1 lower, 1 digit, 1 special
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/', $newPassword)) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 12 caractères, 1 majuscule, 1 minuscule, 1 chiffre et 1 caractère spécial.');
                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            // Vérification HaveIBeenPwned
            $hibpWarning = $this->haveIBeenPwnedService->getWarningMessage($newPassword);
            if ($hibpWarning) {
                $this->addFlash('warning', $hibpWarning);
            }

            // Hasher et sauvegarder le nouveau mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTimeImmutable());

            // Débloquer le compte si il était bloqué
            $user->setBlocked(false);

            // Consommer le token (usage unique)
            $this->tokenService->consumeToken($resetToken);

            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
