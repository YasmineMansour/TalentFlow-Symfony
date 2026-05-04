<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\PostReport;
use App\Entity\User;
use App\Repository\PostRepository;
use App\Repository\PostReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PostReportController extends AbstractController
{
    #[Route('/post/{id}/report', name: 'post_report', methods: ['POST'])]
    public function report(Post $post, Request $request, EntityManagerInterface $entityManager, PostReportRepository $reportRepository, TranslatorInterface $translator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'errors' => [$translator->trans('api.post_report.auth_required')]], Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->json(['success' => false, 'errors' => [$translator->trans('api.post_report.admin_use_panel')]], Response::HTTP_FORBIDDEN);
        }

        if ($post->isHidden()) {
            return $this->json(['success' => false, 'errors' => [$translator->trans('api.post_report.already_hidden')]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($reportRepository->hasPendingReport($post, $user)) {
            return $this->json(['success' => false, 'errors' => [$translator->trans('api.post_report.duplicate_pending')]], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = trim((string) ($payload['reason'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));

        $errors = [];
        if (!in_array($reason, PostReport::reasons(), true)) {
            $errors[] = $translator->trans('api.post_report.invalid_reason');
        }
        if (mb_strlen($description) > 1000) {
            $errors[] = $translator->trans('api.post_report.description_too_long');
        }

        if ($errors) {
            return $this->json(['success' => false, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $report = (new PostReport())
            ->setPost($post)
            ->setReportedBy($user)
            ->setReason($reason)
            ->setDescription($description !== '' ? $description : null)
            ->setStatus(PostReport::STATUS_PENDING);

        $entityManager->persist($report);
        $entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/admin/reports', name: 'admin_reports', methods: ['GET'])]
    public function index(PostReportRepository $reportRepository, PostRepository $postRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/reports.html.twig', [
            'reports' => $reportRepository->findPendingOrdered(),
            'hiddenPosts' => $postRepository->findBy(['hidden' => true], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/admin/reports/{id}/dismiss', name: 'admin_reports_dismiss', methods: ['POST'])]
    public function dismiss(PostReport $report, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('dismiss_report_' . $report->getId(), (string) $request->request->get('_token'))) {
            $report->setStatus(PostReport::STATUS_DISMISSED);
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('api.post_report.flash_dismissed'));
        }

        return $this->redirectToRoute('admin_reports');
    }

    #[Route('/admin/reports/{id}/hide-post', name: 'admin_reports_hide_post', methods: ['POST'])]
    public function hidePost(PostReport $report, Request $request, EntityManagerInterface $entityManager, PostReportRepository $reportRepository, TranslatorInterface $translator): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('hide_report_' . $report->getId(), (string) $request->request->get('_token'))) {
            $post = $report->getPost();
            if ($post) {
                $post->setHidden(true);
                $reportRepository->resolvePendingForPost($post, PostReport::STATUS_RESOLVED);
            }
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('api.post_report.flash_hidden'));
        }

        return $this->redirectToRoute('admin_reports');
    }

    #[Route('/admin/reports/post/{id}/unhide', name: 'admin_reports_unhide_post', methods: ['POST'])]
    public function unhidePost(Post $post, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('unhide_post_' . $post->getId(), (string) $request->request->get('_token'))) {
            $post->setHidden(false);
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('api.post_report.flash_unhidden'));
        }

        return $this->redirectToRoute('admin_reports');
    }
}
