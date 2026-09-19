<?php

declare(strict_types=1);

namespace Corpus\Repository;

use PDO;

final class UserRepository
{
    private const TABLE = 'users';

    private string $order = 'name';

    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query($this->selectSql())->fetchAll();
    }

    public function selectSql(): string
    {
        return 'SELECT id, name FROM ' . self::TABLE . ' ORDER BY ' . $this->order;
    }

    public function byId(int $id): array
    {
        $statement = $this->pdo->prepare(sprintf('SELECT id, name FROM %s WHERE id = ?', self::TABLE));
        $statement->execute([$id]);

        return $statement->fetchAll();
    }

    public function totalsByUser(): array
    {
        return $this->pdo->query(
            'SELECT user_id, SUM(total) AS total FROM orders GROUP BY user_id',
        )->fetchAll();
    }
}

function run(PDO $pdo): void
{
    $repository = new UserRepository($pdo);
    $repository->all();
    $repository->byId(1);
    $repository->totalsByUser();
}
