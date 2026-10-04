<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Mutation\InsertFacts;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(InsertFacts::class)]
#[Medium]
final class InsertFactsTest extends TestCase
{
    public function testDeriveCountsTheSourceColumnsAgainstTheTarget(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $rows = $semantics->analyze('INSERT INTO t VALUES (1, 2)', [$t])->statement;
        $listed = $semantics->analyze('INSERT INTO t (a) VALUES (1, 2)', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));
        $facts = new InsertFacts();

        self::assertInstanceOf(InsertRows::class, $rows);
        self::assertInstanceOf(InsertRows::class, $listed);
        self::assertNull($facts->derive($rows->into, $rows->rows, [], [], $derivation, $derivation->environment()));
        self::assertNull($facts->derive($listed->into, $listed->rows, [], [], $derivation, $derivation->environment()));
        $diagnostics = $derivation->facts()->diagnostics;
        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $diagnostics[0]);
        self::assertSame(ArityRule::InsertedValues, $diagnostics[0]->rule);
        self::assertSame([3, 2], [$diagnostics[0]->expected, $diagnostics[0]->actual]);
        self::assertInstanceOf(ArityMismatch::class, $diagnostics[1]);
        self::assertSame([1, 2], [$diagnostics[1]->expected, $diagnostics[1]->actual]);
        self::assertTrue($derivation->facts()->covers($rows->rows));
    }

    public function testDeriveSkipsTheCountWhenEitherSideIsOpen(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $open = $semantics->analyze('INSERT INTO t SELECT * FROM u', [$t], );
        $undeclared = $semantics->analyze('INSERT INTO u VALUES (1)', [$t], );
        $derivation = new Derivation($semantics->context([$t], false));
        $facts = new InsertFacts();

        self::assertInstanceOf(InsertSelect::class, $open->statement);
        self::assertInstanceOf(InsertRows::class, $undeclared->statement);
        $facts->derive($open->statement->into, $open->statement->query, [], [], $derivation, $derivation->environment());
        $facts->derive($undeclared->statement->into, $undeclared->statement->rows, [], [], $derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testDeriveLetsUpsertsSeeTheTargetAndExcludedInTheRightPlaces(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $derivation = new Derivation($semantics->context([$t]));
        $into = new InsertInto(new MutationTarget(new QualifiedName(new Name('t'))), [new Name('a')]);
        $term = new ColumnUse(new Name('id'));
        $wrong = new ColumnUse(new Name('id'), new QualifiedName(new Name('excluded')));
        $value = new ColumnUse(new Name('a'), new QualifiedName(new Name('excluded')));
        $predicate = new ColumnUse(new Name('b'));
        $upsert = new Upsert(new ConflictTarget([new SortTerm($term)], $wrong), [new Assignment(new Name('a'), $value), new Assignment(new Name('zz'), new ColumnUse(new Name('b')))], $predicate);
        (new InsertFacts())->derive($into, null, [$upsert], [], $derivation, $derivation->environment());
        $facts = $derivation->facts();
        $resolved = $facts->scalar($value)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($term)->resolution);
        self::assertInstanceOf(MissingColumn::class, $facts->scalar($wrong)->resolution);
        self::assertInstanceOf(ResolvedColumn::class, $resolved);
        self::assertSame($into->target, $resolved->relation);
        self::assertSame($t->declarations()[0]->columns[1], $resolved->declaration());
        self::assertInstanceOf(ResolvedColumn::class, $facts->scalar($predicate)->resolution);
        self::assertSame(['excluded.id', 'zz'], array_map(static fn (object $diagnostic): string => $diagnostic instanceof MissingColumn ? ($diagnostic->qualifier === null ? '' : $diagnostic->qualifier->name->value . '.') . $diagnostic->name->value : '', $facts->diagnostics));
    }

    public function testDeriveBindsTheWithClauseAndReturnsTheTargetRow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('WITH w AS (SELECT 1 AS q) INSERT INTO t (a) SELECT q FROM w', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));
        $returning = new ResultColumn(new ColumnUse(new Name('b')));

        self::assertInstanceOf(InsertSelect::class, $statement);
        $fact = (new InsertFacts())->derive($statement->into, $statement->query, [], [new Star(), $returning], $derivation, $derivation->environment());
        self::assertNotNull($fact);
        self::assertSame(['id', 'a', 'b', 'b'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $fact->shape->slots));
        self::assertSame($t->declarations()[0]->columns[2], $fact->shape->slots[3]->declaration());
        self::assertSame([], $derivation->facts()->diagnostics);
        self::assertNull($derivation->facts()->output);
    }
}
