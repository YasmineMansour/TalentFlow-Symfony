<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

class BrevoEmailService
{
    private const BREVO_SEND_URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $brevoApiKey,
        private readonly string $brevoFromEmail,
        private readonly string $brevoFromName,
    ) {}

    /**
     * Sends a professional candidature confirmation email to the candidate.
     * Returns true on success, false on any failure — never throws.
     */
    public function sendCandidatureConfirmation(Candidature $candidature): bool
    {
        $recipientEmail = $this->getRecipientEmail($candidature);

        if ($recipientEmail === null) {
            $this->logger->info('Candidature confirmation email skipped: recipient email missing.', [
                'candidature_id' => $candidature->getId(),
            ]);
            return false;
        }

        if (empty($this->brevoApiKey)) {
            $this->logger->warning('Candidature confirmation email skipped: BREVO_API_KEY is not configured.');
            return false;
        }

        try {
            $payload = $this->buildCandidatureConfirmationPayload($candidature, $recipientEmail);

            $response = $this->httpClient->request('POST', self::BREVO_SEND_URL, [
                'headers' => [
                    'api-key'      => $this->brevoApiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Candidature confirmation email sent successfully via Brevo.', [
                    'candidature_id' => $candidature->getId(),
                    'recipient'      => $recipientEmail,
                ]);
                return true;
            }

            $this->logger->error('Candidature confirmation email failed: unexpected Brevo API status.', [
                'candidature_id' => $candidature->getId(),
                'status_code'    => $statusCode,
            ]);
            return false;

        } catch (\Throwable $e) {
            $this->logger->error('Candidature confirmation email failed due to an exception.', [
                'candidature_id' => $candidature->getId(),
                'error'          => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resolves the recipient email: candidature direct email first, then linked user account.
     */
    public function getRecipientEmail(Candidature $candidature): ?string
    {
        $email = $candidature->getEmail();
        if ($email !== null && $email !== '') {
            return $email;
        }

        return $candidature->getCandidat()?->getEmail();
    }

    /**
     * Resolves the recipient display name for the email greeting.
     */
    public function getRecipientName(Candidature $candidature): string
    {
        $candidat = $candidature->getCandidat();
        if ($candidat !== null) {
            $prenom = $candidat->getPrenom();
            $nom    = $candidat->getNom();
            $full   = trim(($prenom ?? '') . ' ' . ($nom ?? ''));
            if ($full !== '') {
                return $full;
            }
        }

        return 'Madame, Monsieur';
    }

    /**
     * Builds the Brevo API payload for the candidature confirmation email.
     */
    private function buildCandidatureConfirmationPayload(Candidature $candidature, string $recipientEmail): array
    {
        $recipientName = $this->getRecipientName($candidature);
        $titrePoste    = $candidature->getTitrePoste();
        $entreprise    = $candidature->getEntreprise();

        $subject = 'Confirmation de réception de votre candidature';
        if ($titrePoste !== null && $titrePoste !== '') {
            $subject = 'Confirmation de réception de votre candidature – ' . $titrePoste;
        }

        $htmlContent = $this->twig->render('email/candidature_confirmation.html.twig', [
            'recipientName' => $recipientName,
            'titrePoste'    => $titrePoste,
            'entreprise'    => $entreprise,
        ]);

        return [
            'sender' => [
                'name'  => $this->brevoFromName,
                'email' => $this->brevoFromEmail,
            ],
            'to' => [
                [
                    'email' => $recipientEmail,
                    'name'  => $recipientName,
                ],
            ],
            'subject'     => $subject,
            'htmlContent' => $htmlContent,
        ];
    }
}
