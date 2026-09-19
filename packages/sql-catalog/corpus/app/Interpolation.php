<?php

declare(strict_types=1);

namespace Corpus\Interpolation;

use PDO;

const ORDER_BY = 'created_at DESC';

function run(PDO $pdo): void
{
    $columns = ['id', 'name'];
    $template = <<<'SQL'
        SELECT %s FROM users WHERE status = ? ORDER BY %s
        SQL;

    $statement = $pdo->prepare(sprintf($template, implode(', ', $columns), ORDER_BY));
    $statement->execute(['active']);

    $table = 'orders';
    $pdo->query("SELECT COUNT(*) FROM {$table}");
}
