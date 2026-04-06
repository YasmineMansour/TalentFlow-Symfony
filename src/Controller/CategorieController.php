<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use App\Service\CategorieBusinessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/categorie')]
class CategorieController extends AbstractController
{
    #[Route('/', name: 'categorie_index', methods: ['GET'])]
    public function index(CategorieRepository $repo, CategorieBusinessService $business, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $tri = $request->query->get('tri', 'id');
        $ordre = $request->query->get('ordre', 'DESC');

        $categories = $repo->findByFilters($search, $tri, $ordre);

        $categorieData = [];
        foreach ($categories as $categorie) {
            $categorieData[] = [
                'categorie' => $categorie,
                'popularite' => $business->getPopularite($categorie),
                'tauxActivite' => $business->getTauxActivite($categorie),
                'complete' => $business->isComplete($categorie),
            ];
        }

        return $this->render('categorie/index.html.twig', [
            'categorieData' => $categorieData,
            'search' => $search,
            'tri' => $tri,
            'ordre' => $ordre,
        ]);
    }

    #[Route('/new', name: 'categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new Categorie();
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée avec succès.');
            return $this->redirectToRoute('categorie_index');
        }

        return $this->render('categorie/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'categorie_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Categorie $categorie, CategorieBusinessService $business): Response
    {
        return $this->render('categorie/show.html.twig', [
            'categorie' => $categorie,
            'popularite' => $business->getPopularite($categorie),
            'tauxActivite' => $business->getTauxActivite($categorie),
            'complete' => $business->isComplete($categorie),
        ]);
    }

    #[Route('/{id}/edit', name: 'categorie_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie modifiée avec succès.');
            return $this->redirectToRoute('categorie_index');
        }

        return $this->render('categorie/edit.html.twig', [
            'categorie' => $categorie,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'categorie_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $categorie->getId(), $request->request->get('_token'))) {
            $em->remove($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée avec succès.');
        }

        return $this->redirectToRoute('categorie_index');
    }
}
