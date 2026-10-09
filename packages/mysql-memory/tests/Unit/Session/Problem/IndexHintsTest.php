<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\IndexHints;
use MySqlMemory\Session\Problems;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

#[CoversClass(IndexHints::class)]
#[Small]
final class IndexHintsTest extends TestCase
{
    public function testCheckRefusesAnIndexATableAnUpdateWritesDoesNotHave(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, KEY k (a))');
        $statement = $session->analyze('UPDATE t USE INDEX (nosuch) SET a = 1')->statement;

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1176);
        $this->expectExceptionMessage("Key 'nosuch' doesn't exist in table 't'");

        (new IndexHints())->check($statement, (new Problems())->reached($statement), $session);
    }

    public function testCheckLeavesTheTablesOfAQueryBlockToWhereTheBlockIsResolved(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');
        $statement = $session->analyze('UPDATE t SET a = (SELECT 1 FROM t AS x USE INDEX (nosuch))')->statement;

        (new IndexHints())->check($statement, (new Problems())->reached($statement), $session);

        $this->addToAssertionCount(1);
    }

    public function testCommonAnswersTheNamesOfTheCommonTables(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['c'], (new IndexHints())->common($session->analyze('WITH c AS (SELECT 1) SELECT * FROM c')->statement));
    }

    public function testRefusalNamesTheTableByItsAliasAndTheIndexAsWritten(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT PRIMARY KEY, KEY k (a))');
        $reference = (new Walker())->find($session->analyze('SELECT * FROM t AS x FORCE INDEX (K, primary, Nosuch)')->statement, TableReference::class)[0];

        $refusal = (new IndexHints())->refusal($reference, [], $session);

        self::assertInstanceOf(SqlError::class, $refusal);
        self::assertSame("Key 'Nosuch' doesn't exist in table 'x'", $refusal->getMessage());
    }

    public function testRefusalAcceptsAnyIndexOfACommonTable(): void
    {
        $session = (new Instance())->connect();
        $reference = (new Walker())->find($session->analyze('WITH c AS (SELECT 1) SELECT * FROM c USE INDEX (k)')->statement, TableReference::class)[0];

        self::assertNull((new IndexHints())->refusal($reference, ['c'], $session));
    }

    public function testKeysAnswersNoIndexForAView(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, KEY k (a))');
        $session->query('CREATE VIEW v AS SELECT * FROM t');
        $view = (new Walker())->find($session->analyze('SELECT * FROM v')->statement, TableReference::class)[0];
        $table = (new Walker())->find($session->analyze('SELECT * FROM t')->statement, TableReference::class)[0];
        $system = (new Walker())->find($session->analyze('SELECT * FROM information_schema.TABLES')->statement, TableReference::class)[0];

        self::assertSame([[], ['k'], null], [(new IndexHints())->keys($view, $session), (new IndexHints())->keys($table, $session), (new IndexHints())->keys($system, $session)]);
    }

    public function testRefusalRaisesTheHintsOfABlockBeforeItsDerivedTablesInMySql84(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionMessage("Key 'k' doesn't exist in table 't'");

        $session->query('SELECT * FROM (SELECT nosuch FROM t) AS x JOIN t USE INDEX (k)');
    }

    public function testRefusalRaisesTheHintsOfABlockAfterItsDerivedTablesInMySql56(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionMessage("Unknown column 'nosuch' in 'field list'");

        $session->query('SELECT * FROM (SELECT nosuch FROM t) AS x JOIN t USE INDEX (k)');
    }

    public function testRefusalRaisesTheHintsOfASubqueryInWhereAfterTheNamesBeforeIt(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT)');

        $this->expectExceptionMessage("Unknown column 'nosuch' in 'where clause'");

        $session->query('SELECT * FROM t WHERE nosuch = (SELECT 1 FROM t AS u USE INDEX (k))');
    }
}
