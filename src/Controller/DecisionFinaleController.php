<?php

namespace App\Controller;

use App\Entity\DecisionFinale;
use App\Form\DecisionFinaleType;
use App\Repository\CandidatureRepository;
use App\Repository\DecisionFinaleRepository;
use App\Repository\EntretienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/decisions-finales')]
#[IsGranted('ROLE_RH')]
class DecisionFinaleController extends AbstractController
{
    #[Route('/search', name: 'app_decision_finale_search', methods: ['GET'])]
    public function search(Request $request, DecisionFinaleRepository $decisionFinaleRepository, CandidatureRepository $candidatureRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $decision = trim((string) $request->query->get('decision', ''));
        $entrepriseFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        $decisions = $decisionFinaleRepository->search($search, $decision, $entrepriseFilter);
        $candidatureEmails = $candidatureRepository->findEmailsByIds(array_map(
            static fn ($item) => $item->getEntretien()?->getCandidatureId() ?? 0,
            $decisions
        ));

        return $this->render('decision_finale/_table_body.html.twig', [
            'decisions' => $decisions,
            'candidatureEmails' => $candidatureEmails,
        ]);
    }

    #[Route('/', name: 'app_decision_finale_index', methods: ['GET'])]
    public function index(Request $request, DecisionFinaleRepository $decisionFinaleRepository, EntretienRepository $entretienRepository, CandidatureRepository $candidatureRepository): Response
    {
        $search = trim((string) $request->query->get('search', ''));
        $decision = trim((string) $request->query->get('decision', ''));
        $entrepriseFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        $decisions = $decisionFinaleRepository->search($search, $decision, $entrepriseFilter);
        $candidatureEmails = $candidatureRepository->findEmailsByIds(array_map(
            static fn (DecisionFinale $decisionFinale): int => $decisionFinale->getEntretien()?->getCandidatureId() ?? 0,
            $decisions
        ));

        return $this->render('decision_finale/index.html.twig', [
            'decisions' => $decisions,
            'candidatureEmails' => $candidatureEmails,
            'search' => $search,
            'decisionFilter' => $decision,
            'acceptedCount' => $decisionFinaleRepository->countByDecision('ACCEPTE'),
            'refusedCount' => $decisionFinaleRepository->countByDecision('REFUSE'),
            'pendingCount' => $decisionFinaleRepository->countPending(),
            'realisedWithoutDecisionCount' => count($entretienRepository->findRealisedWithoutDecision()),
        ]);
    }

    #[Route('/new', name: 'app_decision_finale_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $decisionFinale = new DecisionFinale();
        $decisionFinale->setDateDecision(new \DateTime());

        $form = $this->createForm(DecisionFinaleType::class, $decisionFinale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeDecision($decisionFinale);
            $entityManager->persist($decisionFinale);
            $entityManager->flush();

            $this->addFlash('success', 'La décision finale a été créée.');

            return $this->redirectToRoute('app_decision_finale_index');
        }

