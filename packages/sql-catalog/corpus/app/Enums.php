<?php

declare(strict_types=1);

namespace Corpus\Enums;

use PDO;

enum Status: string
{
    case Active = 'active';
    case Banned = 'banned';
}

function findByStatus(PDO $pdo, Status $status): array
{
    $statement = $pdo->prepare('SELECT id FROM users WHERE status = :status');
    $statement->execute([':status' => $status->value]);

    return $statement->fetchAll();
}

function countByStatus(PDO $pdo, Status $status): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM users WHERE status = ?');
    $statement->execute([$status->value]);

    return (int) $statement->fetchColumn();
}

function run(PDO $pdo): void
{
    findByStatus($pdo, Status::Active);
    findByStatus($pdo, Status::Banned);
    countByStatus($pdo, Status::Active);
}
