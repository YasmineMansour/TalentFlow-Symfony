<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Form\EntretienType;
use App\Repository\CandidatureRepository;
use App\Repository\DecisionFinaleRepository;
use App\Repository\EntretienRepository;
use App\Service\EntretienStatusService;
use App\Service\RecruitmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/entretiens')]
#[IsGranted('ROLE_RH')]
class EntretienController extends AbstractController
{
    #[Route('/search', name: 'app_entretien_search', methods: ['GET'])]
    public function search(Request $request, EntretienRepository $entretienRepository, CandidatureRepository $candidatureRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $type = trim((string) $request->query->get('type', ''));
        $entrepriseFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        $entretiens = $entretienRepository->search($search, $statut, $type, $entrepriseFilter);
        $candidatureLabels = $candidatureRepository->findEmailsByIds(array_map(
            static fn ($entretien) => $entretien->getCandidatureId() ?? 0,
            $entretiens
        ));

        return $this->render('entretien/_table_body.html.twig', [
            'entretiens' => $entretiens,
            'candidatureLabels' => $candidatureLabels,
        ]);
    }

    private function getCandidaturesChoices(CandidatureRepository $candidatureRepository): array
    {
        $candidatures = $candidatureRepository->findBy([], ['createdAt' => 'DESC']);
        $choices = [];
        foreach ($candidatures as $c) {
            $email = $c->getEmail() ?? ($c->getCandidat()?->getEmail() ?? 'sans email');
            $label = sprintf('#%d — %s (%s) — %s', $c->getId(), $c->getTitrePoste(), $c->getEntreprise(), $email);
            $choices[$label] = $c->getId();
        }
        return $choices;
    }

    private function getCandidaturesDates(CandidatureRepository $candidatureRepository): string
    {
        $candidatures = $candidatureRepository->findBy([], ['createdAt' => 'DESC']);
        $map = [];
        foreach ($candidatures as $c) {
            if ($c->getDateEntretienSouhaitee() !== null) {
                $map[(string) $c->getId()] = $c->getDateEntretienSouhaitee()->format('Y-m-d');
            }
        }
        return json_encode($map, JSON_THROW_ON_ERROR);
    }

    #[Route('/', name: 'app_entretien_index', methods: ['GET'])]
    public function index(Request $request, EntretienRepository $entretienRepository, DecisionFinaleRepository $decisionFinaleRepository, CandidatureRepository $candidatureRepository, EntretienStatusService $entretienStatusService): Response
    {
        // Marque automatiquement les entretiens passés comme réalisés
        $entretienStatusService->markPastEntretiensAsRealised();
        $search = trim((string) $request->query->get('search', ''));
        $statut = trim((string) $request->query->get('statut', ''));
        $type = trim((string) $request->query->get('type', ''));
        $entrepriseFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        $entretiens = $entretienRepository->search($search, $statut, $type, $entrepriseFilter);
        $candidatureLabels = $candidatureRepository->findEmailsByIds(array_map(
            static fn (Entretien $entretien): int => $entretien->getCandidatureId() ?? 0,
            $entretiens
        ));

        return $this->render('entretien/index.html.twig', [
            'entretiens' => $entretiens,
            'candidatureLabels' => $candidatureLabels,
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
    public function new(Request $request, EntityManagerInterface $entityManager, EntretienRepository $entretienRepository, CandidatureRepository $candidatureRepository, RecruitmentService $recruitmentService): Response
    {
        $entretien = new Entretien();

        // Pré-remplir la candidature si passée en query param
        $candidatureId = $request->query->getInt('candidature');
        if ($candidatureId > 0) {
            $entretien->setCandidatureId($candidatureId);
        }

        $form = $this->createForm(EntretienType::class, $entretien, [
            'candidatures_choices' => $this->getCandidaturesChoices($candidatureRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeEntretien($entretien);

            if ($entretienRepository->existsConflictAtDateHeure($entretien->getDateHeure())) {
                $this->addFlash('warning', 'Un entretien existe déjà sur ce créneau.');
            } else {
                $entityManager->persist($entretien);
                $entityManager->flush();

                try {
                    $recruitmentService->sendEntretienConfirmation($entretien);
                } catch (\Throwable) {
                    // Email non bloquant
                }

                $this->addFlash('success', 'L\'entretien a été créé avec succès.');

                return $this->redirectToRoute('app_entretien_index');
            }
        }

        return $this->render('entretien/new.html.twig', [
            'form' => $form,
            'entretien' => $entretien,
            'candidatures_dates' => $this->getCandidaturesDates($candidatureRepository),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_entretien_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entretien $entretien, EntityManagerInterface $entityManager, EntretienRepository $entretienRepository, CandidatureRepository $candidatureRepository): Response
    {
        $form = $this->createForm(EntretienType::class, $entretien, [
            'candidatures_choices' => $this->getCandidaturesChoices($candidatureRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            'candidatures_dates' => $this->getCandidaturesDates($candidatureRepository),
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
            // Lien non nécessaire : URL Jitsi générée automatiquement via getMeetUrl()
            $entretien->setLien(null);
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
