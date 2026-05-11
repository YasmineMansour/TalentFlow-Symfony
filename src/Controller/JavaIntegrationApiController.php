<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST API for Java ↔ Symfony integration.
 *
 * The Java desktop application (JavaFX) connects to the same MySQL database
 * (talent_flow_db). This API allows the Java app to manage users and forum
 * posts through Symfony's business layer.
 *
 * Authentication: API Key via X-API-KEY header.
 * Set the key in .env: JAVA_INTEGRATION_API_KEY=talentflow-java-api-key-2026
 *
 * Base URL: /api/integration/
 */
#[Route('/api/integration')]
class JavaIntegrationApiController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PostRepository $postRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {}

    // =========================================================================
    // AUTH HELPER
    // =========================================================================

    private function checkApiKey(Request $request): ?JsonResponse
    {
        $apiKey = $request->headers->get('X-API-KEY');
        $expectedKey = $_ENV['JAVA_INTEGRATION_API_KEY'] ?? getenv('JAVA_INTEGRATION_API_KEY');

        if (!$expectedKey) {
            return new JsonResponse(['error' => 'Integration API not configured.'], 503);
        }

        if (!$apiKey || !hash_equals($expectedKey, $apiKey)) {
            return new JsonResponse(['error' => 'Unauthorized. Invalid or missing X-API-KEY header.'], 401);
        }

        return null;
    }

    // =========================================================================
    // USERS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/integration/users
     */
    #[Route('/users', name: 'api_integration_users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $users = $this->userRepository->findAll();
        $data = array_map(fn(User $u) => $this->serializeUser($u), $users);

        return new JsonResponse(['users' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/integration/users/{id}
     */
    #[Route('/users/{id}', name: 'api_integration_users_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserById(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => "Utilisateur #$id introuvable."], 404);
        }

        return new JsonResponse($this->serializeUser($user));
    }

    /**
     * POST /api/integration/users
     * Body: { "nom", "prenom", "email", "password", "role"?, "telephone"? }
     */
    #[Route('/users', name: 'api_integration_users_create', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['nom'], $data['prenom'], $data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Champs requis manquants: nom, prenom, email, password.'], 400);
        }

        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['error' => "Un utilisateur existe déjà avec l'email: {$data['email']}."], 409);
        }

        $user = new User();
        $user->setNom($data['nom']);
        $user->setPrenom($data['prenom']);
        $user->setEmail($data['email']);
        $user->setTelephone($data['telephone'] ?? null);
        $user->setRoles([strtoupper($data['role'] ?? 'ROLE_USER')]);

        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $msgs = [];
            foreach ($errors as $e) {
                $msgs[] = $e->getPropertyPath() . ': ' . $e->getMessage();
            }
            return new JsonResponse(['errors' => $msgs], 422);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeUser($user), 201);
    }

    /**
     * PUT /api/integration/users/{id}
     */
    #[Route('/users/{id}', name: 'api_integration_users_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateUser(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => "Utilisateur #$id introuvable."], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Corps JSON invalide.'], 400);
        }

        if (isset($data['nom']))       { $user->setNom($data['nom']); }
        if (isset($data['prenom']))    { $user->setPrenom($data['prenom']); }
        if (isset($data['telephone'])) { $user->setTelephone($data['telephone']); }
        if (isset($data['role']))      { $user->setRoles([strtoupper($data['role'])]); }
        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }
        $user->setUpdatedAt(new \DateTimeImmutable());

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $msgs = [];
            foreach ($errors as $e) {
                $msgs[] = $e->getPropertyPath() . ': ' . $e->getMessage();
            }
            return new JsonResponse(['errors' => $msgs], 422);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->serializeUser($user));
    }

    /**
     * DELETE /api/integration/users/{id}
     */
    #[Route('/users/{id}', name: 'api_integration_users_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteUser(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => "Utilisateur #$id introuvable."], 404);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "Utilisateur #$id supprimé avec succès."], 200);
    }

    // =========================================================================
    // FORUM / POSTS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/integration/posts
     * Returns all posts (non-hidden) ordered by date DESC.
     */
    #[Route('/posts', name: 'api_integration_posts_list', methods: ['GET'])]
    public function listPosts(Request $request): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $posts = $this->postRepository->findBy(['hidden' => false], ['createdAt' => 'DESC']);
        $data = array_map(fn(Post $p) => $this->serializePost($p), $posts);

        return new JsonResponse(['posts' => $data, 'total' => count($data)]);
    }

    /**
     * GET /api/integration/posts/{id}
     * Returns a single post with its comments.
     */
    #[Route('/posts/{id}', name: 'api_integration_posts_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getPostById(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $post = $this->postRepository->find($id);
        if (!$post || $post->isHidden()) {
            return new JsonResponse(['error' => "Post #$id introuvable."], 404);
        }

        $comments = [];
        foreach ($post->getComments() as $comment) {
            $comments[] = $this->serializeComment($comment);
        }

        return new JsonResponse(array_merge($this->serializePost($post), ['comments' => $comments]));
    }

    /**
     * POST /api/integration/posts
     * Body: { "title", "content"?, "authorId" }
     */
    #[Route('/posts', name: 'api_integration_posts_create', methods: ['POST'])]
    public function createPost(Request $request): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['title']) || empty($data['authorId'])) {
            return new JsonResponse(['error' => 'Champs requis manquants: title, authorId.'], 400);
        }

        $author = $this->userRepository->find((int) $data['authorId']);
        if (!$author) {
            return new JsonResponse(['error' => "Auteur #{$data['authorId']} introuvable."], 404);
        }

        $post = new Post();
        $post->setTitle(trim($data['title']));
        $post->setContent($data['content'] ?? null);
        $post->setAuthor($author);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return new JsonResponse($this->serializePost($post), 201);
    }

    /**
     * PUT /api/integration/posts/{id}
     * Body: { "title"?, "content"? }
     */
    #[Route('/posts/{id}', name: 'api_integration_posts_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updatePost(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $post = $this->postRepository->find($id);
        if (!$post || $post->isHidden()) {
            return new JsonResponse(['error' => "Post #$id introuvable."], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Corps JSON invalide.'], 400);
        }

        if (!empty($data['title']))   { $post->setTitle(trim($data['title'])); }
        if (isset($data['content']))  { $post->setContent($data['content'] ?: null); }

        $this->entityManager->flush();

        return new JsonResponse($this->serializePost($post));
    }

    /**
     * DELETE /api/integration/posts/{id}
     */
    #[Route('/posts/{id}', name: 'api_integration_posts_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deletePost(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => "Post #$id introuvable."], 404);
        }

        $this->entityManager->remove($post);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "Post #$id supprimé avec succès."], 200);
    }

    // =========================================================================
    // COMMENTS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/integration/posts/{id}/comments
     */
    #[Route('/posts/{id}/comments', name: 'api_integration_post_comments_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listComments(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $post = $this->postRepository->find($id);
        if (!$post) {
            return new JsonResponse(['error' => "Post #$id introuvable."], 404);
        }

        $comments = [];
        foreach ($post->getComments() as $comment) {
            $comments[] = $this->serializeComment($comment);
        }

        return new JsonResponse(['comments' => $comments, 'total' => count($comments)]);
    }

    /**
     * POST /api/integration/posts/{id}/comments
     * Body: { "content", "authorId" }
     */
    #[Route('/posts/{id}/comments', name: 'api_integration_post_comments_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createComment(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $post = $this->postRepository->find($id);
        if (!$post || $post->isHidden()) {
            return new JsonResponse(['error' => "Post #$id introuvable."], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['content']) || empty($data['authorId'])) {
            return new JsonResponse(['error' => 'Champs requis manquants: content, authorId.'], 400);
        }

        $author = $this->userRepository->find((int) $data['authorId']);
        if (!$author) {
            return new JsonResponse(['error' => "Auteur #{$data['authorId']} introuvable."], 404);
        }

        $comment = new Comment();
        $comment->setContent(trim($data['content']));
        $comment->setAuthor($author);
        $comment->setPost($post);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return new JsonResponse($this->serializeComment($comment), 201);
    }

    /**
     * DELETE /api/integration/comments/{id}
     */
    #[Route('/comments/{id}', name: 'api_integration_comments_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteComment(Request $request, int $id): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $commentRepo = $this->entityManager->getRepository(Comment::class);
        $comment = $commentRepo->find($id);
        if (!$comment) {
            return new JsonResponse(['error' => "Commentaire #$id introuvable."], 404);
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "Commentaire #$id supprimé avec succès."], 200);
    }

    // =========================================================================
    // STATS ENDPOINT
    // =========================================================================

    /**
     * GET /api/integration/stats
     */
    #[Route('/stats', name: 'api_integration_stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        if ($error = $this->checkApiKey($request)) {
            return $error;
        }

        $totalUsers = $this->userRepository->count([]);
        $blockedUsers = $this->userRepository->count(['blocked' => true]);
        $totalPosts = $this->postRepository->count(['hidden' => false]);

        return new JsonResponse([
            'total_users'   => $totalUsers,
            'blocked_users' => $blockedUsers,
            'total_posts'   => $totalPosts,
            'timestamp'     => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // SERIALIZERS
    // =========================================================================

    private function serializeUser(User $user): array
    {
        $roles = $user->getRoles();
        $primaryRole = 'ROLE_USER';
        foreach ($roles as $r) {
            if ($r !== 'ROLE_USER') {
                $primaryRole = $r;
                break;
            }
        }

        return [
            'id'        => $user->getId(),
            'nom'       => $user->getNom(),
            'prenom'    => $user->getPrenom(),
            'email'     => $user->getEmail(),
            'role'      => $primaryRole,
            'roles'     => $roles,
            'telephone' => $user->getTelephone(),
            'isActive'  => $user->isActive(),
            'blocked'   => $user->isBlocked(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializePost(Post $post): array
    {
        $author = $post->getAuthor();

        return [
            'id'           => $post->getId(),
            'title'        => $post->getTitle(),
            'content'      => $post->getContent(),
            'authorId'     => $author?->getId(),
            'authorNom'    => $author?->getNom(),
            'authorPrenom' => $author?->getPrenom(),
            'authorEmail'  => $author?->getEmail(),
            'upvotes'      => $post->getUpvotes(),
            'commentCount' => $post->getCommentCount(),
            'imagePath'    => $post->getImagePath(),
            'createdAt'    => $post->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializeComment(Comment $comment): array
    {
        $author = $comment->getAuthor();

        return [
            'id'           => $comment->getId(),
            'content'      => $comment->getContent(),
            'authorId'     => $author?->getId(),
            'authorNom'    => $author?->getNom(),
            'authorPrenom' => $author?->getPrenom(),
            'authorName'   => $comment->getAuthorName(),
            'postId'       => $comment->getPost()?->getId(),
            'createdAt'    => $comment->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
