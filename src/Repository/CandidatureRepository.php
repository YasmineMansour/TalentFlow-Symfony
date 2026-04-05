<?php

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class CandidatureRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isAvailable(): bool
    {
        try {
            return $this->connection->createSchemaManager()->tablesExist(['candidature']);
        } catch (Exception) {
            return false;
        }
    }

    public function findIdByEmail(string $email): ?int
    {
        try {
            $result = $this->connection->fetchOne(
                'SELECT id FROM candidature WHERE LOWER(email) = LOWER(?) ORDER BY id DESC LIMIT 1',
                [trim($email)]
            );
        } catch (Exception) {
            return null;
        }

        return $result !== false ? (int) $result : null;
    }

    public function findEmailById(int $id): ?string
    {
        try {
            $result = $this->connection->fetchOne(
                'SELECT email FROM candidature WHERE id = ?',
                [$id]
            );
        } catch (Exception) {
            return null;
        }

        return $result !== false ? (string) $result : null;
    }

    public function findEmailsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, email FROM candidature WHERE id IN (?)',
                [$ids],
                [ArrayParameterType::INTEGER]
            );
        } catch (Exception) {
            return [];
        }

        $emailsById = [];
        foreach ($rows as $row) {
            $emailsById[(int) $row['id']] = (string) $row['email'];
        }

        return $emailsById;
    }
}