<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Form\EntretienType;
use App\Repository\CandidatureRepository;
use App\Repository\DecisionFinaleRepository;
use App\Repository\EntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/entretiens')]
#[IsGranted('ROLE_RH')]
class EntretienController extends AbstractController
{
    #[Route('/', name: 'app_entretien_index', methods: ['GET'])]
    public function index(Request $request, EntretienRepository $entretienRepository, DecisionFinaleRepository $decisionFinaleRepository, CandidatureRepository $candidatureRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $type = trim((string) $request->query->get('type', ''));
        $entretiens = $entretienRepository->search($search, $statut, $type);
        $candidatureEmails = $candidatureRepository->findEmailsByIds(array_map(
            static fn (Entretien $entretien): int => $entretien->getCandidatureId() ?? 0,
            $entretiens
        ));

        return $this->render('entretien/index.html.twig', [
            'entretiens' => $entretiens,
            'candidatureEmails' => $candidatureEmails,
            'search' => $search,
            'statut' => $statut,
            'type' => $type,
            'todayCount' => $entretienRepository->countToday(),
            'planifiesCount' => $entretienRepository->countByStatut('PLANIFIE'),
            'realisesCount' => $entretienRepository->countByStatut('REALISE'),
            'pendingDecisions' => $decisionFinaleRepository->countPending(),
        ]);
    }

    #[Route('/new', name: 'app_entretien_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EntretienRepository $entretienRepository, CandidatureRepository $candidatureRepository): Response
    {
        $entretien = new Entretien();
        $form = $this->createForm(EntretienType::class, $entretien);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$candidatureRepository->isAvailable()) {
                $form->get('candidateEmail')->addError(new FormError('La source des candidatures n\'est pas encore disponible dans la base.'));

                return $this->render('entretien/new.html.twig', [
                    'form' => $form,
                    'entretien' => $entretien,
                ]);
            }

            $candidateEmail = (string) $form->get('candidateEmail')->getData();
            $candidatureId = $candidatureRepository->findIdByEmail($candidateEmail);

            if ($candidatureId === null) {
                $form->get('candidateEmail')->addError(new FormError('Aucune candidature trouvée pour cet email.'));

                return $this->render('entretien/new.html.twig', [
                    'form' => $form,
                    'entretien' => $entretien,
                ]);
            }

            $entretien->setCandidatureId($candidatureId);

            if ($entretien->getCandidatureId() === null || $entretien->getCandidatureId() <= 0) {
                $form->get('candidateEmail')->addError(new FormError('La candidature liée à cet email est invalide.'));

                return $this->render('entretien/new.html.twig', [
                    'form' => $form,
                    'entretien' => $entretien,
                ]);
            }

            $this->normalizeEntretien($entretien);

            if ($entretienRepository->existsConflictAtDateHeure($entretien->getDateHeure())) {
                $this->addFlash('warning', 'Un entretien existe déjà sur ce créneau.');
            } else {
                $entityManager->persist($entretien);
                $entityManager->flush();
                $this->addFlash('success', 'L\'entretien a été créé avec succès.');

                return $this->redirectToRoute('app_entretien_index');
            }
        }

        return $this->render('entretien/new.html.twig', [
            'form' => $form,
            'entretien' => $entretien,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_entretien_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entretien $entretien, EntityManagerInterface $entityManager, EntretienRepository $entretienRepository, CandidatureRepository $candidatureRepository): Response
    {
        $form = $this->createForm(EntretienType::class, $entretien, [
            'candidate_email' => $candidatureRepository->findEmailById($entretien->getCandidatureId() ?? 0) ?? '',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$candidatureRepository->isAvailable()) {
                $form->get('candidateEmail')->addError(new FormError('La source des candidatures n\'est pas encore disponible dans la base.'));

                return $this->render('entretien/edit.html.twig', [
                    'form' => $form,
                    'entretien' => $entretien,
                ]);
            }

            $candidateEmail = (string) $form->get('candidateEmail')->getData();
            $candidatureId = $candidatureRepository->findIdByEmail($candidateEmail);

            if ($candidatureId === null) {
                $form->get('candidateEmail')->addError(new FormError('Aucune candidature trouvée pour cet email.'));

                return $this->render('entretien/edit.html.twig', [
                    'form' => $form,
                    'entretien' => $entretien,
                ]);
            }

            $entretien->setCandidatureId($candidatureId);

            if ($entretien->getCandidatureId() === null || $entretien->getCandidatureId() <= 0) {
                $form->get('candidateEmail')->addError(new FormError('La candidature liée à cet email est invalide.'));

                return $this->render('entretien/edit.html.twig', [
                    'form' => $form,
                    'entretien' => $entretien,
                ]);
            }

            $this->normalizeEntretien($entretien);

            if ($entretienRepository->existsConflictAtDateHeure($entretien->getDateHeure(), $entretien->getId())) {
                $this->addFlash('warning', 'Un autre entretien existe déjà sur ce créneau.');
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'L\'entretien a été modifié avec succès.');

                return $this->redirectToRoute('app_entretien_index');
            }
        }

        return $this->render('entretien/edit.html.twig', [
            'form' => $form,
            'entretien' => $entretien,
        ]);
    }

    #[Route('/{id}', name: 'app_entretien_delete', methods: ['POST'])]
    public function delete(Request $request, Entretien $entretien, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_entretien_' . $entretien->getId(), $request->request->get('_token'))) {
            $entityManager->remove($entretien);
            $entityManager->flush();
            $this->addFlash('success', 'L\'entretien a été supprimé.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_entretien_index');
    }

    private function normalizeEntretien(Entretien $entretien): void
    {
        $now = new \DateTime();
        $dateHeure = $entretien->getDateHeure();

        if ($entretien->getType() === 'EN_LIGNE') {
            $entretien->setLieu(null);
        }

        if ($entretien->getType() === 'PRESENTIEL') {
            $entretien->setLien(null);
        }

        if ($entretien->getType() === 'TELEPHONIQUE') {
            $entretien->setLieu(null);
            $entretien->setLien(null);
        }

        if ($dateHeure !== null && $dateHeure < $now && $entretien->getStatut() !== 'ANNULE') {
            $entretien->setStatut('REALISE');
        }

        if ($entretien->getCreatedAt() === null) {
            $entretien->setCreatedAt($now);
        }

        $entretien->setUpdatedAt($now);
    }
}