<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Instance;
use MySqlMemory\Session\Locator;
use MySqlMemory\Session\Problem\WriteOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;

#[CoversClass(WriteOrder::class)]
#[Small]
final class WriteOrderTest extends TestCase
{
    public function testLocateTellsWhetherTheStatementWritesRows(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = new Locator();

        self::assertSame([true, false], [(new WriteOrder($locator))->locate($session->analyze('DELETE FROM t WHERE a = 1')->statement), (new WriteOrder($locator))->locate($session->analyze('SELECT a FROM t')->statement)]);
        self::assertSame([['where clause', [2, 1]]], array_values($locator->places));
    }

    public function testUpdateLocatesWhereThenTheAssignmentsThenOrderBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('UPDATE t SET a = b WHERE c = 1 ORDER BY a')->statement;
        self::assertInstanceOf(Update::class, $statement);
        $locator = new Locator();

        (new WriteOrder($locator))->update($statement);

        self::assertSame([['where clause', [1, 1]], ['field list', [2, 0, 0]], ['field list', [2, 1, 0]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }

    public function testValuesLocatesTheFirstRowBeforeTheColumnsInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $statement = $session->analyze('INSERT INTO t (a) VALUES (b), (b)')->statement;
        self::assertInstanceOf(InsertRows::class, $statement);
        $locator = new Locator(false, GrammarRelease::MySql5651);

        (new WriteOrder($locator))->values($statement);

        self::assertSame([[0, 0, 0], [1, 5, 0, 0], [2, 0, 0, 0]], array_column(array_values($locator->places), 1));
    }

    public function testQueryLocatesTheColumnsOfOnDuplicateKeyUpdateBeforeTheQuery(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('INSERT INTO t (a) SELECT b FROM t ON DUPLICATE KEY UPDATE c = 1')->statement;
        self::assertInstanceOf(InsertQuery::class, $statement);
        $locator = new Locator();

        (new WriteOrder($locator))->query($statement);

        self::assertSame(['a', 'c', 'b'], array_map(static fn ($node): string => $node instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse ? $node->name->value : '', $locator->nodes()));
    }

    public function testDeleteLocatesWhereThenOrderBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, c INT)');
        $statement = $session->analyze('DELETE FROM t WHERE c = 1 ORDER BY a')->statement;
        self::assertInstanceOf(Delete::class, $statement);
        $locator = new Locator();

        (new WriteOrder($locator))->delete($statement);

        self::assertSame([['where clause', [2, 1]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }
}
