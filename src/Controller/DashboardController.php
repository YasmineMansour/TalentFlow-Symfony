<?php

namespace App\Controller;

use App\Repository\CandidatureRepository;
use App\Repository\DecisionFinaleRepository;
use App\Repository\EntretienRepository;
use App\Repository\OffreRepository;
use App\Repository\UserRepository;
use App\Service\CandidaturePriorityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository,
        EntretienRepository $entretienRepository,
        DecisionFinaleRepository $decisionFinaleRepository,
        CandidatureRepository $candidatureRepository,
        OffreRepository $offreRepository,
        CandidaturePriorityService $priorityService
    ): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->renderAdminDashboard($userRepository, $candidatureRepository, $entretienRepository, $offreRepository);
        }

        if ($this->isGranted('ROLE_RH')) {
            return $this->renderRhDashboard($userRepository, $entretienRepository, $decisionFinaleRepository, $candidatureRepository, $offreRepository, $priorityService);
        }

        return $this->renderCandidatDashboard($candidatureRepository, $offreRepository);
    }

    private function renderAdminDashboard(
        UserRepository $userRepository,
        CandidatureRepository $candidatureRepository,
        EntretienRepository $entretienRepository,
        OffreRepository $offreRepository
    ): Response
    {
        $allUsers = $userRepository->findAllOrdered();
        $totalUsers = count($allUsers);
        $admins = $userRepository->findByRole('ROLE_ADMIN');
        $rhs = $userRepository->findByRole('ROLE_RH');
        $candidats = $userRepository->findByRole('ROLE_CANDIDAT');

        $oneWeekAgo = new \DateTimeImmutable('-7 days');
        $newThisWeek = 0;
        foreach ($allUsers as $u) {
            if ($u->getCreatedAt() && $u->getCreatedAt() >= $oneWeekAgo) {
                $newThisWeek++;
            }
        }

        $twoWeeksAgo = new \DateTimeImmutable('-14 days');
        $newLastWeek = 0;
        foreach ($allUsers as $u) {
            if ($u->getCreatedAt() && $u->getCreatedAt() >= $twoWeeksAgo && $u->getCreatedAt() < $oneWeekAgo) {
                $newLastWeek++;
            }
        }
        $growthRate = $newLastWeek > 0 ? round((($newThisWeek - $newLastWeek) / $newLastWeek) * 100) : ($newThisWeek > 0 ? 100 : 0);

        $recentUsers = array_slice($allUsers, 0, 5);

        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = new \DateTimeImmutable("first day of -$i months midnight");
            $end = new \DateTimeImmutable("last day of -$i months 23:59:59");
            $count = 0;
            foreach ($allUsers as $u) {
                if ($u->getCreatedAt() && $u->getCreatedAt() >= $start && $u->getCreatedAt() <= $end) {
                    $count++;
                }
            }
            $monthlyData[] = [
                'label' => $start->format('M Y'),
                'count' => $count,
            ];
        }

        $totalCandidatures = count($candidatureRepository->findAll());
        $totalOffres = count($offreRepository->findAll());
        $totalEntretiens = count($entretienRepository->findAll());

        return $this->render('dashboard/admin.html.twig', [
            'totalUsers' => $totalUsers,
            'totalAdmins' => count($admins),
            'totalRh' => count($rhs),
            'totalCandidats' => count($candidats),
            'newThisWeek' => $newThisWeek,
            'growthRate' => $growthRate,
            'recentUsers' => $recentUsers,
            'monthlyData' => $monthlyData,
            'totalCandidatures' => $totalCandidatures,
            'totalOffres' => $totalOffres,
            'totalEntretiens' => $totalEntretiens,
        ]);
    }

    private function renderRhDashboard(
        UserRepository $userRepository,
        EntretienRepository $entretienRepository,
        DecisionFinaleRepository $decisionFinaleRepository,
        CandidatureRepository $candidatureRepository,
        OffreRepository $offreRepository,
        CandidaturePriorityService $priorityService
    ): Response
    {
        $candidats = $userRepository->findByRole('ROLE_CANDIDAT');
        $totalCandidatures = count($candidatureRepository->findAll());
        $totalOffres = count($offreRepository->findAll());

        // Priority & alert data for RH
        $entreprise = $this->getUser()?->getEntreprise();
        $activeCandidatures = $candidatureRepository->findNonTerminal($entreprise);

        $topPrioritaires = [];
        $candidaturesEnRetard = [];

        foreach ($activeCandidatures as $c) {
            $summary = $priorityService->summarize($c);
            if (in_array($summary['category'], ['PRIORITAIRE', 'A_EXAMINER'], true)) {
                $topPrioritaires[] = ['candidature' => $c, 'summary' => $summary];
            }
        }

        // Sort top by priorityScore desc, keep top 5
        usort($topPrioritaires, fn ($a, $b) => $b['summary']['priorityScore'] <=> $a['summary']['priorityScore']);
        $topPrioritaires = array_slice($topPrioritaires, 0, 5);

        // Delay alerts — only check candidatures in relevant statuses (avoids unnecessary DB calls)
        foreach ($activeCandidatures as $c) {
            if (!in_array($c->getStatut(), ['En attente', 'Validée RH'], true)) {
                continue;
            }
            $alerts = $priorityService->getDelayAlerts($c);
            if (count($alerts) > 0) {
                $candidaturesEnRetard[] = ['candidature' => $c, 'alerts' => $alerts];
            }
        }

        return $this->render('dashboard/rh.html.twig', [
            'totalCandidats'       => count($candidats),
            'offresActives'        => $totalOffres,
            'candidaturesRecues'   => $totalCandidatures,
            'entretiensAujourdhui' => $entretienRepository->countToday(),
            'decisionsEnAttente'   => $decisionFinaleRepository->countPending(),
            'topPrioritaires'      => $topPrioritaires,
            'candidaturesEnRetard' => $candidaturesEnRetard,
        ]);
    }

    private function renderCandidatDashboard(
        CandidatureRepository $candidatureRepository,
        OffreRepository $offreRepository
    ): Response
    {
        $allCandidatures = $candidatureRepository->findAll();
        $totalOffres = count($offreRepository->findAll());

        $enAttente = 0;
        $acceptees = 0;
        $refusees = 0;
        foreach ($allCandidatures as $c) {
            match ($c->getStatut()) {
                'En attente' => $enAttente++,
                'Acceptée' => $acceptees++,
                'Refusée' => $refusees++,
                default => null,
            };
        }

        return $this->render('dashboard/candidat.html.twig', [
            'candidaturesEnvoyees' => count($allCandidatures),
            'offresDisponibles' => $totalOffres,
            'enAttente' => $enAttente,
            'acceptees' => $acceptees,
            'refusees' => $refusees,
        ]);
    }
}
