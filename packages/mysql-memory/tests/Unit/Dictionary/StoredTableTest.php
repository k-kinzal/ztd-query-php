<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredTable::class)]
#[Small]
final class StoredTableTest extends TestCase
{
    public function testDataHoldsTheRowsInsertedIntoTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, b VARCHAR(5))');
        $session->query("INSERT INTO d.t VALUES (1, 'x'), (2, NULL)");

        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([['a', 'b'], [1 => [1, 'x'], 2 => [2, null]]], [[$table->definition->columns[0]->name, $table->definition->columns[1]->name], $table->data->rows]);
    }

    public function testHistogramsStartEmpty(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT)');

        self::assertSame([], $session->instance->dictionary->table('d', 't')?->histograms);
    }
}
