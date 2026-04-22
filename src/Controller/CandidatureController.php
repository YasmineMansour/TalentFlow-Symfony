<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Entity\CandidatureStatusHistory;
use App\Entity\PieceJointe;
use App\Form\CandidatureType;
use App\Form\PieceJointeType;
use App\Repository\CandidatureRepository;
use App\Repository\CandidatureStatusHistoryRepository;
use App\Service\CandidatureNotificationOrchestrator;
use App\Service\CandidatureMatchingService;
use App\Service\CandidatureAnalysisService;
use App\Service\CandidatureAiRecommendationService;
use App\Service\CandidatureWorkflowService;
use App\Service\CandidatureCompletenessService;
use App\Service\CandidatureDuplicateGuardService;
use App\Service\CandidaturePriorityService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/candidature')]
#[IsGranted('ROLE_USER')]
class CandidatureController extends AbstractController
{
    #[Route('/search', name: 'app_candidature_search', methods: ['GET'])]
    public function search(
        Request $request,
        CandidatureRepository $repository,
        PaginatorInterface $paginator,
        CandidatureCompletenessService $completenessService,
        CandidaturePriorityService $priorityService,
    ): Response
    {
        $search = $request->query->get('search', '');
        $typeContrat = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortDir = $request->query->get('dir', 'DESC');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        [$entrepriseFilter, $candidatFilter] = $this->resolveCandidatureScopeFilters();
        $qb = $repository->createFilteredQueryBuilder(
            $search,
            $typeContrat,
            $statut,
            $sortBy,
            $sortDir,
            $entrepriseFilter,
            $candidatFilter
        );

        $candidatures = $this->paginateSafe($paginator, $qb, $page, $limit);

        [$completenessMap, $priorityMap] = $this->buildCandidatureAnalysisMaps(
            $candidatures,
            $completenessService,
            $priorityService
        );

        return $this->render('candidature/_results.html.twig', [
            'candidatures' => $candidatures,
            'completenessMap' => $completenessMap,
            'priorityMap' => $priorityMap,
        ]);
    }

    #[Route('/', name: 'app_candidature_index', methods: ['GET'])]
    public function index(
        Request $request,
        CandidatureRepository $repository,
        PaginatorInterface $paginator,
        CandidatureCompletenessService $completenessService,
        CandidaturePriorityService $priorityService,
        ChartBuilderInterface $chartBuilder,
    ): Response
    {
        $search = $request->query->get('search', '');
        $typeContrat = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortDir = $request->query->get('dir', 'DESC');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        [$entrepriseFilter, $candidatFilter] = $this->resolveCandidatureScopeFilters();
        $qb = $repository->createFilteredQueryBuilder(
            $search,
            $typeContrat,
            $statut,
            $sortBy,
            $sortDir,
            $entrepriseFilter,
            $candidatFilter
        );

        $candidatures = $this->paginateSafe($paginator, $qb, $page, $limit);

        $statsQb = $repository->createFilteredQueryBuilder(
            '',
            '',
            '',
            'createdAt',
            'DESC',
            $entrepriseFilter,
            $candidatFilter
        );

        /** @var Candidature[] $candidaturesForStats */
        $candidaturesForStats = $statsQb->getQuery()->getResult();

        $statistics = $this->computeCandidatureStatistics(
            $candidaturesForStats,
            $completenessService,
            $priorityService
        );

        $charts = $this->buildCandidatureCharts($chartBuilder, $statistics);

        [$completenessMap, $priorityMap] = $this->buildCandidatureAnalysisMaps(
            $candidatures,
            $completenessService,
            $priorityService
        );

        return $this->render('candidature/index.html.twig', [
            'candidatures'    => $candidatures,
            'search'          => $search,
            'typeContrat'     => $typeContrat,
            'statut'          => $statut,
            'sortBy'          => $sortBy,
            'sortDir'         => $sortDir,
            'completenessMap' => $completenessMap,
            'priorityMap'     => $priorityMap,
            'candidatureStats' => $statistics,
            'statutChart' => $charts['statutChart'],
            'completenessChart' => $charts['completenessChart'],
            'priorityChart' => $charts['priorityChart'],
            'evolutionChart' => $charts['evolutionChart'],
        ]);
    }

