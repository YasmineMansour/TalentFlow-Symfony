<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

final class AccessDeniedExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedHttpException && !$exception instanceof AccessDeniedException) {
            return;
        }

        if ($this->security->getUser() === null) {
            return;
        }

        $request = $event->getRequest();
        if ($request->isXmlHttpRequest()) {
            return;
        }

        $acceptHeader = (string) $request->headers->get('Accept', '');
        if ($acceptHeader !== '' && !str_contains($acceptHeader, 'text/html')) {
            return;
        }

        $message = trim((string) $exception->getMessage());
        if ($message === '') {
            $message = 'Vous n\'avez pas les permissions necessaires pour acceder a cette ressource.';
        }

        $content = $this->twig->render('errors/403.html.twig', [
            'errorMessage' => $message,
        ]);

        $event->setResponse(new Response($content, Response::HTTP_FORBIDDEN));
    }
}
