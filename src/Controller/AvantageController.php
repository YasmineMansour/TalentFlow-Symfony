<?php

namespace App\Controller;

use App\Entity\Avantage;
use App\Form\AvantageType;
use App\Repository\AvantageRepository;
use App\Repository\OffreRepository;
use App\Service\AvantageBusinessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/avantage')]
#[IsGranted('ROLE_RH')]
class AvantageController extends AbstractController
{
    #[Route('/', name: 'avantage_index', methods: ['GET'])]
    public function index(AvantageRepository $repo, AvantageBusinessService $business, OffreRepository $offreRepo, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $type = $request->query->get('type');
        $offreId = $request->query->get('offre') ? (int) $request->query->get('offre') : null;
        $tri = $request->query->get('tri', 'id');
        $ordre = $request->query->get('ordre', 'DESC');

        $avantages = $repo->findByFilters($search, $type, $offreId, $tri, $ordre);

        $avantageData = [];
        foreach ($avantages as $avantage) {
            $avantageData[] = [
                'avantage' => $avantage,
                'importance' => $business->getImportance($avantage),
                'qualiteDescription' => $business->getQualiteDescription($avantage),
                'complete' => $business->isComplete($avantage),
            ];
        }

        return $this->render('avantage/index.html.twig', [
            'avantageData' => $avantageData,
            'search' => $search,
            'types' => ['Financier', 'Bien-être', 'Matériel', 'AUTRE'],
            'type' => $type,
            'offres' => $offreRepo->findAll(),
            'offreId' => $offreId,
            'tri' => $tri,
            'ordre' => $ordre,
        ]);
    }

    #[Route('/new', name: 'avantage_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $avantage = new Avantage();
        $form = $this->createForm(AvantageType::class, $avantage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($avantage);
            $em->flush();
            $this->addFlash('success', 'Avantage créé avec succès.');
            return $this->redirectToRoute('avantage_index');
        }

        return $this->render('avantage/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'avantage_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Avantage $avantage, AvantageBusinessService $business): Response
    {
        return $this->render('avantage/show.html.twig', [
            'avantage' => $avantage,
            'importance' => $business->getImportance($avantage),
            'qualiteDescription' => $business->getQualiteDescription($avantage),
            'complete' => $business->isComplete($avantage),
        ]);
    }

    #[Route('/{id}/edit', name: 'avantage_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Avantage $avantage, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AvantageType::class, $avantage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Avantage modifié avec succès.');
            return $this->redirectToRoute('avantage_index');
        }

        return $this->render('avantage/edit.html.twig', [
            'avantage' => $avantage,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'avantage_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Avantage $avantage, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $avantage->getId(), $request->request->get('_token'))) {
            $em->remove($avantage);
            $em->flush();
            $this->addFlash('success', 'Avantage supprimé avec succès.');
        }

        return $this->redirectToRoute('avantage_index');
    }
}
