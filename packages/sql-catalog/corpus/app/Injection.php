<?php

declare(strict_types=1);

namespace Corpus\Injection;

use PDO;

function run(PDO $pdo): void
{
    $pdo->query("SELECT id FROM users WHERE name = '" . $_GET['name'] . "'");
    $pdo->query('SELECT id FROM users WHERE id = ' . (int) $_GET['id']);
}
