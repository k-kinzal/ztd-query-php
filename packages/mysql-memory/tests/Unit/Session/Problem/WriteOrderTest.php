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
use SqlSemantics\Statement\Operation;

#[CoversClass(WriteOrder::class)]
#[Small]
final class WriteOrderTest extends TestCase
{
    public function testLocateTellsWhetherTheStatementWritesRows(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $locator = new Locator();

        $delete = $session->analyze('DELETE FROM t WHERE a = 1');
        $select = $session->analyze('SELECT a FROM t');
        self::assertInstanceOf(Operation::class, $delete);
        self::assertInstanceOf(Operation::class, $select);
        self::assertSame([true, false], [(new WriteOrder($locator))->locate($delete->statement), (new WriteOrder($locator))->locate($select->statement)]);
        self::assertSame([['where clause', [2, 1]]], array_values($locator->places));
    }

    public function testLocateKeepsTheJsonDocumentClauseOfAMultipleDelete(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $operation = $session->analyze("DELETE t FROM t JOIN JSON_TABLE(missing, '$' COLUMNS (n FOR ORDINALITY)) j");
        self::assertInstanceOf(Operation::class, $operation);
        $statement = $operation->statement;
        $locator = new Locator();
        self::assertTrue((new WriteOrder($locator))->locate($statement));
        self::assertSame(['a table function argument'], array_column(array_values($locator->places), 0));
    }

    public function testUpdateLocatesWhereThenTheAssignmentsThenOrderBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $operation = $session->analyze('UPDATE t SET a = b WHERE c = 1 ORDER BY a');
        self::assertInstanceOf(Operation::class, $operation);
        $statement = $operation->statement;
        self::assertInstanceOf(Update::class, $statement);
        $locator = new Locator();

        (new WriteOrder($locator))->update($statement);

        self::assertSame([['where clause', [3, 1]], ['field list', [5, 0]], ['field list', [6, 0]], ['order clause', [7, 0, 0]]], array_values($locator->places));
    }

    public function testValuesLocatesTheFirstRowBeforeTheColumnsInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('INSERT INTO t (a) VALUES (b), (b)');
        self::assertInstanceOf(Operation::class, $operation);
        $statement = $operation->statement;
        self::assertInstanceOf(InsertRows::class, $statement);
        $locator = new Locator(false, GrammarRelease::MySql5651);

        (new WriteOrder($locator))->values($statement);

        self::assertSame([[0, 0, 0], [1, 5, 0, 0], [2, 0, 0, 0]], array_column(array_values($locator->places), 1));
    }

    public function testUpdateLocatesTheNamesInsideARetainedRowAssignment(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = $session->analyze('UPDATE t SET a = ROW(b, c)');
        self::assertInstanceOf(Operation::class, $operation);
        self::assertInstanceOf(Update::class, $operation->statement);
        $locator = new Locator();

        (new WriteOrder($locator))->update($operation->statement);

        self::assertSame(['a', 'b', 'c'], array_map(static fn ($node): ?string => $node instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse ? $node->name->value : null, $locator->nodes()));
        self::assertSame([5, 6, 6], array_map(static fn (array $place): int => $place[1][0], array_values($locator->places)));
    }

    public function testQueryLocatesTheColumnsOfOnDuplicateKeyUpdateBeforeTheQuery(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $operation = $session->analyze('INSERT INTO t (a) SELECT b FROM t ON DUPLICATE KEY UPDATE c = 1');
        self::assertInstanceOf(Operation::class, $operation);
        $statement = $operation->statement;
        self::assertInstanceOf(InsertQuery::class, $statement);
        $locator = new Locator();

        (new WriteOrder($locator))->query($statement);

        self::assertSame(['a', 'c', 'b'], array_map(static fn ($node): string => $node instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse ? $node->name->value : '', $locator->nodes()));
    }

    public function testDeleteLocatesWhereThenOrderBy(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, c INT)');
        $operation = $session->analyze('DELETE FROM t WHERE c = 1 ORDER BY a');
        self::assertInstanceOf(Operation::class, $operation);
        $statement = $operation->statement;
        self::assertInstanceOf(Delete::class, $statement);
        $locator = new Locator();

        (new WriteOrder($locator))->delete($statement);

        self::assertSame([['where clause', [2, 1]], ['order clause', [6, 0, 0]]], array_values($locator->places));
    }
}
