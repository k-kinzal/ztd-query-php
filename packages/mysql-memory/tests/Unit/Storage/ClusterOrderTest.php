<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Storage\ClusterOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClusterOrder::class)]
#[Small]
final class ClusterOrderTest extends TestCase
{
    public function testRowsFollowThePrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a VARCHAR(5) NOT NULL, b INT NOT NULL, PRIMARY KEY (a, b))');
        $session->query("INSERT INTO d.t VALUES ('b', 1), ('A', 2), ('a', 1)");
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([3 => ['a', 1], 2 => ['A', 2], 1 => ['b', 1]], (new ClusterOrder())->rows($table));
    }

    public function testRowsFollowAUniqueKeyOverNotNullColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT NOT NULL, b INT, UNIQUE KEY (a))');
        $session->query('INSERT INTO d.t VALUES (3, 1), (1, 2), (2, 3)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([2 => [1, 2], 3 => [2, 3], 1 => [3, 1]], (new ClusterOrder())->rows($table));
    }

    public function testRowsKeepTheOrderOfInsertionWithoutAClusteringKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, b INT)');
        $session->query('INSERT INTO d.t VALUES (3, 1), (1, 2)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([1 => [3, 1], 2 => [1, 2]], (new ClusterOrder())->rows($table));
    }
}
