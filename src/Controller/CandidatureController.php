<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\CandidatureStatusHistory;
use App\Entity\PieceJointe;
use App\Form\CandidatureType;
use App\Form\PieceJointeType;
use App\Repository\CandidatureRepository;
use App\Repository\CandidatureStatusHistoryRepository;
use App\Service\CandidatureMatchingService;
use App\Service\CandidatureWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidature')]
#[IsGranted('ROLE_USER')]
class CandidatureController extends AbstractController
{
    #[Route('/search', name: 'app_candidature_search', methods: ['GET'])]
    public function search(Request $request, CandidatureRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $typeContrat = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortDir = $request->query->get('dir', 'DESC');

        $entrepriseFilter = null;
        $candidatFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        if ($this->isGranted('ROLE_CANDIDAT') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_RH')) {
            $candidatFilter = $this->getUser();
        }
        $candidatures = $repository->findFiltered($search, $typeContrat, $statut, $sortBy, $sortDir, $entrepriseFilter, $candidatFilter);

        return $this->render('candidature/_table_body.html.twig', [
            'candidatures' => $candidatures,
        ]);
    }

    #[Route('/', name: 'app_candidature_index', methods: ['GET'])]
    public function index(Request $request, CandidatureRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $typeContrat = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortDir = $request->query->get('dir', 'DESC');

        $entrepriseFilter = null;
        $candidatFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        if ($this->isGranted('ROLE_CANDIDAT') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_RH')) {
            $candidatFilter = $this->getUser();
        }
        $candidatures = $repository->findFiltered($search, $typeContrat, $statut, $sortBy, $sortDir, $entrepriseFilter, $candidatFilter);

        return $this->render('candidature/index.html.twig', [
            'candidatures' => $candidatures,
            'search' => $search,
            'typeContrat' => $typeContrat,
            'statut' => $statut,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_candidature_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, CandidatureMatchingService $matchingService): Response
    {
        $offreId = $request->query->get('offre');
        $user = $this->getUser();
        $isCandidat = $this->isGranted('ROLE_CANDIDAT') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_RH');

        // Pour les candidats, l'offre est obligatoire
        if ($isCandidat && !$offreId) {
            $this->addFlash('warning', 'Vous devez postuler depuis une offre.');
            return $this->redirectToRoute('offre_index');
        }

        $candidature = new Candidature();

        // Si offre passée, pré-remplir
        $offre = null;
        if ($offreId) {
            $offre = $em->getRepository(\App\Entity\Offre::class)->find($offreId);
            if ($offre) {
                $candidature->setOffre($offre);
                $candidature->setTitrePoste($offre->getTitre());
                $candidature->setEntreprise($offre->getEntreprise() ? $offre->getEntreprise()->getNom() : '');
                $candidature->setTypeContrat($offre->getTypeContrat());
                $candidature->setDescription($offre->getDescription());
                $candidature->setDateCandidature(new \DateTimeImmutable());
                if ($isCandidat) {
                    $candidature->setCandidat($user);
                    // Pré-remplir le téléphone du candidat
                    if ($user->getTelephone()) {
                        $candidature->setTelephone($user->getTelephone());
                    }
                }
            }
        }

        $form = $this->createForm(CandidatureType::class, $candidature, [
            'is_candidat' => $isCandidat,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sécurise le lien offre/candidat
            if ($offre) {
                $candidature->setOffre($offre);
            }
            if ($isCandidat) {
                $candidature->setCandidat($user);
                $candidature->setStatut('En attente');
                $candidature->setDateCandidature(new \DateTimeImmutable());
            }

            $candidature->setMatchingScore($matchingService->computeScore($candidature));

            // Upload CV
            $cvFile = $form->get('cvFile')->getData();
            if ($cvFile) {
                $newFilename = $this->uploadFile($cvFile, $slugger, 'cv');
                $candidature->setCvFilename($newFilename);
            }

            // Upload Lettre de motivation
            $lettreFile = $form->get('lettreMotivationFile')->getData();
            if ($lettreFile) {
                $newFilename = $this->uploadFile($lettreFile, $slugger, 'lettre');
                $candidature->setLettreMotivationFilename($newFilename);
            }

            $em->persist($candidature);

            $history = (new CandidatureStatusHistory())
                ->setCandidature($candidature)
                ->setChangedBy($this->getUser())
                ->setFromStatus(null)
                ->setToStatus((string) $candidature->getStatut())
                ->setTransitionName('created')
                ->setNote('Creation de la candidature.');
            $em->persist($history);

            $em->flush();
            $this->addFlash('success', 'Candidature pour "' . $candidature->getTitrePoste() . '" créée avec succès !');
            return $this->redirectToRoute('app_candidature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('candidature/new.html.twig', [
            'candidature' => $candidature,
            'form' => $form,
            'offre' => $offre,
            'isCandidat' => $isCandidat,
        ]);
    }

    private function uploadFile($file, SluggerInterface $slugger, string $prefix): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $prefix . '-' . $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/candidatures';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $file->move($uploadDir, $newFilename);

        return $newFilename;
    }

    #[Route('/{id}', name: 'app_candidature_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[Route('/{id}/upload', name: 'app_candidature_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function show(
        Request $request,
        Candidature $candidature,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        CandidatureStatusHistoryRepository $historyRepository,
        CandidatureWorkflowService $workflowService,
    ): Response
    {
        $pieceJointe = new PieceJointe();
        $form = $this->createForm(PieceJointeType::class, $pieceJointe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fichier = $form->get('fichier')->getData();
            if ($fichier) {
                $originalFilename = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $fichier->guessExtension();
                $fileSize = $fichier->getSize() ?: 0;

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/pieces_jointes';
                $fichier->move($uploadDir, $newFilename);

                $pieceJointe->setNomFichier($fichier->getClientOriginalName());
                $pieceJointe->setCheminFichier('uploads/pieces_jointes/' . $newFilename);
                $pieceJointe->setTailleFichier($fileSize);
                $pieceJointe->setCandidature($candidature);

                $em->persist($pieceJointe);
                $em->flush();

                $this->addFlash('success', 'Pièce jointe ajoutée avec succès.');
                return $this->redirectToRoute('app_candidature_show', ['id' => $candidature->getId()]);
            }
        }

        $history = $historyRepository->findByCandidatureOrdered($candidature);
        $acceptedSnapshot = $historyRepository->findAcceptedTransition($candidature);
        $timeToHireDays = null;
        if ($acceptedSnapshot !== null && $candidature->getDateCandidature() !== null) {
            $seconds = $acceptedSnapshot->getChangedAt()?->getTimestamp() - $candidature->getDateCandidature()->getTimestamp();
            if ($seconds !== null && $seconds >= 0) {
                $timeToHireDays = round($seconds / 86400, 2);
            }
        }

        return $this->render('candidature/show.html.twig', [
            'candidature' => $candidature,
            'pieceJointeForm' => $form,
            'statusHistory' => $history,
            'availableTransitions' => $workflowService->getEnabledTransitions($candidature),
            'timeToHireDays' => $timeToHireDays,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_candidature_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Candidature $candidature,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        CandidatureMatchingService $matchingService,
        CandidatureWorkflowService $workflowService,
    ): Response
    {
        $isCandidat = $this->isGranted('ROLE_CANDIDAT') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_RH');
        $previousStatus = $candidature->getStatut();

        $form = $this->createForm(CandidatureType::class, $candidature, [
            'is_candidat' => $isCandidat,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Upload CV
            $cvFile = $form->get('cvFile')->getData();
            if ($cvFile) {
                $newFilename = $this->uploadFile($cvFile, $slugger, 'cv');
                $candidature->setCvFilename($newFilename);
            }

            // Upload Lettre de motivation
            $lettreFile = $form->get('lettreMotivationFile')->getData();
            if ($lettreFile) {
                $newFilename = $this->uploadFile($lettreFile, $slugger, 'lettre');
                $candidature->setLettreMotivationFilename($newFilename);
            }

            $requestedStatus = $previousStatus;
            if (!$isCandidat && $form->has('statut')) {
                $requestedStatus = (string) $form->get('statut')->getData();
                $candidature->setStatut((string) $previousStatus);
            }

            if (!$isCandidat && $requestedStatus !== $previousStatus) {
                try {
                    /** @var \App\Entity\User|null $actor */
                    $actor = $this->getUser();
                    $workflowService->transitionToStatus($candidature, $requestedStatus, $actor);
                } catch (\Throwable $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->render('candidature/edit.html.twig', [
                        'candidature' => $candidature,
                        'form' => $form,
                        'isCandidat' => $isCandidat,
                    ]);
                }
            }

            $candidature->setMatchingScore($matchingService->computeScore($candidature));
            $candidature->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Candidature modifiée avec succès.');
            return $this->redirectToRoute('app_candidature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('candidature/edit.html.twig', [
            'candidature' => $candidature,
            'form' => $form,
            'isCandidat' => $isCandidat,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_candidature_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Candidature $candidature, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $candidature->getId(), $request->request->get('_token'))) {
            $em->remove($candidature);
            $em->flush();
            $this->addFlash('success', 'Candidature supprimée avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_candidature_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/piece-jointe/{id}/download', name: 'app_piece_jointe_download', requirements: ['id' => '\d+'])]
    public function downloadPieceJointe(PieceJointe $pieceJointe): BinaryFileResponse
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $pieceJointe->getCheminFichier();

        return $this->file($filePath, $pieceJointe->getNomFichier());
    }

    #[Route('/piece-jointe/{id}/delete', name: 'app_piece_jointe_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePieceJointe(Request $request, PieceJointe $pieceJointe, EntityManagerInterface $em): Response
    {
        $candidatureId = $pieceJointe->getCandidature()->getId();

        if ($this->isCsrfTokenValid('delete_pj' . $pieceJointe->getId(), $request->request->get('_token'))) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $pieceJointe->getCheminFichier();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $em->remove($pieceJointe);
            $em->flush();
            $this->addFlash('success', 'Pièce jointe supprimée.');
        }

        return $this->redirectToRoute('app_candidature_show', ['id' => $candidatureId]);
    }

    #[Route('/{id}/status-transition/{transition}', name: 'app_candidature_transition', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function transitionStatus(
        Request $request,
        Candidature $candidature,
        string $transition,
        EntityManagerInterface $em,
        CandidatureWorkflowService $workflowService
    ): Response {
        $tokenId = sprintf('candidature_transition_%d_%s', $candidature->getId(), $transition);
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_candidature_show', ['id' => $candidature->getId()]);
        }

        try {
            /** @var \App\Entity\User|null $actor */
            $actor = $this->getUser();
            $workflowService->applyTransition($candidature, $transition, $actor, 'Transition declenchee depuis la fiche candidature.');
            $em->flush();
            $this->addFlash('success', 'Statut mis a jour via workflow.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_candidature_show', ['id' => $candidature->getId()]);
    }
}
