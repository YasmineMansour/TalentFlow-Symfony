<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LiveCodingSnapshotService
{
    private string $snapshotDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ) {
        $this->snapshotDir = $projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'live-coding';
    }

    public function loadCode(int $entretienId): string
    {
        $file = $this->snapshotFile($entretienId);
        if (!is_file($file)) {
            return '';
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        return $content;
    }

    public function saveCode(int $entretienId, string $code): void
    {
        if (!is_dir($this->snapshotDir)) {
            @mkdir($this->snapshotDir, 0775, true);
        }

        file_put_contents($this->snapshotFile($entretienId), $code);
    }

    private function snapshotFile(int $entretienId): string
    {
        return $this->snapshotDir . DIRECTORY_SEPARATOR . 'entretien_' . $entretienId . '.txt';
    }
}
