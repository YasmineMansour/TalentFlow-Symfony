<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Form\EntrepriseType;
use App\Repository\EntrepriseRepository;
use App\Service\EntrepriseBusinessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/entreprise')]
class EntrepriseController extends AbstractController
{
    #[Route('/', name: 'entreprise_index', methods: ['GET'])]
    public function index(EntrepriseRepository $repo, EntrepriseBusinessService $business, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $secteur = $request->query->get('secteur');
        $tri = $request->query->get('tri', 'id');
        $ordre = $request->query->get('ordre', 'DESC');

        $entreprises = $repo->findByFilters($search, $secteur, $tri, $ordre);

        $entrepriseData = [];
        foreach ($entreprises as $entreprise) {
            $entrepriseData[] = [
                'entreprise' => $entreprise,
                'taille' => $business->getTaille($entreprise),
                'activite' => $business->getNiveauActivite($entreprise),
                'complete' => $business->isComplete($entreprise),
            ];
        }

        return $this->render('entreprise/index.html.twig', [
            'entrepriseData' => $entrepriseData,
            'search' => $search,
            'secteurs' => $repo->findDistinctSecteurs(),
            'secteur' => $secteur,
            'tri' => $tri,
            'ordre' => $ordre,
        ]);
    }

    #[Route('/new', name: 'entreprise_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = new Entreprise();
        $form = $this->createForm(EntrepriseType::class, $entreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entreprise);
            $em->flush();
            $this->addFlash('success', 'Entreprise créée avec succès.');
            return $this->redirectToRoute('entreprise_index');
        }

        return $this->render('entreprise/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'entreprise_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Entreprise $entreprise, EntrepriseBusinessService $business): Response
    {
        return $this->render('entreprise/show.html.twig', [
            'entreprise' => $entreprise,
            'taille' => $business->getTaille($entreprise),
            'activite' => $business->getNiveauActivite($entreprise),
            'complete' => $business->isComplete($entreprise),
        ]);
    }

    #[Route('/{id}/edit', name: 'entreprise_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Entreprise $entreprise, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EntrepriseType::class, $entreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Entreprise modifiée avec succès.');
            return $this->redirectToRoute('entreprise_index');
        }

        return $this->render('entreprise/edit.html.twig', [
            'entreprise' => $entreprise,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'entreprise_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Entreprise $entreprise, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $entreprise->getId(), $request->request->get('_token'))) {
            $em->remove($entreprise);
            $em->flush();
            $this->addFlash('success', 'Entreprise supprimée avec succès.');
        }

        return $this->redirectToRoute('entreprise_index');
    }
}
