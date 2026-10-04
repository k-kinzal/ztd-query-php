<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Locking;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

#[CoversClass(Locking::class)]
#[Medium]
final class LockingTest extends TestCase
{
    public function testCheckReportsANamedItemThatCannotBeLocked(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['FOR UPDATE cannot be applied to a function', 'FOR SHARE cannot be applied to a WITH query', 'FOR KEY SHARE cannot be applied to a join', 'FOR UPDATE cannot be applied to a table function'],
            array_map(static fn (Diagnostic $problem): string => $problem->message(), [
                ...$semantics->analyze('SELECT 1 FROM generate_series(1, 2) AS f FOR UPDATE OF f', [])->facts->diagnostics,
                ...$semantics->analyze('WITH c AS (SELECT 1) SELECT 1 FROM c FOR SHARE OF c', [])->facts->diagnostics,
                ...$semantics->analyze('SELECT 1 FROM ((SELECT 1 AS a) AS x JOIN (SELECT 1 AS a) AS y USING (a)) AS j FOR KEY SHARE OF j', [])->facts->diagnostics,
                ...$semantics->analyze("SELECT 1 FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text) AS x FOR UPDATE OF x", [])->facts->diagnostics,
            ]),
        );
    }

    public function testCheckReportsATableOnTheNullableSideOfAnOuterJoin(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int4)', []), $semantics->analyze('CREATE TABLE u (a int4)', [])];
        self::assertSame(
            ['FOR UPDATE cannot be applied to the nullable side of an outer join', 'FOR SHARE cannot be applied to the nullable side of an outer join', 'FOR UPDATE cannot be applied to the nullable side of an outer join'],
            array_map(static fn (Diagnostic $problem): string => $problem->message(), [
                ...$semantics->analyze('SELECT 1 FROM t LEFT JOIN u ON true FOR UPDATE', $context)->facts->diagnostics,
                ...$semantics->analyze('SELECT 1 FROM t RIGHT JOIN u ON true FOR SHARE OF t', $context)->facts->diagnostics,
                ...$semantics->analyze('(SELECT 1 FROM t FULL JOIN u ON true) FOR UPDATE', $context)->facts->diagnostics,
            ]),
        );
    }

    public function testCheckAdmitsTheOtherSideAndASkippedItem(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int4)', []), $semantics->analyze('CREATE TABLE u (a int4)', [])];
        self::assertSame([], [
            ...$semantics->analyze('SELECT 1 FROM t LEFT JOIN u ON true FOR UPDATE OF t', $context)->facts->diagnostics,
            ...$semantics->analyze('WITH c AS (SELECT 1) SELECT 1 FROM c, t, generate_series(1, 2) AS f FOR UPDATE', $context)->facts->diagnostics,
        ]);
    }

    public function testNamedAnswersTheItemWithTheName(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 FROM t AS x', []);
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(Relation::class, $query->statement->from);
        $visible = [new VisibleRelation($query->statement->from, $query->facts->relation($query->statement->from)->shape, new Name('x'))];
        $locking = new Locking();
        $derivation = new Derivation($query->context);
        self::assertSame([$query->statement->from, null], [$locking->named($visible, new Name('x'), $derivation), $locking->named($visible, new Name('t'), $derivation)]);
    }

    public function testRefusedAnswersTheRuleOfAnItemThatCannotBeLocked(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $function = $semantics->analyze('SELECT 1 FROM generate_series(1, 2) AS f', []);
        $table = $semantics->analyze('SELECT 1 FROM t', []);
        self::assertInstanceOf(Select::class, $function->statement);
        self::assertInstanceOf(Select::class, $table->statement);
        self::assertInstanceOf(Relation::class, $function->statement->from);
        self::assertInstanceOf(Relation::class, $table->statement->from);
        $derivation = new Derivation($table->context);
        self::assertSame([QueryMisuseRule::LockingOnFunction, null], [
            (new Locking())->refused($function->statement->from, $derivation, $derivation->environment()),
            (new Locking())->refused($table->statement->from, $derivation, $derivation->environment()),
        ]);
    }

    public function testTablesMarksTheNullableSides(): void
    {
        $query = (new Semantics(Dialect::PostgreSql))->analyze('SELECT 1 FROM a LEFT JOIN (b RIGHT JOIN c ON true) ON true, d', []);
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(Relation::class, $query->statement->from);
        $pairs = (new Locking())->tables($query->statement->from);
        usort($pairs, static fn (array $left, array $right): int => $left[0]->name()->name->value <=> $right[0]->name()->name->value);
        self::assertSame([['a', false], ['b', true], ['c', true], ['d', false]], array_map(static fn (array $pair): array => [$pair[0]->name()->name->value, $pair[1]], $pairs));
        self::assertContainsOnlyInstancesOf(TableInput::class, array_column($pairs, 0));
    }
}
