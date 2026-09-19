<?php

declare(strict_types=1);

namespace Corpus\DynamicFilters;

use PDO;

function search(PDO $pdo, array $filters): array
{
    $sql = 'SELECT id FROM users WHERE 1 = 1';
    $values = [];
    foreach ($filters as $column => $value) {
        $sql .= ' AND ' . $column . ' = ?';
        $values[] = $value;
    }

    $statement = $pdo->prepare($sql);
    $statement->execute($values);

    return $statement->fetchAll();
}

function run(PDO $pdo): void
{
    search($pdo, []);
    search($pdo, ['status' => 'active']);
    search($pdo, ['status' => 'banned', 'name' => 'Ada']);
}
