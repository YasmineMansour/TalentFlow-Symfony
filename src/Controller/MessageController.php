<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/message')]
class MessageController extends AbstractController
{
    #[Route('/', name: 'message_index', methods: ['GET'])]
    public function index(ConversationRepository $convRepo, MessageRepository $msgRepo): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $conversations = $convRepo->findByUser($user);
        $unreadCount = $msgRepo->countUnreadForUser($user);

        return $this->render('message/index.html.twig', [
            'conversations' => $conversations,
            'activeConversation' => null,
            'messages' => [],
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/conversation/{id}', name: 'message_conversation', methods: ['GET'])]
    public function conversation(
        Conversation $conversation,
        ConversationRepository $convRepo,
        MessageRepository $msgRepo
    ): Response {
        $user = $this->getUser();
        if (!$user || !$conversation->involvesUser($user)) {
            throw $this->createAccessDeniedException();
        }

        $msgRepo->markConversationReadForUser($conversation, $user);
        $conversations = $convRepo->findByUser($user);
        $messages = $msgRepo->findByConversation($conversation, 100);
        $unreadCount = $msgRepo->countUnreadForUser($user);

        return $this->render('message/index.html.twig', [
            'conversations' => $conversations,
            'activeConversation' => $conversation,
            'messages' => $messages,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/send', name: 'message_send', methods: ['POST'])]
    public function send(
        Request $request,
        EntityManagerInterface $em,
        ConversationRepository $convRepo
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $convId = $request->request->getInt('conversation_id');
        $content = trim($request->request->get('content', ''));

        if ($content === '' || mb_strlen($content) > 2000) {
            return new JsonResponse(['error' => 'Message invalide (1-2000 caractères)'], 400);
        }

        $conversation = $convRepo->find($convId);
        if (!$conversation || !$conversation->involvesUser($user)) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'senderId' => $user->getId(),
                'senderName' => $user->getFullName(),
                'senderInitials' => mb_strtoupper(mb_substr($user->getPrenom(), 0, 1) . mb_substr($user->getNom(), 0, 1)),
                'createdAt' => $message->getCreatedAt()->format('H:i'),
                'isOwn' => true,
                'canEdit' => true,
                'type' => 'text',
            ],
        ]);
    }

    #[Route('/start/{id}', name: 'message_start', methods: ['GET'])]
    public function start(
        User $targetUser,
        ConversationRepository $convRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getId() === $targetUser->getId()) {
            $this->addFlash('warning', 'Vous ne pouvez pas vous envoyer un message.');
            return $this->redirectToRoute('message_index');
        }

        $conversation = $convRepo->findBetweenUsers($user, $targetUser);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setUserOne($user);
            $conversation->setUserTwo($targetUser);
            $em->persist($conversation);
            $em->flush();
        }

        return $this->redirectToRoute('message_conversation', ['id' => $conversation->getId()]);
    }

    #[Route('/unread-count', name: 'message_unread_count', methods: ['GET'])]
    public function unreadCount(MessageRepository $msgRepo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['count' => 0]);
        }

        return new JsonResponse(['count' => $msgRepo->countUnreadForUser($user)]);
    }

    #[Route('/fetch/{id}', name: 'message_fetch', methods: ['GET'])]
    public function fetchMessages(
        Conversation $conversation,
        MessageRepository $msgRepo
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user || !$conversation->involvesUser($user)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $msgRepo->markConversationReadForUser($conversation, $user);
        $messages = $msgRepo->findByConversation($conversation, 100);

        $now = new \DateTimeImmutable();
        $data = [];
        foreach ($messages as $msg) {
            $sender = $msg->getSender();
            $isOwn = $sender === $user;
            $ageMinutes = ($now->getTimestamp() - $msg->getCreatedAt()->getTimestamp()) / 60;
            $data[] = [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'senderId' => $sender->getId(),
                'senderName' => $sender->getFullName(),
                'senderInitials' => mb_strtoupper(mb_substr($sender->getPrenom(), 0, 1) . mb_substr($sender->getNom(), 0, 1)),
                'createdAt' => $msg->getCreatedAt()->format('H:i'),
                'isOwn' => $isOwn,
                'canEdit' => $isOwn && $ageMinutes <= 15,
                'type' => $msg->getType(),
            ];
        }

        return new JsonResponse(['messages' => $data]);
    }

    #[Route('/edit/{id}', name: 'message_edit', methods: ['POST'])]
    public function edit(
        Message $message,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user || $message->getSender() !== $user) {
            return new JsonResponse(['error' => 'Vous ne pouvez modifier que vos propres messages'], 403);
        }

        $ageMinutes = (time() - $message->getCreatedAt()->getTimestamp()) / 60;
        if ($ageMinutes > 15) {
            return new JsonResponse(['error' => 'Le délai de modification (15 min) est dépassé'], 403);
        }

        $content = trim($request->request->get('content', ''));
        if ($content === '' || mb_strlen($content) > 2000) {
            return new JsonResponse(['error' => 'Message invalide (1-2000 caractères)'], 400);
        }

        $message->setContent($content);
        $em->flush();

        return new JsonResponse(['success' => true, 'content' => $message->getContent()]);
    }

    #[Route('/delete/{id}', name: 'message_delete', methods: ['POST'])]
    public function delete(
        Message $message,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user || $message->getSender() !== $user) {
            return new JsonResponse(['error' => 'Vous ne pouvez supprimer que vos propres messages'], 403);
        }

        $ageMinutes = (time() - $message->getCreatedAt()->getTimestamp()) / 60;
        if ($ageMinutes > 15) {
            return new JsonResponse(['error' => 'Le délai de suppression (15 min) est dépassé'], 403);
        }

        $em->remove($message);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
