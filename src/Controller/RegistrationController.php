<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];
        $data = [
            'nom' => '',
            'prenom' => '',
            'email' => '',
            'telephone' => '',
        ];

        if ($request->isMethod('POST')) {
            $data = [
                'nom' => trim($request->request->get('nom', '')),
                'prenom' => trim($request->request->get('prenom', '')),
                'email' => trim($request->request->get('email', '')),
                'telephone' => trim($request->request->get('telephone', '')),
            ];
            $password = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');

            // CSRF
            if (!$this->isCsrfTokenValid('register', $request->request->get('_token'))) {
                $errors[] = 'Token CSRF invalide.';
            }

            // Password match
            if ($password !== $confirmPassword) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            // Password strength
            if (strlen($password) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }

            if (empty($errors)) {
                $user = new User();
                $user->setNom($data['nom']);
                $user->setPrenom($data['prenom']);
                $user->setEmail($data['email']);
                $user->setTelephone($data['telephone']);
                $user->setRoles(['ROLE_CANDIDAT']);
                $user->setPassword($passwordHasher->hashPassword($user, $password));

                $violations = $validator->validate($user);
                if (count($violations) > 0) {
                    foreach ($violations as $violation) {
                        $errors[] = $violation->getMessage();
                    }
                } else {
                    $em->persist($user);
                    $em->flush();

                    $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('public/register.html.twig', [
            'errors' => $errors,
            'data' => $data,
        ]);
    }
}
