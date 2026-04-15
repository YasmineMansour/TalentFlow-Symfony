<?php

namespace App\Controller;

use App\Entity\Entretien;
use App\Repository\CandidatureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MonEntretienController extends AbstractController
{
    #[Route('/mon-entretien/{id}', name: 'app_mon_entretien', methods: ['GET'])]
    public function show(Entretien $entretien, CandidatureRepository $candidatureRepository): Response
    {
        // Page uniquement disponible pour les entretiens EN_LIGNE
        if ($entretien->getType() !== 'EN_LIGNE') {
            $this->addFlash('info', 'Cette page est réservée aux entretiens en ligne. Consultez votre email pour les détails de votre entretien.');
            return $this->redirectToRoute('app_dashboard');
        }

        $user = $this->getUser();

        // RH et admin peuvent voir tous les entretiens
        if (!$this->isGranted('ROLE_RH')) {
            // Candidat : vérifier que l'entretien lui appartient
            $candidature = $candidatureRepository->find($entretien->getCandidatureId() ?? 0);

            if ($candidature === null || $candidature->getCandidat()?->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException('Cet entretien ne vous appartient pas.');
            }
        }

        return $this->render('entretien/mon_entretien.html.twig', [
            'entretien' => $entretien,
        ]);
    }
}
