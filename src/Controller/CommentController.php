<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comment')]
#[IsGranted('ROLE_USER')]
class CommentController extends AbstractController
{
    #[Route('/', name: 'comment_index', methods: ['GET'])]
    #[Route('/search', name: 'comment_search', methods: ['GET'])]
    public function index(CommentRepository $repo, PostRepository $postRepo, UserRepository $userRepo, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $postId = $request->query->get('post');
        $auteur = $request->query->get('auteur');
        $tri = $request->query->get('tri', 'createdAt');
        $ordre = $request->query->get('ordre', 'DESC');

        $comments = $repo->findByFilters($search, $postId, $auteur, $tri, $ordre);

        // Si AJAX, ne retourner que le partial tbody
        if ($request->isXmlHttpRequest()) {
            return $this->render('comment/_table_body.html.twig', [
                'comments' => $comments
            ]);
        }

        return $this->render('comment/index.html.twig', [
            'comments' => $comments,
            'search' => $search,
            'posts' => $postRepo->findAllOrderedByDate(),
            'postId' => $postId,
            'auteurs' => $userRepo->findAll(),
            'auteur' => $auteur,
            'tri' => $tri,
            'ordre' => $ordre,
        ]);
    }

    #[Route('/new', name: 'comment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $comment = new Comment();
        $comment->setAuthor($this->getUser());
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Commentaire créé avec succès.');
            return $this->redirectToRoute('comment_index');
        }

        return $this->render('comment/new.html.twig', [
            'form' => $form,
            'comment' => $comment,
        ]);
    }

    #[Route('/{id}', name: 'comment_show', methods: ['GET'])]
    public function show(Comment $comment): Response
    {
        return $this->render('comment/show.html.twig', [
            'comment' => $comment,
        ]);
    }

    #[Route('/{id}/edit', name: 'comment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Comment $comment, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Commentaire modifié avec succès.');
            return $this->redirectToRoute('comment_index');
        }

        return $this->render('comment/edit.html.twig', [
            'form' => $form,
            'comment' => $comment,
        ]);
    }

    #[Route('/{id}/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(Request $request, Comment $comment, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé avec succès.');
        }

        return $this->redirectToRoute('comment_index');
    }
}
