<?php

namespace App\Controller;

use App\Repository\CandidatureRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(UserRepository $userRepository, CandidatureRepository $candidatureRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->renderAdminDashboard($userRepository, $candidatureRepository);
        }

        if ($this->isGranted('ROLE_RH')) {
            return $this->renderRhDashboard($userRepository, $candidatureRepository);
        }

        return $this->renderCandidatDashboard();
    }

    private function renderAdminDashboard(UserRepository $userRepository, CandidatureRepository $candidatureRepository): Response
    {
        $allUsers = $userRepository->findAllOrdered();
        $totalUsers = count($allUsers);

        $admins = $userRepository->findByRole('ROLE_ADMIN');
        $rhs = $userRepository->findByRole('ROLE_RH');
        $candidats = $userRepository->findByRole('ROLE_CANDIDAT');

        // Candidatures stats
        $allCandidatures = $candidatureRepository->findAll();
        $totalCandidatures = count($allCandidatures);
        $candidaturesByStatut = $candidatureRepository->countByStatut();
        $candidaturesByType = $candidatureRepository->countByTypeContrat();

        // New users this week
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

        // Recent users
        $recentUsers = array_slice($allUsers, 0, 5);

        // Monthly data for chart (last 6 months)
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
            'totalCandidatures' => $totalCandidatures,
            'candidaturesByStatut' => $candidaturesByStatut,
            'candidaturesByType' => $candidaturesByType,
            'newThisWeek' => $newThisWeek,
            'growthRate' => $growthRate,
            'recentUsers' => $recentUsers,
            'monthlyData' => $monthlyData,
        ]);
    }

    private function renderRhDashboard(UserRepository $userRepository, CandidatureRepository $candidatureRepository): Response
    {
        $candidats = $userRepository->findByRole('ROLE_CANDIDAT');
        $totalCandidatures = count($candidatureRepository->findAll());
        $candidaturesByStatut = $candidatureRepository->countByStatut();

        return $this->render('dashboard/rh.html.twig', [
            'totalCandidats' => count($candidats),
            'totalCandidatures' => $totalCandidatures,
            'candidaturesByStatut' => $candidaturesByStatut,
        ]);
    }

    private function renderCandidatDashboard(): Response
    {
        return $this->render('dashboard/candidat.html.twig', [
            'candidaturesEnvoyees' => 0,
            'offresDisponibles' => 0,
            'enAttente' => 0,
            'acceptees' => 0,
            'refusees' => 0,
        ]);
    }
}
