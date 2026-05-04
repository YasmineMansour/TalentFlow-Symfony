<?php

namespace App\EventListener;

use App\Entity\Offre;
use App\Service\OffrePdfMailerService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
class OffreCreatedListener
{
    public function __construct(
        private readonly OffrePdfMailerService $offrePdfMailerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Only process Offre entities
        if (!$entity instanceof Offre) {
            return;
        }

        try {
            // Send email with PDF to company
            $this->offrePdfMailerService->sendOffreEmail($entity);
            
            $this->logger->info('Email with PDF sent successfully for offre', [
                'offre_id' => $entity->getId(),
                'offre_titre' => $entity->getTitre(),
                'entreprise_email' => $entity->getEntreprise()?->getEmail(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the creation - emails are non-blocking
            $this->logger->error('Failed to send offre email with PDF', [
                'offre_id' => $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
