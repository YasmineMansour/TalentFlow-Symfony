<?php

namespace App\Controller;

use App\Repository\DecisionFinaleRepository;
use App\Repository\EntretienRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        UserRepository $userRepository,
        EntretienRepository $entretienRepository,
        DecisionFinaleRepository $decisionFinaleRepository
    ): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->renderAdminDashboard($userRepository);
        }

        if ($this->isGranted('ROLE_RH')) {
            return $this->renderRhDashboard($userRepository, $entretienRepository, $decisionFinaleRepository);
        }

        return $this->renderCandidatDashboard();
    }

    private function renderAdminDashboard(UserRepository $userRepository): Response
    {
        $allUsers = $userRepository->findAllOrdered();
        $totalUsers = count($allUsers);

        // Count by role
        $admins = $userRepository->findByRole('ROLE_ADMIN');
        $rhs = $userRepository->findByRole('ROLE_RH');
        $candidats = $userRepository->findByRole('ROLE_CANDIDAT');

        // New users this week (created in the last 7 days)
        $oneWeekAgo = new \DateTimeImmutable('-7 days');
        $newThisWeek = 0;
        foreach ($allUsers as $u) {
            if ($u->getCreatedAt() && $u->getCreatedAt() >= $oneWeekAgo) {
                $newThisWeek++;
            }
        }

        // Growth rate
        $twoWeeksAgo = new \DateTimeImmutable('-14 days');
        $newLastWeek = 0;
        foreach ($allUsers as $u) {
            if ($u->getCreatedAt() && $u->getCreatedAt() >= $twoWeeksAgo && $u->getCreatedAt() < $oneWeekAgo) {
                $newLastWeek++;
            }
        }
        $growthRate = $newLastWeek > 0 ? round((($newThisWeek - $newLastWeek) / $newLastWeek) * 100) : ($newThisWeek > 0 ? 100 : 0);

        // Recent users (last 5)
        $recentUsers = array_slice($allUsers, 0, 5);

        // Monthly registration data for chart (last 6 months)
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

        return $this->render('dashboard/admin.html.twig', [
            'totalUsers' => $totalUsers,
            'totalAdmins' => count($admins),
            'totalRh' => count($rhs),
            'totalCandidats' => count($candidats),
            'newThisWeek' => $newThisWeek,
            'growthRate' => $growthRate,
            'recentUsers' => $recentUsers,
            'monthlyData' => $monthlyData,
        ]);
    }

    private function renderRhDashboard(
        UserRepository $userRepository,
        EntretienRepository $entretienRepository,
        DecisionFinaleRepository $decisionFinaleRepository
    ): Response
    {
        $candidats = $userRepository->findByRole('ROLE_CANDIDAT');

        return $this->render('dashboard/rh.html.twig', [
            'totalCandidats' => count($candidats),
            'offresActives' => 0,       // Placeholder - module Offres
            'candidaturesRecues' => 0,   // Placeholder - module Candidatures
            'entretiensAujourdhui' => $entretienRepository->countToday(),
            'decisionsEnAttente' => $decisionFinaleRepository->countPending(),
        ]);
    }

    private function renderCandidatDashboard(): Response
    {
        return $this->render('dashboard/candidat.html.twig', [
            'candidaturesEnvoyees' => 0, // Placeholder
            'offresDisponibles' => 0,    // Placeholder
            'enAttente' => 0,            // Placeholder
            'acceptees' => 0,            // Placeholder
            'refusees' => 0,             // Placeholder
        ]);
    }
}
