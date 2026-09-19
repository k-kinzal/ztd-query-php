<?php

declare(strict_types=1);

namespace Corpus\Plain;

use PDO;

function run(PDO $pdo): void
{
    $pdo->exec("INSERT INTO users (id, name, status) VALUES (1, 'Ada', 'active')");
    $pdo->query('SELECT id, name FROM users ORDER BY id');

    $byId = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $byId->execute([1]);

    $update = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
    $update->execute(['banned', 1]);

    $delete = $pdo->prepare('DELETE FROM orders WHERE user_id = ?');
    $delete->execute([1]);
}
