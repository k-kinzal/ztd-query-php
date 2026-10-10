<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredTable::class)]
#[Small]
final class StoredTableTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUpdatesAfterSleep(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            foreach ([
                'CREATE TABLE d.t(a INT) ENGINE=MyISAM',
                'CREATE TABLE d.t LIKE d.template',
                'CREATE TABLE d.t AS SELECT 1 a',
                'CREATE TABLE d.t(a INT); BEGIN; INSERT INTO d.t VALUES(1); COMMIT',
                "CREATE TABLE d.t(a INT); XA START 'q'; INSERT INTO d.t VALUES(1); XA END 'q'; XA PREPARE 'q'; XA COMMIT 'q'",
                'CREATE TABLE d.t(a INT) ENGINE=MyISAM; INSERT INTO d.t VALUES(1)',
            ] as $sql) {
                yield $release . ': ' . $sql => [$release, $sql];
            }
        }
        yield '5.7 copy rebuild' => ['5.7.44', 'CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(1); ALTER TABLE d.t ALGORITHM=COPY'];
    }

    #[DataProvider('providerUpdatesAfterSleep')]
    public function testUpdatedUsesTheServerClockAfterSleep(string $release, string $sql): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.template(a INT) ENGINE=MyISAM; SET timestamp=1700000000; DO SLEEP(5)');
        $before = (int) floor($session->variables->clock());
        $session->query($sql);
        $after = (int) floor($session->variables->clock());
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertNotNull($table->updated);
        self::assertGreaterThanOrEqual($before, $table->updated);
        self::assertLessThanOrEqual($after, $table->updated);
    }

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
