<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\HaveIBeenPwnedService;
use App\Service\RoleHierarchyManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user')]
class UserController extends AbstractController
{
    public function __construct(
        private RoleHierarchyManager $roleHierarchyManager,
        private HaveIBeenPwnedService $haveIBeenPwnedService,
    ) {
    }
    /**
     * LIST - Affichage de la liste des utilisateurs avec recherche et filtre par rôle.
     */
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = $request->query->get('search', '');
        $role = $request->query->get('role', '');

        if (!empty($search)) {
            $users = $userRepository->searchByName($search);
        } elseif (!empty($role)) {
            $users = $userRepository->findByRole($role);
        } else {
            $users = $userRepository->findAllOrdered();
        }

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
        ]);
    }

    /**
     * CREATE - Création d'un nouvel utilisateur avec hashing du mot de passe
     * et vérification d'unicité de l'email.
     */
    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'available_roles' => $this->roleHierarchyManager->getAvailableRolesForUser($currentUser),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification d'unicité de l'email en base
            $existingUser = $userRepository->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'L\'email "' . $user->getEmail() . '" est déjà utilisé par un autre compte.');
                return $this->render('user/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Vérification de permission sur le rôle assigné
            $assignedRoles = $user->getRoles();
            foreach ($assignedRoles as $role) {
                if ($role !== 'ROLE_USER' && !$this->roleHierarchyManager->canCreateWithRole($currentUser, $role)) {
                    $this->addFlash('error', 'Vous n\'avez pas la permission de créer un utilisateur avec le rôle ' . $role . '.');
                    return $this->render('user/new.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            // Hash du mot de passe via UserPasswordHasherInterface
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                // Vérification HaveIBeenPwned — mot de passe compromis ?
                $hibpWarning = $this->haveIBeenPwnedService->getWarningMessage($plainPassword);
                if ($hibpWarning) {
                    $this->addFlash('warning', $hibpWarning);
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Gestion automatique des rôles : garantir au moins un rôle
            if (empty($user->getRoles()) || $user->getRoles() === ['ROLE_USER']) {
                $user->setRoles(['ROLE_CANDIDAT']);
            }

            try {
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'L\'utilisateur "' . $user->getFullName() . '" a été créé avec succès.');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez vérifier les champs.');
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * SHOW - Affichage des détails d'un utilisateur.
     */
    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * EDIT - Modification d'un utilisateur avec vérification d'email unique
     * et hashing conditionnel du mot de passe.
     */
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Vérification de permission via RoleHierarchyManager
        if (!$this->roleHierarchyManager->canEdit($currentUser, $user)) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de modifier cet utilisateur.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        $originalEmail = $user->getEmail();
        $canChangeRole = $this->roleHierarchyManager->canChangeRole($currentUser, $user);

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'available_roles' => $canChangeRole
                ? $this->roleHierarchyManager->getAvailableRolesForUser($currentUser)
                : null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérification d'unicité si l'email a changé
            $newEmail = $user->getEmail();
            if ($newEmail !== $originalEmail) {
                $existingUser = $userRepository->findOneBy(['email' => $newEmail]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'L\'email "' . $newEmail . '" est déjà utilisé par un autre compte.');
                    $user->setEmail($originalEmail);
                    return $this->render('user/edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            // Hash du nouveau mot de passe uniquement si fourni
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                // Vérification HaveIBeenPwned — mot de passe compromis ?
                $hibpWarning = $this->haveIBeenPwnedService->getWarningMessage($plainPassword);
                if ($hibpWarning) {
                    $this->addFlash('warning', $hibpWarning);
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Gestion automatique des rôles
            if (empty($user->getRoles()) || $user->getRoles() === ['ROLE_USER']) {
                $user->setRoles(['ROLE_CANDIDAT']);
            }

            $user->setUpdatedAt(new \DateTimeImmutable());

            try {
                $entityManager->flush();

                $this->addFlash('success', 'L\'utilisateur "' . $user->getFullName() . '" a été modifié avec succès.');
                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs. Veuillez vérifier les champs.');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * DELETE - Suppression d'un utilisateur avec protection CSRF
     * et empêchement de l'auto-suppression.
     */
    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Vérification de permission via RoleHierarchyManager
        if (!$this->roleHierarchyManager->canDelete($currentUser, $user)) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de supprimer cet utilisateur.');
            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            try {
                $fullName = $user->getFullName();
                $entityManager->remove($user);
                $entityManager->flush();

                $this->addFlash('success', 'L\'utilisateur "' . $fullName . '" a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide. La suppression a été annulée.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
