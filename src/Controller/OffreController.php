<?php

namespace App\Controller;

use App\Entity\Offre;
use App\Form\OffreType;
use App\Repository\CategorieRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\OffreRepository;
use App\Service\OffreBusinessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/offre')]
class OffreController extends AbstractController
{
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

        $offres = $repo->findByFilters($search, $categorieId, $entrepriseId, $tri, $ordre);

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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $offre = new Offre();
        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offre);
            $em->flush();
            $this->addFlash('success', 'Offre créée avec succès.');
            return $this->redirectToRoute('offre_index');
        }

        return $this->render('offre/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'offre_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Offre $offre, OffreBusinessService $business): Response
    {
        return $this->render('offre/show.html.twig', [
            'offre' => $offre,
            'classement' => $business->getClassement($offre),
            'coherence' => $business->getCoherence($offre),
            'complete' => $business->isComplete($offre),
        ]);
    }

    #[Route('/{id}/edit', name: 'offre_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Offre $offre, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Offre modifiée avec succès.');
            return $this->redirectToRoute('offre_index');
        }

        return $this->render('offre/edit.html.twig', [
            'offre' => $offre,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'offre_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Offre $offre, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $offre->getId(), $request->request->get('_token'))) {
            $em->remove($offre);
            $em->flush();
            $this->addFlash('success', 'Offre supprimée avec succès.');
        }

        return $this->redirectToRoute('offre_index');
    }
}
