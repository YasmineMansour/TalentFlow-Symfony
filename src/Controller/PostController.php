<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/post')]
#[IsGranted('ROLE_USER')]
class PostController extends AbstractController
{
    #[Route('/', name: 'post_index', methods: ['GET'])]
    #[Route('/search', name: 'post_search', methods: ['GET'])]
    public function index(PostRepository $repo, UserRepository $userRepo, Request $request): Response
    {
        $search = $request->query->get('q', '');
        $auteur = $request->query->get('auteur');
        $tri = $request->query->get('tri', 'createdAt');
        $ordre = $request->query->get('ordre', 'DESC');

        $posts = $repo->findByFilters($search, $auteur, $tri, $ordre);

        // Si AJAX, ne retourner que le partial tbody
        if ($request->isXmlHttpRequest()) {
            return $this->render('post/_table_body.html.twig', [
                'posts' => $posts
            ]);
        }

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'search' => $search,
            'auteurs' => $userRepo->findAll(),
            'auteur' => $auteur,
            'tri' => $tri,
            'ordre' => $ordre,
        ]);
    }

    #[Route('/new', name: 'post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $post = new Post();
        $post->setAuthor($this->getUser());
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Post créé avec succès.');
            return $this->redirectToRoute('post_index');
        }

        return $this->render('post/new.html.twig', [
            'form' => $form,
            'post' => $post,
        ]);
    }

    #[Route('/{id}', name: 'post_show', methods: ['GET', 'POST'])]
    public function show(Request $request, Post $post, EntityManagerInterface $em): Response
    {
        $comment = new Comment();
        $comment->setAuthor($this->getUser());
        $comment->setPost($post);

        $form = $this->createFormBuilder($comment)
            ->add('content', TextareaType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Écrire un commentaire...',
                    'rows' => 3,
                    'class' => 'form-control',
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Commentaire ajouté avec succès.');
            return $this->redirectToRoute('post_show', ['id' => $post->getId()]);
        }

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'commentForm' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Post modifié avec succès.');
            return $this->redirectToRoute('post_index');
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form,
            'post' => $post,
        ]);
    }

    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function delete(Request $request, Post $post, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Post supprimé avec succès.');
        }

        return $this->redirectToRoute('post_index');
    }
}
