<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/entreprise')]
#[IsGranted('ROLE_RH')]
class EntrepriseLogoApiController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    private const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2 Mo

    #[Route('/{id}/logo', name: 'api_entreprise_logo_upload', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function upload(
        Request $request,
        Entreprise $entreprise,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $file = $request->files->get('logo');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier envoyé. Utilisez le champ "logo".'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isValid()) {
            return $this->json(['error' => 'Le fichier est invalide : ' . $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->json(['error' => 'Le fichier ne doit pas dépasser 2 Mo.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return $this->json([
                'error' => 'Type de fichier non autorisé. Types acceptés : JPEG, PNG, GIF, WebP, SVG.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $entreprise->setLogoFile($file);

        $errors = $validator->validate($entreprise);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $messages)], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Logo uploadé avec succès.',
            'logo' => $entreprise->getLogo(),
            'logoUrl' => '/uploads/logos/' . $entreprise->getLogo(),
        ]);
    }

    #[Route('/{id}/logo', name: 'api_entreprise_logo_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(
        Entreprise $entreprise,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (!$entreprise->getLogo()) {
            return $this->json(['error' => 'Cette entreprise n\'a pas de logo.'], Response::HTTP_NOT_FOUND);
        }

        $entreprise->setLogoFile(null);
        $entreprise->setLogo(null);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Logo supprimé avec succès.',
        ]);
    }

    #[Route('/{id}/logo', name: 'api_entreprise_logo_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(Entreprise $entreprise): JsonResponse
    {
        if (!$entreprise->getLogo()) {
            return $this->json(['logo' => null, 'logoUrl' => null]);
        }

        return $this->json([
            'logo' => $entreprise->getLogo(),
            'logoUrl' => '/uploads/logos/' . $entreprise->getLogo(),
        ]);
    }
}
