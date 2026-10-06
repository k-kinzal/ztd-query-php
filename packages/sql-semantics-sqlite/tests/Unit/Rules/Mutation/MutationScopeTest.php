<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\MutationScope;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\RowAssignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(MutationScope::class)]
#[Medium]
final class MutationScopeTest extends TestCase
{
    public function testOpenBindsTheWithClauseAndMakesTheTargetVisible(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('WITH w AS (SELECT 1 AS q) UPDATE t AS x SET a = 1', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));
        $outer = $derivation->environment();

        self::assertInstanceOf(Update::class, $statement);
        [$base, $target] = (new MutationScope())->open($statement->target, $statement->with, $derivation, $outer);
        self::assertSame($outer, $base->outer);
        self::assertNotNull($base->commonTable(new Name('w')));
        self::assertSame($statement->target, $target->relation);
        self::assertSame('x', $target->alias?->value);
        self::assertSame($statement->target->name, $target->name);
        self::assertCount(3, $target->shape->slots);
        self::assertCount(1, $target->implicit);
        self::assertInstanceOf(DeclaredTable::class, $derivation->facts()->relation($statement->target)->table);
        self::assertSame($outer, (new MutationScope())->open($statement->target, null, $derivation, $outer)[0]);
    }

    public function testNamesReportsColumnsAClosedTargetCertainlyLacks(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $closed = new Derivation($semantics->context([$t]));
        $open = new Derivation($semantics->context());
        $scope = new MutationScope();
        $known = $scope->open(new MutationTarget(new QualifiedName(new Name('t'))), null, $closed, $closed->environment())[1];
        $unknown = $scope->open(new MutationTarget(new QualifiedName(new Name('t'))), null, $open, $open->environment())[1];
        $scope->names([new Name('A'), new Name('rowid'), new Name('zz')], $known, $closed);
        $scope->names([new Name('zz')], $unknown, $open);
        $diagnostics = $closed->facts()->diagnostics;

        self::assertCount(1, $diagnostics);
        self::assertInstanceOf(MissingColumn::class, $diagnostics[0]);
        self::assertSame('zz', $diagnostics[0]->name->value);
        self::assertSame([], $open->facts()->diagnostics);
    }

    public function testAssignChecksTheColumnsAndTheWidthOfEachAssignment(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $scope = new MutationScope();
        $target = $scope->open(new MutationTarget(new QualifiedName(new Name('t'))), null, $derivation, $derivation->environment())[1];
        $environment = new Environment($derivation->context, $derivation->environment(), [$target]);
        $value = new ColumnUse(new Name('b'));
        $scope->assign([
            new Assignment(new Name('a'), $value),
            new RowAssignment([new Name('a'), new Name('b')], new RowExpression([new IntegerLiteral('1'), new IntegerLiteral('2')])),
            new RowAssignment([new Name('a')], new RowExpression([new IntegerLiteral('1'), new IntegerLiteral('2')])),
            new RowAssignment([new Name('a'), new Name('zz')], new IntegerLiteral('1')),
        ], $target, $derivation, $environment);
        $facts = $derivation->facts();
        $resolution = $facts->scalar($value)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($target->relation, $resolution->relation);
        self::assertCount(3, $facts->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $facts->diagnostics[0]);
        self::assertSame(ArityRule::RowAssignment, $facts->diagnostics[0]->rule);
        self::assertSame([1, 2], [$facts->diagnostics[0]->expected, $facts->diagnostics[0]->actual]);
        self::assertInstanceOf(MissingColumn::class, $facts->diagnostics[1]);
        self::assertInstanceOf(ArityMismatch::class, $facts->diagnostics[2]);
        self::assertSame([2, 1], [$facts->diagnostics[2]->expected, $facts->diagnostics[2]->actual]);
    }

    public function testReturningIsNullWithoutColumnsAndProjectsTheTargetOtherwise(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $scope = new MutationScope();
        $target = $scope->open(new MutationTarget(new QualifiedName(new Name('t'))), null, $derivation, $derivation->environment())[1];
        $base = $derivation->environment();
        $fact = $scope->returning([new Star(), new TableStar(new Name('t')), new ResultColumn(new ColumnUse(new Name('b')), new Name('q'))], $target, $derivation, $base);

        self::assertNull($scope->returning([], $target, $derivation, $base));
        self::assertNotNull($fact);
        self::assertSame(['id', 'a', 'b', 'id', 'a', 'b', 'q'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertSame($t->declarations()[0]->columns[2], $fact->shape->slots[6]->declaration());
        self::assertTrue($fact->shape->complete());
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
