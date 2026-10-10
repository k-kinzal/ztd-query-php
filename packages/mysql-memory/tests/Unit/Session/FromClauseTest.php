<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use MySqlMemory\Instance;
use MySqlMemory\Session\FromClause;
use MySqlMemory\Session\Locator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(FromClause::class)]
#[Small]
final class FromClauseTest extends TestCase
{
    public function testDerivedLocatesOnlyTheDerivedTables(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM (SELECT b FROM t WHERE c = 1) AS s JOIN t AS u ON u.a = 1')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $clause = new FromClause(new Locator());
        $clause->derived($statement->from, [7]);

        self::assertSame(['b', 'c'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $clause->locator->nodes()));
        self::assertSame(['field list', 'where clause'], array_column(array_values($clause->locator->places), 0));
    }

    public function testDerivedLocatesNothingInABaseTable(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM t')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $clause = new FromClause(new Locator());
        $clause->derived($statement->from, []);
        $clause->derived(null, []);

        self::assertSame([], $clause->locator->places);
    }

    public function testConditionsLocatesTheOnConditionsOfTheJoins(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT t.a FROM t JOIN t AS u ON u.c = 1 WHERE t.b = 1')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $clause = new FromClause(new Locator());
        $clause->conditions($statement->from, [3]);

        self::assertSame(['c'], array_map(static fn (ColumnUse|OutputOrdinal|FunctionCall $node): string => $node instanceof OutputOrdinal ? (string) $node->position() : $node->name->value, $clause->locator->nodes()));
        self::assertSame([['on clause', [3, 2, 1]]], array_values($clause->locator->places));
    }

    public function testConditionsLocatesNothingWithoutAJoin(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT, b INT, c INT)');
        $statement = $session->analyze('SELECT a FROM (SELECT a FROM t AS x JOIN t AS y ON x.b = y.b) AS s')->statement;
        self::assertInstanceOf(Select::class, $statement);
        $clause = new FromClause(new Locator());
        $clause->conditions($statement->from, []);
        $clause->conditions(null, []);

        self::assertSame([], $clause->locator->places);
    }
}