    /**
     * @param Candidature[] $candidatures
     * @return array{
     *   total: int,
     *   statut: array<string,int>,
     *   completeness: array<string,int>,
     *   priority: array<string,int>,
     *   evolution: array<string,int>,
     *   completeCount: int,
     *   blockingCount: int,
     *   prioritaireCount: int
     * }
     */
    private function computeCandidatureStatistics(
        array $candidatures,
        CandidatureCompletenessService $completenessService,
        CandidaturePriorityService $priorityService,
    ): array {
        $statutCounts = [
            'En attente' => 0,
            'Validée RH' => 0,
            'Entretien' => 0,
            'Acceptée' => 0,
            'Refusée' => 0,
        ];

        $completenessCounts = [
            'Complet' => 0,
            'À compléter' => 0,
            'Bloquante' => 0,
        ];

        $priorityCounts = [
            'Prioritaire' => 0,
            'À examiner' => 0,
            'Incomplète' => 0,
            'Faible priorité' => 0,
        ];

        $evolutionCounts = [];
        $completeCount = 0;
        $blockingCount = 0;
        $prioritaireCount = 0;

        foreach ($candidatures as $candidature) {
            $statut = (string) $candidature->getStatut();
            if (array_key_exists($statut, $statutCounts)) {
                $statutCounts[$statut]++;
            }

            $completenessSummary = $completenessService->analyze($candidature);
            $level = (string) ($completenessSummary['level'] ?? '');
            if ($level === 'COMPLET') {
                $completenessCounts['Complet']++;
                $completeCount++;
            } elseif ($level === 'A_COMPLETER') {
                $completenessCounts['À compléter']++;
            } else {
                $completenessCounts['Bloquante']++;
                $blockingCount++;
            }

            $prioritySummary = $priorityService->summarize($candidature);
            $category = (string) ($prioritySummary['category'] ?? '');
            if ($category === 'PRIORITAIRE') {
                $priorityCounts['Prioritaire']++;
                $prioritaireCount++;
            } elseif ($category === 'A_EXAMINER') {
                $priorityCounts['À examiner']++;
            } elseif ($category === 'INCOMPLETE') {
                $priorityCounts['Incomplète']++;
            } else {
                $priorityCounts['Faible priorité']++;
            }

            $date = $candidature->getDateCandidature() ?? $candidature->getCreatedAt();
            if ($date !== null) {
                $monthLabel = $date->format('m/Y');
                $evolutionCounts[$monthLabel] = ($evolutionCounts[$monthLabel] ?? 0) + 1;
            }
        }

        ksort($evolutionCounts);

        return [
            'total' => count($candidatures),
            'statut' => $statutCounts,
            'completeness' => $completenessCounts,
            'priority' => $priorityCounts,
            'evolution' => $evolutionCounts,
            'completeCount' => $completeCount,
            'blockingCount' => $blockingCount,
            'prioritaireCount' => $prioritaireCount,
        ];
    }

    /**
     * @param array{
     *   statut: array<string,int>,
     *   completeness: array<string,int>,
     *   priority: array<string,int>,
     *   evolution: array<string,int>
     * } $statistics
     * @return array{statutChart: Chart, completenessChart: Chart, priorityChart: Chart, evolutionChart: Chart}
     */
    private function buildCandidatureCharts(ChartBuilderInterface $chartBuilder, array $statistics): array
    {
        $statutChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $statutChart->setData([
            'labels' => array_keys($statistics['statut']),
            'datasets' => [[
                'data' => array_values($statistics['statut']),
                'backgroundColor' => ['#6c757d', '#0dcaf0', '#ffc107', '#198754', '#dc3545'],
                'borderWidth' => 1,
            ]],
        ]);
        $statutChart->setOptions([
            'plugins' => ['legend' => ['position' => 'bottom']],
            'maintainAspectRatio' => false,
        ]);

        $completenessChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $completenessChart->setData([
            'labels' => array_keys($statistics['completeness']),
            'datasets' => [[
                'data' => array_values($statistics['completeness']),
                'backgroundColor' => ['#198754', '#ffc107', '#dc3545'],
                'borderWidth' => 1,
            ]],
        ]);
        $completenessChart->setOptions([
            'plugins' => ['legend' => ['position' => 'bottom']],
            'maintainAspectRatio' => false,
        ]);

        $priorityChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $priorityChart->setData([
            'labels' => array_keys($statistics['priority']),
            'datasets' => [[
                'data' => array_values($statistics['priority']),
                'backgroundColor' => ['#198754', '#0dcaf0', '#fd7e14', '#6c757d'],
                'borderWidth' => 1,
            ]],
        ]);
        $priorityChart->setOptions([
            'plugins' => ['legend' => ['position' => 'bottom']],
            'maintainAspectRatio' => false,
        ]);

        $evolutionChart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $evolutionChart->setData([
            'labels' => array_keys($statistics['evolution']),
            'datasets' => [[
                'label' => 'Candidatures',
                'data' => array_values($statistics['evolution']),
                'backgroundColor' => '#0d6efd',
                'borderRadius' => 6,
            ]],
        ]);
        $evolutionChart->setOptions([
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            'maintainAspectRatio' => false,
        ]);

        return [
            'statutChart' => $statutChart,
            'completenessChart' => $completenessChart,
            'priorityChart' => $priorityChart,
            'evolutionChart' => $evolutionChart,
        ];
    }

