<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BrevoSmsService
{
    private const BREVO_SMS_URL = 'https://api.brevo.com/v3/transactionalSMS/sms';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $brevoApiKey,
        private readonly string $brevoSmsSender,
        private readonly bool $brevoSmsEnabled,
    ) {}

    /**
     * Sends a professional blocking-candidature reminder SMS to the candidate.
     * Returns true on success, false on any failure — never throws.
     */
    public function sendBlockingCandidatureReminder(Candidature $candidature): bool
    {
        if (!$this->brevoSmsEnabled) {
            $this->logger->info('Blocking candidature SMS skipped: SMS sending is disabled (BREVO_SMS_ENABLED=false).', [
                'candidature_id' => $candidature->getId(),
            ]);
            return false;
        }

        if (empty($this->brevoApiKey)) {
            $this->logger->warning('Blocking candidature SMS skipped: BREVO_API_KEY is not configured.');
            return false;
        }

        $recipientPhone = $this->normalizePhone($candidature->getTelephone());

        if ($recipientPhone === null) {
            $this->logger->info('Blocking candidature SMS skipped: recipient phone missing or invalid.', [
                'candidature_id' => $candidature->getId(),
            ]);
            return false;
        }

        try {
            $content = $this->buildSmsContent($candidature);

            $response = $this->httpClient->request('POST', self::BREVO_SMS_URL, [
                'headers' => [
                    'api-key'      => $this->brevoApiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => [
                    'sender'    => $this->brevoSmsSender,
                    'recipient' => $recipientPhone,
                    'content'   => $content,
                    'type'      => 'transactional',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Blocking candidature reminder SMS sent successfully via Brevo.', [
                    'candidature_id' => $candidature->getId(),
                    'recipient'      => $recipientPhone,
                ]);
                return true;
            }

            $this->logger->error('Blocking candidature SMS failed: unexpected Brevo API status.', [
                'candidature_id' => $candidature->getId(),
                'status_code'    => $statusCode,
            ]);
            return false;

        } catch (\Throwable $e) {
            $this->logger->error('Blocking candidature SMS failed due to an exception.', [
                'candidature_id' => $candidature->getId(),
                'error'          => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Builds the professional SMS content for a blocking candidature.
     * Kept short and action-oriented without exposing internal scores.
     */
    private function buildSmsContent(Candidature $candidature): string
    {
        $titrePoste = $candidature->getTitrePoste();

        if ($titrePoste !== null && $titrePoste !== '') {
            return sprintf(
                'TalentFlow : votre candidature pour le poste "%s" est incomplète. '
                . 'Merci de compléter votre dossier (CV, coordonnées, compétences) '
                . 'afin qu\'il puisse être examiné par notre équipe RH.',
                $titrePoste
            );
        }

        return 'TalentFlow : votre candidature est incomplète. '
            . 'Merci de la compléter (CV, coordonnées, compétences) '
            . 'afin qu\'elle puisse être étudiée par notre équipe RH.';
    }

    /**
     * Normalises a Tunisian phone number to international E.164 format (+216XXXXXXXX).
     * Returns null if the number cannot be normalised.
     */
    private function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $cleaned = preg_replace('/\s+/', '', $raw);

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        if (str_starts_with($cleaned, '+216')) {
            $local = substr($cleaned, 4);
        } elseif (str_starts_with($cleaned, '00216')) {
            $local = substr($cleaned, 5);
        } else {
            $local = $cleaned;
        }

        // Validate: must be 8 digits starting with 2, 4, 5, or 9
        if (!preg_match('/^[2459][0-9]{7}$/', $local)) {
            return null;
        }

        return '+216' . $local;
    }
}
