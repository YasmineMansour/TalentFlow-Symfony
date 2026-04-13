<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/call')]
class CallController extends AbstractController
{
    private function callFile(int $convId): string
    {
        return sys_get_temp_dir() . '/tf_call_' . $convId . '.json';
    }

    #[Route('/room/{id}', name: 'call_room', methods: ['GET'])]
    public function room(Conversation $conversation): Response
    {
        $user = $this->getUser();
        if (!$user || !$conversation->involvesUser($user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('call/room.html.twig', [
            'conversation' => $conversation,
            'otherUser' => $conversation->getOtherUser($user),
            'myUserId' => $user->getId(),
            'myName' => $user->getFullName(),
        ]);
    }

    /**
     * Register my PeerJS peer ID so the other side can discover it.
     */
    #[Route('/signal/{id}', name: 'call_signal', methods: ['POST'])]
    public function signal(Conversation $conversation, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$conversation->involvesUser($user)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $peerId = $request->request->get('peerId', '');
        if (!$peerId) {
            $body = json_decode($request->getContent(), true);
            $peerId = $body['peerId'] ?? '';
        }

        $file = $this->callFile($conversation->getId());
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
        }

        $userId = $user->getId();
        $data['peers'][$userId] = [
            'peerId' => $peerId,
            'time' => time(),
        ];

        file_put_contents($file, json_encode($data));

        return new JsonResponse(['success' => true]);
    }

    /**
     * Poll for the other user's peer ID.
     */
    #[Route('/check/{id}', name: 'call_check', methods: ['GET'])]
    public function check(Conversation $conversation): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$conversation->involvesUser($user)) {
            return new JsonResponse(['active' => false]);
        }

        $file = $this->callFile($conversation->getId());
        if (!file_exists($file)) {
            return new JsonResponse(['active' => false, 'remotePeerId' => null]);
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        $peers = $data['peers'] ?? [];

        // Find the OTHER user's peer ID
        $myId = $user->getId();
        $remotePeerId = null;
        $active = false;

        foreach ($peers as $uid => $info) {
            if ((int)$uid !== $myId && ($info['time'] ?? 0) > time() - 60) {
                $remotePeerId = $info['peerId'] ?? null;
                $active = true;
                break;
            }
        }

        return new JsonResponse([
            'active' => $active,
            'remotePeerId' => $remotePeerId,
        ]);
    }

    #[Route('/end/{id}', name: 'call_end', methods: ['POST'])]
    public function end(Conversation $conversation, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user || !$conversation->involvesUser($user)) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        // Read call status from the request
        $body = json_decode($request->getContent(), true) ?: [];
        $status = $body['status'] ?? 'ended';       // ended | missed
        $duration = (int)($body['duration'] ?? 0);   // seconds
        $mode = $body['mode'] ?? 'video';             // video | audio

        // Only log once per call (check temp file still exists)
        $file = $this->callFile($conversation->getId());
        if (file_exists($file)) {
            @unlink($file);

            // Build call message
            $type = ($status === 'missed') ? 'call_missed' : 'call_ended';
            $content = json_encode([
                'mode' => $mode,
                'duration' => $duration,
            ]);

            $msg = new Message();
            $msg->setConversation($conversation);
            $msg->setSender($user);
            $msg->setType($type);
            $msg->setContent($content);
            $em->persist($msg);
            $em->flush();
        }

        return new JsonResponse(['success' => true]);
    }
}
