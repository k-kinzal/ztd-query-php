<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\HandlerCursor;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;

#[CoversClass(HandlerCursor::class)]
#[Small]
final class HandlerCursorTest extends TestCase
{
    public function testOrderedAnswersTheRowsInTheOrderOfAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b VARCHAR(5), KEY ib (b)); INSERT INTO t VALUES (1,'b'),(2,'a'),(3,NULL)");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([3, 2, 1], (new HandlerCursor())->ordered($table->data->rows, $table->definition->keys[1], $table));
    }

    public function testCompareComparesTheLeadingColumnsOfAnIndex(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, KEY i (a, b))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([0, 1, -1], [(new HandlerCursor())->compare([1, 5], $table->definition->keys[0], $table, [1]), (new HandlerCursor())->compare([1, 5], $table->definition->keys[0], $table, [1, 4]), (new HandlerCursor())->compare([1, 5], $table->definition->keys[0], $table, [2])]);
    }

    public function testStartStartsAReadOfAnotherOrderAfresh(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT)');
        $statement = $session->analyze('HANDLER h READ NEXT')->statement;
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertInstanceOf(HandlerScan::class, $statement);

        self::assertSame([0, 1], (new HandlerCursor())->start($statement, [1, 2], [], null, $table, [], null));
    }

    public function testStartSeeksTheFirstRowPastTheValues(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, KEY ia (a)); INSERT INTO t VALUES (1),(2),(3)');
        $statement = $session->analyze('HANDLER h READ ia > (1)')->statement;
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        self::assertInstanceOf(HandlerIndexSeek::class, $statement);

        self::assertSame([1, 1], (new HandlerCursor())->start($statement, [1, 2, 3], $table->data->rows, $table->definition->keys[0], $table, [1], null));
    }

    public function testSeekReadsForwardFromTheFirstRowOrBackwardFromTheLastRowThatSatisfiesTheComparison(): void
    {
        self::assertSame(
            [[1, 1], [2, 1], [1, -1], [0, -1], [3, 1], [-1, -1]],
            [
                (new HandlerCursor())->seek(KeyComparison::Equal, [-1, 0, 1]),
                (new HandlerCursor())->seek(KeyComparison::Greater, [-1, 0, 1]),
                (new HandlerCursor())->seek(KeyComparison::LessOrEqual, [-1, 0, 1]),
                (new HandlerCursor())->seek(KeyComparison::Less, [-1, 0, 1]),
                (new HandlerCursor())->seek(KeyComparison::GreaterOrEqual, [-1, -1, -1]),
                (new HandlerCursor())->seek(KeyComparison::Less, [0, 1]),
            ],
        );
    }

    public function testWalkSkipsTheOffsetAndStopsAfterTheCount(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, KEY ia (a)); INSERT INTO t VALUES (1),(2),(2),(3)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);

        self::assertSame(
            [[[[2], [3]], 3], [[[2], [2]], 2], [[], null], [[[1], [2], [2], [3]], null]],
            [
                (new HandlerCursor())->walk($table->data->rows, [1, 2, 3, 4], 0, 1, 2, 2, null, $table, [], null, $context),
                (new HandlerCursor())->walk($table->data->rows, [1, 2, 3, 4], 1, 1, 5, 0, $table->definition->keys[0], $table, [2], null, $context),
                (new HandlerCursor())->walk($table->data->rows, [1, 2, 3, 4], 0, 1, 0, 0, null, $table, [], null, $context),
                (new HandlerCursor())->walk($table->data->rows, [1, 2, 3, 4], 0, 1, null, 0, null, $table, [], null, $context),
            ],
        );
    }
}
