<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Entity\Vote;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\PostRepository;
use App\Repository\VoteRepository;
use App\Service\ContentValidator;
use App\Service\LanguageDetector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/post')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly LanguageDetector $langDetector,
    ) {}

    #[Route('/', name: 'post_index', methods: ['GET'])]
    public function index(PostRepository $repo, EntityManagerInterface $em, VoteRepository $voteRepo): Response
    {
        $posts = $repo->findAllOrderedByDate();
        $userVotes = [];
        $currentUser = $this->getUser();
        if ($currentUser) {
            foreach ($posts as $post) {
                $vote = $voteRepo->findByUserAndPost($currentUser, $post);
                if ($vote) {
                    $userVotes[$post->getId()] = $vote->getType();
                }
            }
        }

        // Stats for right sidebar
        $userRepo = $em->getRepository(User::class);
        $commentRepo = $em->getRepository(\App\Entity\Comment::class);
        $totalUsers = $userRepo->count([]);
        $totalPosts = count($posts);
        $totalComments = $commentRepo->count([]);
        $totalVotes = $voteRepo->count([]);

        // Posts per day (last 7 days)
        $postsPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = new \DateTimeImmutable("-{$i} days midnight");
            $dayEnd = $dayStart->modify('+1 day');
            $count = 0;
            foreach ($posts as $p) {
                if ($p->getCreatedAt() >= $dayStart && $p->getCreatedAt() < $dayEnd) {
                    $count++;
                }
            }
            $postsPerDay[] = [
                'label' => $dayStart->format('D'),
                'count' => $count,
            ];
        }

        // Top 5 most active authors
        $authorStats = [];
        foreach ($posts as $p) {
            $name = $p->getAuthorName();
            $authorStats[$name] = ($authorStats[$name] ?? 0) + 1;
        }
        arsort($authorStats);
        $topAuthors = array_slice($authorStats, 0, 5, true);

        // Detect language per post
        $postLangs = [];
        foreach ($posts as $p) {
            $postLangs[$p->getId()] = $this->langDetector->detect($p->getTitle() . ' ' . ($p->getContent() ?? ''));
        }

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'users' => $em->getRepository(User::class)->findAll(),
            'userVotes' => $userVotes,
            'postLangs' => $postLangs,
            'totalUsers' => $totalUsers,
            'totalPosts' => $totalPosts,
            'totalComments' => $totalComments,
            'totalVotes' => $totalVotes,
            'postsPerDay' => $postsPerDay,
            'topAuthors' => $topAuthors,
        ]);
    }

    #[Route('/search', name: 'post_search', methods: ['GET'])]
    public function search(Request $request, PostRepository $repo, CsrfTokenManagerInterface $csrf, VoteRepository $voteRepo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        $sort = $request->query->get('sort', 'recent');
        $author = trim($request->query->get('author', ''));

        $posts = $repo->searchAndFilter($q, $sort, $author);

        $data = [];
        foreach ($posts as $post) {
            $data[] = $this->serializePost($post, $csrf, $voteRepo);
        }

        return $this->json($data);
    }

    #[Route('/create-ajax', name: 'post_create_ajax', methods: ['POST'])]
    public function createAjax(Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf, VoteRepository $voteRepo, ContentValidator $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $imagePath = trim($data['imagePath'] ?? '');
        $audioPath = trim($data['audioPath'] ?? '');

        // Admin can choose author; others default to current user
        if ($this->isGranted('ROLE_ADMIN')) {
            $authorId = (int) ($data['author'] ?? 0);
            $author = $em->getRepository(User::class)->find($authorId);
        } else {
            $author = $this->getUser();
        }

        $errors = $validator->validatePostTitle($title);
        $errors = array_merge($errors, $validator->validatePostContent($content, !empty($imagePath)));
        if (!$author) {
            $errors[] = 'Veuillez sélectionner un auteur valide.';
        }

        if ($errors) {
            return $this->json(['success' => false, 'errors' => $errors], 422);
        }

        $post = new Post();
        $post->setTitle($title);
        $post->setContent($content ?: null);
        $post->setAuthor($author);
        if ($imagePath) { $post->setImagePath($imagePath); }
        if ($audioPath) { $post->setAudioPath($audioPath); }
        $em->persist($post);
        $em->flush();

        return $this->json([
            'success' => true,
            'post' => $this->serializePost($post, $csrf, $voteRepo),
        ]);
    }

    #[Route('/{id}/detail', name: 'post_detail_ajax', methods: ['GET'])]
    public function detailAjax(Post $post, CsrfTokenManagerInterface $csrf, VoteRepository $voteRepo): JsonResponse
    {
        $currentUser = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $canViewOriginal = $this->canViewOriginalPost($post);

        if ($post->isHidden() && !$canViewOriginal) {
            return $this->json([
                'post' => $this->serializePost($post, $csrf, $voteRepo),
                'comments' => [],
            ]);
        }

        $comments = [];
        foreach ($post->getComments() as $comment) {
            $diff = time() - $comment->getCreatedAt()->getTimestamp();
            if ($diff < 60) {
                $t = "à l'instant";
            } elseif ($diff < 3600) {
                $t = 'il y a ' . intdiv($diff, 60) . ' min';
            } elseif ($diff < 86400) {
                $t = 'il y a ' . intdiv($diff, 3600) . ' h';
            } elseif ($diff < 604800) {
                $t = 'il y a ' . intdiv($diff, 86400) . ' j';
            } else {
                $t = $comment->getCreatedAt()->format('d/m/Y');
            }
            $canManage = $isAdmin || ($currentUser && $comment->getAuthor() === $currentUser);
            $comments[] = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'authorName' => $comment->getAuthorName(),
                'authorInitial' => mb_strtoupper(mb_substr($comment->getAuthorName(), 0, 1)),
                'timeLabel' => $t,
                'canManage' => $canManage,
                'deleteToken' => $canManage ? $csrf->getToken('delete_comment' . $comment->getId())->getValue() : '',
            ];
        }

        return $this->json([
            'post' => $this->serializePost($post, $csrf, $voteRepo),
            'comments' => $comments,
        ]);
    }

    #[Route('/edit-ajax/{id}', name: 'post_edit_ajax', methods: ['POST'])]
    public function editAjax(Post $post, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf, VoteRepository $voteRepo, ContentValidator $validator): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN') && $post->getAuthor() !== $this->getUser()) {
            return $this->json(['success' => false, 'errors' => ['Vous ne pouvez modifier que vos propres posts.']], 403);
        }

        $data = json_decode($request->getContent(), true);
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $imagePath = $data['imagePath'] ?? null;
        $audioPath = $data['audioPath'] ?? null;

        $futureImage = $imagePath !== null ? trim($imagePath) : $post->getImagePath();
        $errors = $validator->validatePostTitle($title);
        $errors = array_merge($errors, $validator->validatePostContent($content, !empty($futureImage)));
        if ($errors) {
            return $this->json(['success' => false, 'errors' => $errors], 422);
        }

        $post->setTitle($title);
        $post->setContent($content ?: null);
        if ($imagePath !== null) { $post->setImagePath($imagePath ?: null); }
        if ($audioPath !== null) { $post->setAudioPath($audioPath ?: null); }
        $em->flush();

        return $this->json([
            'success' => true,
            'post' => $this->serializePost($post, $csrf, $voteRepo),
        ]);
    }

    #[Route('/{id}/comment-ajax', name: 'post_comment_ajax', methods: ['POST'])]
    public function commentAjax(Post $post, Request $request, EntityManagerInterface $em, CsrfTokenManagerInterface $csrf, ContentValidator $validator): JsonResponse
    {
        if ($post->isHidden() && !$this->canViewOriginalPost($post)) {
            return $this->json(['success' => false, 'errors' => ['Ce post a été retiré et ne peut plus recevoir de commentaires.']], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        // Admin can choose author; others default to current user
        if ($this->isGranted('ROLE_ADMIN')) {
            $authorId = (int) ($data['author'] ?? 0);
            $author = $em->getRepository(User::class)->find($authorId);
        } else {
            $author = $this->getUser();
        }

        $errors = $validator->validateComment($content);
        if (!$author) {
            $errors[] = 'Veuillez sélectionner un auteur valide.';
        }

        if ($errors) {
            return $this->json(['success' => false, 'errors' => $errors], 422);
        }

        $comment = new Comment();
        $comment->setContent($content);
        $comment->setAuthor($author);
        $comment->setPost($post);
        $em->persist($comment);
        $em->flush();

        return $this->json([
            'success' => true,
            'comment' => [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'authorName' => $comment->getAuthorName(),
                'authorInitial' => mb_strtoupper(mb_substr($comment->getAuthorName(), 0, 1)),
                'timeLabel' => "à l'instant",
                'canManage' => true,
                'deleteToken' => $csrf->getToken('delete_comment' . $comment->getId())->getValue(),
            ],
            'commentCount' => $post->getCommentCount(),
        ]);
    }

    #[Route('/comment/{id}/edit-ajax', name: 'comment_edit_ajax', methods: ['POST'])]
    public function commentEditAjax(Comment $comment, Request $request, EntityManagerInterface $em, ContentValidator $validator): JsonResponse
    {
        // Only admin or the comment author can edit
        if (!$this->isGranted('ROLE_ADMIN') && $comment->getAuthor() !== $this->getUser()) {
            return $this->json(['success' => false, 'errors' => ['Vous ne pouvez modifier que vos propres commentaires.']], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        $errors = $validator->validateComment($content);
        if ($errors) {
            return $this->json(['success' => false, 'errors' => $errors], 422);
        }

        $comment->setContent($content);
        $em->flush();

        return $this->json([
            'success' => true,
            'comment' => [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
            ],
        ]);
    }

    #[Route('/comment/{id}/delete-ajax', name: 'comment_delete_ajax', methods: ['POST'])]
    public function commentDeleteAjax(Comment $comment, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Only admin or the comment author can delete
        if (!$this->isGranted('ROLE_ADMIN') && $comment->getAuthor() !== $this->getUser()) {
            return $this->json(['success' => false, 'errors' => ['Vous ne pouvez supprimer que vos propres commentaires.']], 403);
        }

        $data = json_decode($request->getContent(), true);
        $token = $data['_token'] ?? '';

        if (!$this->isCsrfTokenValid('delete_comment' . $comment->getId(), $token)) {
            return $this->json(['success' => false, 'errors' => ['Token CSRF invalide.']], 403);
        }

        $post = $comment->getPost();
        $em->remove($comment);
        $em->flush();

        return $this->json([
            'success' => true,
            'commentCount' => $post->getCommentCount(),
        ]);
    }

    #[Route('/{id}/upvote', name: 'post_upvote', methods: ['POST'])]
    public function upvote(Post $post, EntityManagerInterface $em, VoteRepository $voteRepo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($post->isHidden() && !$this->canViewOriginalPost($post)) {
            return $this->json(['error' => 'Ce post a été retiré.'], 403);
        }

        $existing = $voteRepo->findByUserAndPost($user, $post);

        if ($existing && $existing->getType() === Vote::TYPE_UP) {
            // Already upvoted → remove vote
            $post->decrementUpvotes();
            $em->remove($existing);
            $em->flush();
            return $this->json(['upvotes' => $post->getUpvotes(), 'userVote' => null]);
        }

        if ($existing && $existing->getType() === Vote::TYPE_DOWN) {
            // Was downvote → switch to upvote (+2: undo -1 then +1)
            $post->incrementUpvotes();
            $post->incrementUpvotes();
            $existing->setType(Vote::TYPE_UP);
            $em->flush();
            return $this->json(['upvotes' => $post->getUpvotes(), 'userVote' => 'up']);
        }

        // No existing vote → create upvote
        $vote = new Vote();
        $vote->setUser($user)->setPost($post)->setType(Vote::TYPE_UP);
        $post->incrementUpvotes();
        $em->persist($vote);
        $em->flush();
        return $this->json(['upvotes' => $post->getUpvotes(), 'userVote' => 'up']);
    }

    #[Route('/{id}/downvote', name: 'post_downvote', methods: ['POST'])]
    public function downvote(Post $post, EntityManagerInterface $em, VoteRepository $voteRepo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if ($post->isHidden() && !$this->canViewOriginalPost($post)) {
            return $this->json(['error' => 'Ce post a été retiré.'], 403);
        }

        $existing = $voteRepo->findByUserAndPost($user, $post);

        if ($existing && $existing->getType() === Vote::TYPE_DOWN) {
            // Already downvoted → remove vote
            $post->incrementUpvotes();
            $em->remove($existing);
            $em->flush();
            return $this->json(['upvotes' => $post->getUpvotes(), 'userVote' => null]);
        }

        if ($existing && $existing->getType() === Vote::TYPE_UP) {
            // Was upvote → switch to downvote (-2: undo +1 then -1)
            $post->decrementUpvotes();
            $post->decrementUpvotes();
            $existing->setType(Vote::TYPE_DOWN);
            $em->flush();
            return $this->json(['upvotes' => $post->getUpvotes(), 'userVote' => 'down']);
        }

        // No existing vote → create downvote
        $vote = new Vote();
        $vote->setUser($user)->setPost($post)->setType(Vote::TYPE_DOWN);
        $post->decrementUpvotes();
        $em->persist($vote);
        $em->flush();
        return $this->json(['upvotes' => $post->getUpvotes(), 'userVote' => 'down']);
    }

    #[Route('/new', name: 'post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $post = new Post();
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

    #[Route('/{id}', name: 'post_show', methods: ['GET'])]
    public function show(Post $post): Response
    {
        $comment = new Comment();
        $comment->setPost($post);
        $commentForm = $this->createForm(CommentType::class, $comment);

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'commentForm' => $commentForm,
            'canViewOriginal' => $this->canViewOriginalPost($post),
        ]);
    }

    #[Route('/{id}/edit', name: 'post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post, EntityManagerInterface $em): Response
    {
        // Only admin or the post author can edit
        if (!$this->isGranted('ROLE_ADMIN') && $post->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres posts.');
        }

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
        // Only admin or the post author can delete
        if (!$this->isGranted('ROLE_ADMIN') && $post->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres posts.');
        }

        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $em->remove($post);
            $em->flush();
            $this->addFlash('success', 'Post supprimé avec succès.');
        }

        return $this->redirectToRoute('post_index');
    }

    #[Route('/{id}/summarize', name: 'post_summarize', methods: ['POST'])]
    public function summarize(Post $post, Request $request): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            throw $this->createNotFoundException();
        }

        if ($post->isHidden() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Summary unavailable right now.'], 403);
        }

        $apiKey = $_SERVER['GROQ_API_KEY'] ?? $_ENV['GROQ_API_KEY'] ?? '';
        if (empty($apiKey)) {
            return $this->json(['error' => 'Summary unavailable right now.'], 503);
        }

        $text = trim(($post->getTitle() ?? '') . "\n\n" . ($post->getContent() ?? ''));
        if (empty($text)) {
            return $this->json(['error' => 'Nothing to summarize.'], 400);
        }

        $payload = json_encode([
            'model' => 'llama-3.1-8b-instant',
            'max_tokens' => 256,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant. Summarize the following post in 1-2 concise sentences, capturing the main point. Be neutral and factual. Reply with the summary only, no preamble.'],
                ['role' => 'user', 'content' => $text],
            ],
        ]);

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return $this->json(['error' => 'Summary unavailable right now.'], 503);
        }

        $data = json_decode($response, true);
        $summary = $data['choices'][0]['message']['content'] ?? null;
        if (!$summary) {
            return $this->json(['error' => 'Summary unavailable right now.'], 503);
        }

        return $this->json(['summary' => trim($summary)]);
    }

    private function serializePost(Post $post, CsrfTokenManagerInterface $csrf, ?VoteRepository $voteRepo = null): array
    {
        $diff = time() - $post->getCreatedAt()->getTimestamp();
        if ($diff < 60) {
            $timeLabel = "à l'instant";
        } elseif ($diff < 3600) {
            $timeLabel = 'il y a ' . intdiv($diff, 60) . ' min';
        } elseif ($diff < 86400) {
            $timeLabel = 'il y a ' . intdiv($diff, 3600) . ' h';
        } elseif ($diff < 604800) {
            $timeLabel = 'il y a ' . intdiv($diff, 86400) . ' j';
        } else {
            $timeLabel = $post->getCreatedAt()->format('d/m/Y');
        }

        $currentUser = $this->getUser();
        $canManage = $this->isGranted('ROLE_ADMIN') || ($currentUser && $post->getAuthor() === $currentUser);
        $canViewOriginal = $this->canViewOriginalPost($post);
        $isHiddenForViewer = $post->isHidden() && !$canViewOriginal;

        $userVote = null;
        if (!$isHiddenForViewer && $currentUser && $voteRepo) {
            $vote = $voteRepo->findByUserAndPost($currentUser, $post);
            $userVote = $vote?->getType();
        }

        return [
            'id' => $post->getId(),
            'title' => $isHiddenForViewer ? 'Post retiré' : $post->getTitle(),
            'content' => $isHiddenForViewer ? 'This post was removed for violating community guidelines.' : $post->getContent(),
            'authorId' => $post->getAuthor()?->getId(),
            'authorName' => $post->getAuthorName(),
            'authorRole' => $post->getAuthorRole(),
            'authorInitial' => mb_strtoupper(mb_substr($post->getAuthorName(), 0, 1)),
            'upvotes' => $post->getUpvotes(),
            'commentCount' => $isHiddenForViewer ? 0 : $post->getCommentCount(),
            'timeLabel' => $timeLabel,
            'showUrl' => $this->generateUrl('post_show', ['id' => $post->getId()]),
            'editUrl' => $this->generateUrl('post_edit', ['id' => $post->getId()]),
            'deleteUrl' => $this->generateUrl('post_delete', ['id' => $post->getId()]),
            'reportUrl' => $this->generateUrl('post_report', ['id' => $post->getId()]),
            'deleteToken' => $canManage ? $csrf->getToken('delete' . $post->getId())->getValue() : '',
            'canManage' => $canManage,
            'canViewOriginal' => $canViewOriginal,
            'canReport' => $currentUser && !$this->isGranted('ROLE_ADMIN') && !$post->isHidden(),
            'hidden' => $post->isHidden(),
            'hiddenMessage' => 'This post was removed for violating community guidelines.',
            'userVote' => $userVote,
            'imagePath' => $isHiddenForViewer ? null : $post->getImagePath(),
            'audioPath' => $isHiddenForViewer ? null : $post->getAudioPath(),
            'lang' => $isHiddenForViewer ? 'fr' : $this->langDetector->detect($post->getTitle() . ' ' . ($post->getContent() ?? '')),
        ];
    }

    private function canViewOriginalPost(Post $post): bool
    {
        return !$post->isHidden() || $this->isGranted('ROLE_ADMIN');
    }
}
