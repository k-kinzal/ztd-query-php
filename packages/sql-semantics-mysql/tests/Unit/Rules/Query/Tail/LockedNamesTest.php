<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Tail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\LockedNames;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(LockedNames::class)]
#[Medium]
final class LockedNamesTest extends TestCase
{
    public function testProblemMatchesCorrelationNamesAndTableNamesByteForByteAndLeavesQualifiedNamesAlone(): void
    {
        $rule = new LockedNames();
        $aliased = new TableReference(new QualifiedName(new Name('t')), new Name('T'));
        $plain = new TableReference(new QualifiedName(new Name('t')));
        $of = static fn (string $name, ?string $schema = null): LockingClause => new LockingClause(LockStrength::Update, [new QualifiedName(new Name($name), $schema === null ? null : new Name($schema))]);

        self::assertEquals(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t'))), $rule->problem($aliased, [$of('t')]));
        self::assertNull($rule->problem($aliased, [$of('T')]));
        self::assertNull($rule->problem($plain, [$of('t')]));
        self::assertEquals(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('T'))), $rule->problem($plain, [$of('T')]));
        self::assertNull($rule->problem($plain, [$of('t', 'nosuch')]));
        self::assertNull($rule->problem($plain, [new LockingClause(LockStrength::Update), new LockingClause(LockStrength::Share)]));
        self::assertNull($rule->problem(null, []));
    }

    public function testProblemLocksATableOnceAndNoDerivedTable(): void
    {
        $rule = new LockedNames();
        $table = new TableReference(new QualifiedName(new Name('t')));
        $derived = new DerivedTable(new Select([], [new SelectExpression(new NumberLiteral('1'))]), new Name('d'));
        $of = static fn (string $name): LockingClause => new LockingClause(LockStrength::Update, [new QualifiedName(new Name($name))]);

        self::assertEquals(new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('t'))), $rule->problem($table, [$of('t'), $of('t')]));
        self::assertEquals(new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('t'))), $rule->problem($table, [new LockingClause(LockStrength::Update, [new QualifiedName(new Name('t')), new QualifiedName(new Name('t'))])]));
        self::assertEquals(new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('d'))), $rule->problem($derived, [$of('d')]));
    }

    public function testStatementLocksTheLastBlockAfterItsOwnClauses(): void
    {
        $rule = new LockedNames();
        $first = new Select([], [new SelectExpression(new NumberLiteral('1'))], new TableReference(new QualifiedName(new Name('t'))));
        $last = new Select([], [new SelectExpression(new NumberLiteral('2'))], new TableReference(new QualifiedName(new Name('u'))));
        $of = static fn (string $name): LockingClause => new LockingClause(LockStrength::Update, [new QualifiedName(new Name($name))]);

        self::assertEquals(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t'))), $rule->statement(new SetOperation($first, SetOperator::Union, null, $last), [$of('t')]));
        self::assertNull($rule->statement(new SetOperation($first, SetOperator::Union, null, $last), [$of('u')]));
        self::assertNull($rule->statement(new ExplicitTable(new QualifiedName(new Name('t'))), [$of('t')]));
        self::assertEquals(new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('u'))), $rule->statement(new ExplicitTable(new QualifiedName(new Name('t'))), [$of('u')]));
    }

    public function testNamesUsesTheCorrelationNameWhenThereIsOne(): void
    {
        $rule = new LockedNames();

        self::assertTrue($rule->names(new TableReference(new QualifiedName(new Name('t'))), 't'));
        self::assertFalse($rule->names(new TableReference(new QualifiedName(new Name('t')), new Name('a')), 't'));
        self::assertTrue($rule->names(new ExplicitTable(new QualifiedName(new Name('t'))), 't'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRefusals(): iterable
    {
        yield 'unknown name' => ['SELECT 1 FROM t FOR UPDATE OF u', 'Table in the locking clause is not in the query'];
        yield 'table name hidden by its alias' => ['SELECT 1 FROM t AS a, t AS b FOR UPDATE OF t', 'Table in the locking clause is not in the query'];
        yield 'last operand of a union' => ['SELECT 1 FROM t UNION SELECT 2 FROM u FOR UPDATE OF t', 'Table in the locking clause is not in the query'];
        yield 'subquery' => ['SELECT (SELECT 1 FROM t FOR UPDATE OF x)', 'Table in the locking clause is not in the query'];
        yield 'insert source' => ['INSERT INTO x SELECT 1 FROM t FOR UPDATE OF u', 'Table in the locking clause is not in the query'];
        yield 'twice' => ['SELECT 1 FROM t FOR UPDATE OF t FOR SHARE OF t', 'Table appears in multiple locking clauses'];
        yield 'derived table' => ['SELECT 1 FROM t, (SELECT 1) AS d FOR UPDATE OF d', 'Table appears in multiple locking clauses'];
        yield 'in a body' => ['CREATE PROCEDURE p() BEGIN SELECT 1 FROM t FOR UPDATE OF t, t; END', 'Table appears in multiple locking clauses'];
    }

    #[DataProvider('providerRefusals')]
    public function testProblemIsRefusedWhileReading(string $sql, string $rule): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('The locking clause names a table the block cannot lock.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(Misuse::class, $failure->problem);
            self::assertSame($rule, $failure->problem->rule->value);
            self::assertFalse($failure->aborts);
        }
        self::assertSame('SELECT 1 FROM t AS `T` FOR UPDATE OF T', (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t AS `T` FOR UPDATE OF T')->toString());
    }
}
