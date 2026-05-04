<?php

namespace App\Service;

use App\Entity\Offre;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;

class OffrePdfMailerService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly string $projectDir,
    ) {
    }

    public function generatePdf(Offre $offre): string
    {
        $html = $this->twig->render('offre/pdf.html.twig', [
            'offre' => $offre,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot($this->projectDir . '/public');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function sendOffreEmail(Offre $offre): void
    {
        $entreprise = $offre->getEntreprise();
        if (!$entreprise || !$entreprise->getEmail()) {
            return;
        }

        $pdfContent = $this->generatePdf($offre);
        $filename = 'offre-' . $offre->getId() . '-' . date('Ymd') . '.pdf';

        $email = (new TemplatedEmail())
            ->from(new Address('nouralouini004@gmail.com', 'TalentFlow'))
            ->to(new Address($entreprise->getEmail(), $entreprise->getNom()))
            ->subject('📄 Nouvelle offre publiée — ' . $offre->getTitre())
            ->htmlTemplate('email/offre_published.html.twig')
            ->context([
                'offre' => $offre,
                'entreprise' => $entreprise,
            ])
            ->attach($pdfContent, $filename, 'application/pdf');

        $this->mailer->send($email);
    }
}
