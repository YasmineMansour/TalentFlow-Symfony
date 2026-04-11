<?php

namespace App\Controller;

use App\Repository\OffreRepository;
use App\Repository\PostRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\CategorieRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    #[Route('/', name: 'public_home', methods: ['GET'])]
    public function home(OffreRepository $offreRepo, EntrepriseRepository $entrepriseRepo): Response
    {
        $offresRecentes = $offreRepo->findBy(
            ['active' => true],
            ['id' => 'DESC'],
            6
        );

        $totalOffres = $offreRepo->count(['active' => true]);
        $totalEntreprises = $entrepriseRepo->count([]);

        return $this->render('public/home.html.twig', [
            'offresRecentes' => $offresRecentes,
            'totalOffres' => $totalOffres,
            'totalEntreprises' => $totalEntreprises,
        ]);
    }

    #[Route('/offres', name: 'public_offres', methods: ['GET'])]
    public function offres(
        OffreRepository $offreRepo,
        CategorieRepository $categorieRepo,
        EntrepriseRepository $entrepriseRepo,
        Request $request
    ): Response {
        $search = $request->query->get('q', '');
        $categorieId = $request->query->get('categorie') ? (int) $request->query->get('categorie') : null;
        $typeContrat = $request->query->get('type', '');
        $localisation = $request->query->get('localisation', '');

        $offres = $offreRepo->findPublicByFilters($search, $categorieId, $typeContrat, $localisation);

        return $this->render('public/offres.html.twig', [
            'offres' => $offres,
            'search' => $search,
            'categories' => $categorieRepo->findAll(),
            'categorieId' => $categorieId,
            'typeContrat' => $typeContrat,
            'localisation' => $localisation,
        ]);
    }

    #[Route('/offres/{id}', name: 'public_offre_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function offreShow(int $id, OffreRepository $offreRepo): Response
    {
        $offre = $offreRepo->findOneBy(['id' => $id, 'active' => true]);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        return $this->render('public/offre_show.html.twig', [
            'offre' => $offre,
        ]);
    }

    #[Route('/offres/search', name: 'public_offres_search', methods: ['GET'])]
    public function offresSearch(OffreRepository $offreRepo, Request $request): JsonResponse
    {
        $q = $request->query->get('q', '');
        if (strlen($q) < 2) {
            return $this->json([]);
        }

        $offres = $offreRepo->findPublicByFilters($q, null, '', '', 8);

        $results = [];
        foreach ($offres as $offre) {
            $results[] = [
                'title' => $offre->getTitre(),
                'subtitle' => ($offre->getEntreprise() ? $offre->getEntreprise()->getNom() : '') . ' · ' . $offre->getLocalisation(),
                'url' => $this->generateUrl('public_offre_show', ['id' => $offre->getId()]),
                'icon' => 'briefcase',
            ];
        }

        return $this->json($results);
    }

    #[Route('/forum', name: 'public_forum', methods: ['GET'])]
    public function forum(PostRepository $postRepo, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $posts = $postRepo->findPublicPosts($search);

        return $this->render('public/forum.html.twig', [
            'posts' => $posts,
            'search' => $search,
        ]);
    }
}
