<?php

declare(strict_types=1);

namespace Corpus\Inheritance;

use PDO;

abstract class Table
{
    public function __construct(protected PDO $pdo)
    {
    }

    abstract public function table(): string;

    public function countRows(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $this->table())->fetchColumn();
    }
}

final class Users extends Table
{
    public function table(): string
    {
        return 'users';
    }
}

final class Orders extends Table
{
    public function table(): string
    {
        return 'orders';
    }
}

function run(PDO $pdo): void
{
    (new Users($pdo))->countRows();
    (new Orders($pdo))->countRows();
}
