<?php

declare(strict_types=1);

namespace Corpus\NamedBinds;

use PDO;

function run(PDO $pdo): void
{
    $statement = $pdo->prepare('UPDATE users SET name = :name WHERE id = :id');
    $statement->bindValue(':name', 'Grace');
    $statement->bindValue(':id', 1, PDO::PARAM_INT);
    $statement->execute();

    $insert = $pdo->prepare('INSERT INTO orders (id, user_id, total) VALUES (:id, :user_id, :total)');
    $insert->execute([':id' => 1, ':user_id' => 1, ':total' => 9.5]);
}
