<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AuditController extends AbstractController
{
    #[Route('/admin/audit', name: 'app_admin_audit', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('dh_auditor_list_audits');
    }
}
