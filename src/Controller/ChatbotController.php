<?php

namespace App\Controller;

use App\Service\ChatbotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ChatbotController extends AbstractController
{
    public function __construct(
        private ChatbotService $chatbotService,
    ) {
    }

    #[Route('/chatbot/message', name: 'app_chatbot_message', methods: ['POST'])]
    public function message(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (mb_strlen($message) > 500) {
            return $this->json(['response' => 'Message trop long (max 500 caractères).', 'type' => 'error']);
        }

        $user = $this->getUser();
        $result = $this->chatbotService->handleMessage($message, $user);

        return $this->json($result);
    }
}