        return $this->render('decision_finale/new.html.twig', [
            'form' => $form,
            'decision_finale' => $decisionFinale,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_decision_finale_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DecisionFinale $decisionFinale, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DecisionFinaleType::class, $decisionFinale, [
            'current_entretien_id' => $decisionFinale->getEntretien()?->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->normalizeDecision($decisionFinale);
            $entityManager->flush();

            $this->addFlash('success', 'La décision finale a été modifiée.');

            return $this->redirectToRoute('app_decision_finale_index');
        }

        return $this->render('decision_finale/edit.html.twig', [
            'form' => $form,
            'decision_finale' => $decisionFinale,
        ]);
    }

    #[Route('/sync', name: 'app_decision_finale_sync', methods: ['POST'])]
    public function sync(Request $request, EntretienRepository $entretienRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('decision_sync', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_decision_finale_index');
        }

        $created = 0;

        foreach ($entretienRepository->findRealisedWithoutDecision() as $entretien) {
            $decision = new DecisionFinale();
            $decision->setEntretien($entretien);
            $decision->setDecision('EN_ATTENTE');
            $decision->setMotif('Décision auto-générée après réalisation de l\'entretien.');
            $decision->setDateDecision(new \DateTime());
            $decision->setCreatedAt(new \DateTime());
            $decision->setUpdatedAt(new \DateTime());
            $decision->setScore($entretien->getScoreFinal());

            $entityManager->persist($decision);
            $created++;
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d décision(s) générée(s).', $created));

        return $this->redirectToRoute('app_decision_finale_index');
    }

    #[Route('/auto-decide', name: 'app_decision_finale_auto_decide', methods: ['POST'])]
    public function autoDecide(Request $request, DecisionFinaleRepository $decisionFinaleRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('decision_auto_decide', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_decision_finale_index');
        }

        $acceptThreshold = (float) $request->request->get('accept_threshold', 14);
        $rejectThreshold = (float) $request->request->get('reject_threshold', 8);

        if ($rejectThreshold >= $acceptThreshold) {
            $this->addFlash('warning', 'Le seuil de refus doit être inférieur au seuil d\'acceptation.');

            return $this->redirectToRoute('app_decision_finale_index');
        }

        $updated = 0;
        foreach ($decisionFinaleRepository->findPendingWithScore() as $decisionFinale) {
            $score = $decisionFinale->getScore();

            if ($score === null) {
                continue;
            }

            if ($score >= $acceptThreshold) {
                $decisionFinale->setDecision('ACCEPTE');
                $decisionFinale->setMotif(sprintf('Décision automatique : score %.2f >= seuil %.2f', $score, $acceptThreshold));
                $updated++;
            } elseif ($score < $rejectThreshold) {
                $decisionFinale->setDecision('REFUSE');
                $decisionFinale->setMotif(sprintf('Décision automatique : score %.2f < seuil %.2f', $score, $rejectThreshold));
                $updated++;
            }

            $decisionFinale->setUpdatedAt(new \DateTime());
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('%d décision(s) mises à jour automatiquement.', $updated));

        return $this->redirectToRoute('app_decision_finale_index');
    }

    #[Route('/{id}/status', name: 'app_decision_finale_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, DecisionFinale $decisionFinale, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('update_status_decision_' . $decisionFinale->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_decision_finale_index');
        }

        $status = strtoupper(trim((string) $request->request->get('decision')));

        if (!in_array($status, ['ACCEPTE', 'REFUSE'], true)) {
            $this->addFlash('warning', 'Décision invalide.');

            return $this->redirectToRoute('app_decision_finale_index');
        }

        $decisionFinale->setDecision($status);
        $decisionFinale->setUpdatedAt(new \DateTime());

        if ($decisionFinale->getDateDecision() === null) {
            $decisionFinale->setDateDecision(new \DateTime());
        }

        if ($decisionFinale->getScore() === null) {
            $decisionFinale->setScore($decisionFinale->getEntretien()?->getScoreFinal());
        }

        if ($status === 'ACCEPTE') {
            $decisionFinale->setMotif('Décision validée manuellement par le responsable RH.');
        } else {
            $decisionFinale->setMotif('Décision refusée manuellement par le responsable RH.');
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf('La décision #%d a été mise à jour en %s.', $decisionFinale->getId(), $status));

        return $this->redirectToRoute('app_decision_finale_index');
    }

    #[Route('/{id}', name: 'app_decision_finale_delete', methods: ['POST'])]
    public function delete(Request $request, DecisionFinale $decisionFinale, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete_decision_' . $decisionFinale->getId(), $request->request->get('_token'))) {
            $entityManager->remove($decisionFinale);
            $entityManager->flush();
            $this->addFlash('success', 'La décision finale a été supprimée.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_decision_finale_index');
    }

    private function normalizeDecision(DecisionFinale $decisionFinale): void
    {
        $now = new \DateTime();

        if ($decisionFinale->getDateDecision() === null) {
            $decisionFinale->setDateDecision($now);
        }

        if ($decisionFinale->getCreatedAt() === null) {
            $decisionFinale->setCreatedAt($now);
        }

        if ($decisionFinale->getScore() === null) {
            $decisionFinale->setScore($decisionFinale->getEntretien()?->getScoreFinal());
        }

        $decisionFinale->setUpdatedAt($now);
    }
}