    private function paginateSafe(
        PaginatorInterface $paginator,
        mixed $target,
        int $page,
        int $limit
    ): \Knp\Component\Pager\Pagination\PaginationInterface {
        $pagination = $paginator->paginate($target, $page, $limit);

        // If the requested page exceeds the available pages (e.g. filters narrowed
        // the result set after the user navigated to a later page), fall back to
        // page 1 so we never render an empty out-of-range page.
        if ($page > 1 && $pagination->getTotalItemCount() > 0 && $page > $pagination->getPageCount()) {
            $pagination = $paginator->paginate($target, 1, $limit);
        }

        return $pagination;
    }

    private function resolveCandidatureScopeFilters(): array
    {
        $entrepriseFilter = null;
        $candidatFilter = null;

        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }

        if ($this->isGranted('ROLE_CANDIDAT') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_RH')) {
            $candidatFilter = $this->getUser();
        }

        return [$entrepriseFilter, $candidatFilter];
    }

    private function buildCandidatureAnalysisMaps(
        iterable $candidatures,
        CandidatureCompletenessService $completenessService,
        CandidaturePriorityService $priorityService
    ): array {
        $completenessMap = [];
        $priorityMap = [];

        foreach ($candidatures as $candidature) {
            $analysis = $completenessService->analyze($candidature);
            $completenessMap[$candidature->getId()] = [
                'score' => $analysis['score'],
                'level' => $analysis['level'],
                'label' => $analysis['label'],
            ];
            $priorityMap[$candidature->getId()] = $priorityService->summarize($candidature);
        }

        return [$completenessMap, $priorityMap];
    }

    #[Route('/new', name: 'app_candidature_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        CandidatureMatchingService $matchingService,
        CandidatureDuplicateGuardService $duplicateGuard,
        CandidatureNotificationOrchestrator $notificationOrchestrator,
    ): Response
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

            $duplicateAnalysis = $duplicateGuard->analyze($candidature);
            if ($duplicateAnalysis['severity'] === 'BLOCKING') {
                $form->addError(new \Symfony\Component\Form\FormError('Une candidature en doublon existe deja pour cette offre. Veuillez consulter la candidature existante avant d\'en creer une nouvelle.'));
                $this->addFlash('error', (string) ($duplicateAnalysis['reason'] ?? 'Candidature en doublon bloquante.'));

                return $this->render('candidature/new.html.twig', [
                    'candidature' => $candidature,
                    'form' => $form,
                    'offre' => $offre,
                    'isCandidat' => $isCandidat,
                    'duplicateAnalysis' => $duplicateAnalysis,
                ]);
            }

            if ($duplicateAnalysis['severity'] === 'WARNING') {
                $this->addFlash('warning', 'Une ancienne candidature existe deja pour cette offre. La nouvelle candidature est autorisee mais sera signalee comme doublon potentiel.');
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

            $notificationOrchestrator->handlePostSubmissionNotifications($candidature);

            $this->addFlash('success', 'Candidature pour "' . $candidature->getTitrePoste() . '" créée avec succès !');
            return $this->redirectToRoute('app_candidature_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('candidature/new.html.twig', [
            'candidature' => $candidature,
            'form' => $form,
            'offre' => $offre,
            'isCandidat' => $isCandidat,
            'duplicateAnalysis' => null,
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
        CandidatureStatusHistoryRepository $historyRepository,
        CandidatureAnalysisService $analysisService,
        CandidatureAiRecommendationService $aiRecommendationService,
        CandidatureWorkflowService $workflowService,
        CandidatureCompletenessService $completenessService,
        CandidatureDuplicateGuardService $duplicateGuard,
        CandidaturePriorityService $priorityService,
    ): Response
    {
        $pieceJointe = new PieceJointe();
        $pieceJointe->setCandidature($candidature);
        $form = $this->createForm(PieceJointeType::class, $pieceJointe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($pieceJointe);
            $em->flush();

            $this->addFlash('success', 'Pièce jointe ajoutée avec succès.');
            return $this->redirectToRoute('app_candidature_show', ['id' => $candidature->getId()]);
        }

        $history = $historyRepository->findByCandidatureOrdered($candidature);
        $analysisPayload = $analysisService->analyze($candidature);
        $aiRecommendation = $aiRecommendationService->generateRecommendation($candidature, $analysisPayload);
        $acceptedSnapshot = $historyRepository->findAcceptedTransition($candidature);
        $timeToHireDays = null;
        if ($acceptedSnapshot !== null && $candidature->getDateCandidature() !== null) {
            $seconds = $acceptedSnapshot->getChangedAt()?->getTimestamp() - $candidature->getDateCandidature()->getTimestamp();
            if ($seconds !== null && $seconds >= 0) {
                $timeToHireDays = round($seconds / 86400, 2);
            }
        }

        return $this->render('candidature/show.html.twig', [
            'candidature'          => $candidature,
            'pieceJointeForm'      => $form,
            'statusHistory'        => $history,
            'availableTransitions' => $workflowService->getEnabledTransitions($candidature),
            'timeToHireDays'       => $timeToHireDays,
            'completeness'         => $completenessService->analyze($candidature),
            'duplicateAnalysis'    => $duplicateGuard->analyze($candidature, $candidature->getId()),
            'priorityAnalysis'     => $priorityService->analyze($candidature),
            'aiRecommendation'     => $aiRecommendation,
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
        CandidatureDuplicateGuardService $duplicateGuard,
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
                        'duplicateAnalysis' => $duplicateGuard->analyze($candidature, $candidature->getId()),
                    ]);
                }
            }

            $duplicateAnalysis = $duplicateGuard->analyze($candidature, $candidature->getId());
            if ($duplicateAnalysis['severity'] === 'BLOCKING') {
                $form->addError(new \Symfony\Component\Form\FormError('Une candidature en doublon existe deja pour cette offre. Veuillez consulter la candidature existante avant d\'en creer une nouvelle.'));
                $this->addFlash('error', (string) ($duplicateAnalysis['reason'] ?? 'Candidature en doublon bloquante.'));

                return $this->render('candidature/edit.html.twig', [
                    'candidature' => $candidature,
                    'form' => $form,
                    'isCandidat' => $isCandidat,
                    'duplicateAnalysis' => $duplicateAnalysis,
                ]);
            }

            if ($duplicateAnalysis['severity'] === 'WARNING') {
                $this->addFlash('warning', 'Une ancienne candidature existe deja pour cette offre. La nouvelle candidature est autorisee mais sera signalee comme doublon potentiel.');
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
            'duplicateAnalysis' => $duplicateGuard->analyze($candidature, $candidature->getId()),
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
        $storedFilename = $pieceJointe->getCheminFichier();
        if ($storedFilename === null || $storedFilename === '') {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        // Backward compatibility: older rows may store a relative path (uploads/pieces_jointes/xxx)
        if (str_contains($storedFilename, '/')) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($storedFilename, '/');
        } else {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/pieces_jointes/' . $storedFilename;
        }

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file($filePath, $pieceJointe->getNomFichier());
    }

    #[Route('/piece-jointe/{id}/delete', name: 'app_piece_jointe_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePieceJointe(Request $request, PieceJointe $pieceJointe, EntityManagerInterface $em): Response
    {
        $candidatureId = $pieceJointe->getCandidature()->getId();

        if ($this->isCsrfTokenValid('delete_pj' . $pieceJointe->getId(), $request->request->get('_token'))) {
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
