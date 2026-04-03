<?php

namespace App\Controller;

use App\Service\LoginTrackerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private LoginTrackerService $loginTracker,
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // If already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Remaining attempts info for brute force display
        $remainingAttempts = null;
        $lockoutMinutes = null;
        if ($lastUsername) {
            if ($this->loginTracker->isBlocked($lastUsername)) {
                $lockoutMinutes = $this->loginTracker->getLockoutRemainingMinutes($lastUsername);
            } else {
                $remaining = $this->loginTracker->getRemainingAttempts($lastUsername);
                if ($remaining < 5) {
                    $remainingAttempts = $remaining;
                }
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'remaining_attempts' => $remainingAttempts,
            'lockout_minutes' => $lockoutMinutes,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
