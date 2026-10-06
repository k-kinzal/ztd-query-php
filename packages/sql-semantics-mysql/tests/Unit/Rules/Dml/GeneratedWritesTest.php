<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\GeneratedWrites;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(GeneratedWrites::class)]
#[Medium]
final class GeneratedWritesTest extends TestCase
{
    public function testValueReportsAValueWrittenIntoAGeneratedColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1), s INT AS (a * 2) STORED, b INT DEFAULT 5)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $tables)->facts->diagnostics);

        self::assertSame(["The value specified for generated column 'g' in table 't' is not allowed."], $messages('INSERT INTO t (a, G) VALUES (1, 2)'));
        self::assertSame(["The value specified for generated column 's' in table 't' is not allowed."], $messages('REPLACE INTO t (a, s) VALUES (1, NULL)'));
        self::assertSame(["The value specified for generated column 'g' in table 't' is not allowed."], $messages('INSERT IGNORE INTO t (a, g) VALUES (1, DEFAULT), (2, 3)'));
        self::assertSame(["The value specified for generated column 'g' in table 't' is not allowed.", "The value specified for generated column 's' in table 't' is not allowed."], $messages('INSERT INTO t VALUES (1, 2, 3, 4)'));
    }

    public function testValueAcceptsDefaultValueItems(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1), s INT AS (a * 2) STORED, b INT DEFAULT 5)')->declarations();

        self::assertSame([], $semantics->analyze('INSERT INTO t VALUES (1, DEFAULT, DEFAULT(b), 4)', $tables)->facts->diagnostics);
        self::assertSame([], $semantics->analyze('INSERT INTO t (a, g) VALUES (1, ((DEFAULT(b))))', $tables)->facts->diagnostics);
    }

    public function testValueLeavesARowWithAnotherCountToTheCountMismatch(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1))')->declarations();

        self::assertSame(["Column count doesn't match value count at row 1"], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('INSERT INTO t (a, g) VALUES (1, 2, 3)', $tables)->facts->diagnostics));
    }

    public function testAssignmentsReportsAssignedGeneratedColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT PRIMARY KEY, g INT AS (a + 1), b INT)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $tables)->facts->diagnostics);
        $expected = ["The value specified for generated column 'g' in table 't' is not allowed."];

        self::assertSame($expected, $messages('INSERT INTO t SET a = 1, g = 2'));
        self::assertSame($expected, $messages('INSERT INTO t (a) VALUES (1) AS n ON DUPLICATE KEY UPDATE g = n.a'));
        self::assertSame($expected, $messages('INSERT INTO t (a) VALUES (1) ON DUPLICATE KEY UPDATE g = VALUES(g)'));
        self::assertSame($expected, $messages('UPDATE IGNORE t SET g = g'));
        self::assertSame($expected, $messages('UPDATE t, t AS u SET u.b = 1, t.g = 1'));
        self::assertSame([], $messages('UPDATE t, t AS u SET u.g = DEFAULT, t.g = DEFAULT(t.b)'));
        self::assertSame([], $messages('INSERT INTO t SET a = 1, g = DEFAULT ON DUPLICATE KEY UPDATE g = DEFAULT'));
    }

    public function testAssignmentsIgnoresColumnsOutsideDeclaredTables(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1))')->declarations();
        $copy = $semantics->analyze('CREATE TABLE c SELECT * FROM t', $tables)->declarations();

        self::assertSame([], $semantics->analyze('LOAD DATA INFILE \'f\' INTO TABLE t (a, g) SET g = 1', $tables)->facts->diagnostics);
        self::assertSame([], $semantics->analyze('UPDATE c SET g = 1', $copy)->facts->diagnostics);
    }

    public function testQueryReportsTheFieldsOfAQuerySource(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1), b INT DEFAULT 5)')->declarations();
        $messages = static fn (string $sql): array => array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze($sql, $tables)->facts->diagnostics);
        $expected = ["The value specified for generated column 'g' in table 't' is not allowed."];

        self::assertSame($expected, $messages('INSERT INTO t (a, g) SELECT 1, 2'));
        self::assertSame($expected, $messages('INSERT INTO t (a, g) SELECT 1, DEFAULT(b) FROM t UNION SELECT 2, DEFAULT(b) FROM t'));
        self::assertSame($expected, $messages('INSERT INTO t (a, g) SELECT * FROM (SELECT 1, DEFAULT(b) FROM t) AS d'));
        self::assertSame($expected, $messages('INSERT INTO t (a, g) VALUES ROW(1, 2)'));
        self::assertSame([], $messages('INSERT INTO t (a, g) WITH c AS (SELECT 1) (SELECT DISTINCT 1, (DEFAULT(b)) FROM t ORDER BY 1 LIMIT 1)'));
    }

    public function testQueryChecksTheRowsOfAValuesSourceAsRows(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1))')->declarations();
        $diagnostics = $semantics->analyze('INSERT INTO t (a, g) VALUES ROW(1, DEFAULT), ROW(2, 3)', $tables)->facts->diagnostics;

        self::assertCount(1, array_filter($diagnostics, static fn (Diagnostic $diagnostic): bool => $diagnostic instanceof GeneratedColumnWrite));
    }

    public function testValueRowsFindsAValuesStatementInsideItsClauses(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $values = $semantics->analyze('INSERT INTO t (a) WITH c AS (SELECT 1) (VALUES ROW(1)) ORDER BY 1 LIMIT 1')->statement;
        $select = $semantics->analyze('INSERT INTO t (a) (SELECT 1)')->statement;
        self::assertInstanceOf(InsertQuery::class, $values);
        self::assertInstanceOf(InsertQuery::class, $select);

        self::assertInstanceOf(ValuesQuery::class, (new GeneratedWrites())->valueRows($values->source));
        self::assertNull((new GeneratedWrites())->valueRows($select->source));
    }

    public function testDefaultTellsDefaultValueItems(): void
    {
        $insert = (new Semantics(Dialect::MySql))->analyze('INSERT INTO t (a, b, c, d) VALUES (DEFAULT, (DEFAULT(a)), DEFAULT(a) + 0, NULL)')->statement;
        self::assertInstanceOf(InsertRows::class, $insert);
        $values = $insert->rows[0]->values;

        self::assertSame([true, true, false, false, false], [...array_map(static fn ($value): bool => (new GeneratedWrites())->default($value), $values), (new GeneratedWrites())->default(null)]);
    }

    public function testTableAnswersTheDeclaredNameOfTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t (a INT, g INT AS (a + 1))')->declarations();

        self::assertSame("The value specified for generated column 'g' in table 't' is not allowed.", $semantics->analyze('UPDATE t AS x SET x.g = 1', $tables)->facts->diagnostics[0]->message());
    }
}
