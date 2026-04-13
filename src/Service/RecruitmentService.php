<?php

namespace App\Service;

use App\Entity\DecisionFinale;
use App\Entity\Entretien;
use App\Repository\CandidatureRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class RecruitmentService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly CandidatureRepository $candidatureRepository,
    ) {}

    /**
     * Envoie un email de confirmation au candidat après création d'un entretien.
     */
    public function sendEntretienConfirmation(Entretien $entretien): void
    {
        $candidature = $this->candidatureRepository->find($entretien->getCandidatureId() ?? 0);
        if ($candidature === null) {
            return;
        }

        $recipientEmail = $candidature->getCandidat()?->getEmail();
        if ($recipientEmail === null) {
            return;
        }

        $html = $this->twig->render('email/entretien_confirmation.html.twig', [
            'entretien'   => $entretien,
            'candidature' => $candidature,
            'meetUrl'     => $entretien->getType() === 'EN_LIGNE' ? $entretien->getMeetUrl() : null,
        ]);

        $this->mailer->send(
            (new Email())
                ->from(new Address('nouralouini004@gmail.com', 'TalentFlow RH'))
                ->to($recipientEmail)
                ->subject('Confirmation de votre entretien – TalentFlow')
                ->html($html)
        );
    }

    /**
     * Envoie un email de résultat au candidat après enregistrement d'une décision (ACCEPTE / REFUSE).
     */
    public function sendDecisionNotification(DecisionFinale $decision): void
    {
        if ($decision->getDecision() === 'EN_ATTENTE') {
            return;
        }

        $entretien = $decision->getEntretien();
        if ($entretien === null) {
            return;
        }

        $candidature = $this->candidatureRepository->find($entretien->getCandidatureId() ?? 0);
        if ($candidature === null) {
            return;
        }

        $recipientEmail = $candidature->getCandidat()?->getEmail();
        if ($recipientEmail === null) {
            return;
        }

        $html = $this->twig->render('email/decision_notification.html.twig', [
            'decision'    => $decision,
            'entretien'   => $entretien,
            'candidature' => $candidature,
        ]);

        $subject = $decision->getDecision() === 'ACCEPTE'
            ? 'Félicitations ! Votre candidature a été retenue – TalentFlow'
            : 'Résultat de votre candidature – TalentFlow';

        $this->mailer->send(
            (new Email())
                ->from(new Address('nouralouini004@gmail.com', 'TalentFlow RH'))
                ->to($recipientEmail)
                ->subject($subject)
                ->html($html)
        );
    }
}
