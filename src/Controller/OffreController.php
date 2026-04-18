<?php

namespace App\Controller;

use App\Entity\Offre;
use App\Form\OffreType;
use App\Repository\CategorieRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\OffreRepository;
use App\Service\OffreBusinessService;
use App\Service\OffrePdfMailerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/offre')]
#[IsGranted('ROLE_USER')]
class OffreController extends AbstractController
{
    #[Route('/search', name: 'offre_search', methods: ['GET'])]
    public function search(
        OffreRepository $repo,
        OffreBusinessService $business,
        Request $request
    ): Response {
        $search = $request->query->get('q', '');
        $categorieId = $request->query->get('categorie') ? (int) $request->query->get('categorie') : null;
        $entrepriseId = $request->query->get('entreprise') ? (int) $request->query->get('entreprise') : null;
        $tri = $request->query->get('tri', 'id');
        $ordre = $request->query->get('ordre', 'DESC');

        $entrepriseFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        $offres = $repo->findByFilters($search, $categorieId, $entrepriseId, $tri, $ordre, $entrepriseFilter);

        $offreData = [];
        foreach ($offres as $offre) {
            $offreData[] = [
                'offre' => $offre,
                'classement' => $business->getClassement($offre),
                'coherence' => $business->getCoherence($offre),
                'complete' => $business->isComplete($offre),
            ];
        }

        return $this->render('offre/_table_body.html.twig', [
            'offreData' => $offreData,
        ]);
    }

    #[Route('/', name: 'offre_index', methods: ['GET'])]
    public function index(
        OffreRepository $repo,
        OffreBusinessService $business,
        CategorieRepository $categorieRepo,
        EntrepriseRepository $entrepriseRepo,
        Request $request
    ): Response {
        $search = $request->query->get('q', '');
        $categorieId = $request->query->get('categorie') ? (int) $request->query->get('categorie') : null;
        $entrepriseId = $request->query->get('entreprise') ? (int) $request->query->get('entreprise') : null;
        $tri = $request->query->get('tri', 'id');
        $ordre = $request->query->get('ordre', 'DESC');

        $entrepriseFilter = null;
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $entrepriseFilter = $this->getUser()->getEntreprise();
        }
        $offres = $repo->findByFilters($search, $categorieId, $entrepriseId, $tri, $ordre, $entrepriseFilter);

        $offreData = [];
        foreach ($offres as $offre) {
            $offreData[] = [
                'offre' => $offre,
                'classement' => $business->getClassement($offre),
                'coherence' => $business->getCoherence($offre),
                'complete' => $business->isComplete($offre),
            ];
        }

        return $this->render('offre/index.html.twig', [
            'offreData' => $offreData,
            'search' => $search,
            'categories' => $categorieRepo->findAll(),
            'entreprises' => $entrepriseRepo->findAll(),
            'categorieId' => $categorieId,
            'entrepriseId' => $entrepriseId,
            'tri' => $tri,
            'ordre' => $ordre,
        ]);
    }

    #[Route('/new', name: 'offre_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function new(Request $request, EntityManagerInterface $em, OffrePdfMailerService $pdfMailer, LoggerInterface $logger): Response
    {
        $offre = new Offre();

        // Auto-assigner l'entreprise du RH
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            $offre->setEntreprise($this->getUser()->getEntreprise());
        }

        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Forcer l'entreprise du RH même si le formulaire tente de la changer
            if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
                $offre->setEntreprise($this->getUser()->getEntreprise());
            }
            $em->persist($offre);
            $em->flush();

            // Générer le PDF et envoyer par email à l'entreprise
            try {
                $pdfMailer->sendOffreEmail($offre);
                $this->addFlash('success', 'Offre créée avec succès. Un email avec la fiche PDF a été envoyé à l\'entreprise.');
            } catch (\Throwable $e) {
                $logger->error('Erreur envoi email offre PDF: ' . $e->getMessage());
                $this->addFlash('success', 'Offre créée avec succès.');
                $this->addFlash('warning', 'L\'email avec la fiche PDF n\'a pas pu être envoyé.');
            }

            return $this->redirectToRoute('offre_index');
        }

        return $this->render('offre/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'offre_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Offre $offre, OffreBusinessService $business): Response
    {
        // RH ne peut voir que les offres de son entreprise
        if ($this->isGranted('ROLE_RH') && !$this->isGranted('ROLE_ADMIN')) {
            if ($offre->getEntreprise() !== $this->getUser()->getEntreprise()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette offre.');
            }
        }

        return $this->render('offre/show.html.twig', [
            'offre' => $offre,
            'classement' => $business->getClassement($offre),
            'coherence' => $business->getCoherence($offre),
            'complete' => $business->isComplete($offre),
        ]);
    }

    #[Route('/{id}/edit', name: 'offre_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_RH')]
    public function edit(Request $request, Offre $offre, EntityManagerInterface $em, OffrePdfMailerService $pdfMailer, LoggerInterface $logger): Response
    {
        // RH ne peut modifier que les offres de son entreprise
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($offre->getEntreprise() !== $this->getUser()->getEntreprise()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette offre.');
            }
        }

        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Empêcher le changement d'entreprise pour un RH
            if (!$this->isGranted('ROLE_ADMIN')) {
                $offre->setEntreprise($this->getUser()->getEntreprise());
            }
            $em->flush();

            // Générer le PDF mis à jour et envoyer par email à l'entreprise
            try {
                $pdfMailer->sendOffreEmail($offre);
                $this->addFlash('success', 'Offre modifiée avec succès. Un email avec la fiche PDF mise à jour a été envoyé à l\'entreprise.');
            } catch (\Throwable $e) {
                $logger->error('Erreur envoi email offre PDF: ' . $e->getMessage());
                $this->addFlash('success', 'Offre modifiée avec succès.');
                $this->addFlash('warning', 'L\'email avec la fiche PDF n\'a pas pu être envoyé.');
            }

            return $this->redirectToRoute('offre_index');
        }

        return $this->render('offre/edit.html.twig', [
            'offre' => $offre,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'offre_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_RH')]
    public function delete(Request $request, Offre $offre, EntityManagerInterface $em): Response
    {
        // RH ne peut supprimer que les offres de son entreprise
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($offre->getEntreprise() !== $this->getUser()->getEntreprise()) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette offre.');
            }
        }

        if ($this->isCsrfTokenValid('delete' . $offre->getId(), $request->request->get('_token'))) {
            $em->remove($offre);
            $em->flush();
            $this->addFlash('success', 'Offre supprimée avec succès.');
        }

        return $this->redirectToRoute('offre_index');
    }
}
