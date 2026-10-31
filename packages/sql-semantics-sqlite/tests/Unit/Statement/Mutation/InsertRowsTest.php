<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(InsertRows::class)]
#[Medium]
final class InsertRowsTest extends TestCase
{
    public function testDeriveStatementCountsTheValuesAgainstTheColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $short = $semantics->analyze('INSERT INTO t VALUES (1)', [$t]);
        $listed = $semantics->analyze('INSERT INTO t (a, b) VALUES (1, 2)', [$t]);
        $open = $semantics->analyze('INSERT INTO t VALUES (1)');

        self::assertInstanceOf(ArityMismatch::class, $short->diagnostics()[0]);
        self::assertSame(ArityRule::InsertedValues, $short->diagnostics()[0]->rule);
        self::assertSame(3, $short->diagnostics()[0]->expected);
        self::assertSame(1, $short->diagnostics()[0]->actual);
        self::assertSame('1 values for 3 columns.', $short->diagnostics()[0]->message());
        self::assertSame([], $listed->diagnostics());
        self::assertSame([], $open->diagnostics());
        self::assertNull($short->shape());
    }

    public function testDeriveStatementReportsAColumnTheTableLacks(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('INSERT INTO t (zz, rowid) VALUES (1, 2)', [$t]);

        self::assertCount(1, $query->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $query->diagnostics()[0]);
        self::assertSame('zz', $query->diagnostics()[0]->name->value);
    }

    public function testDeriveWithinLetsDoUpdateSeeTheTableAndExcluded(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('INSERT INTO t VALUES (1, 2, 3) ON CONFLICT (id) DO UPDATE SET a = excluded.a, b = b WHERE a > 1 RETURNING *', [$t]);
        $missing = $semantics->analyze('INSERT INTO t VALUES (1, 2, 3) ON CONFLICT (id) DO UPDATE SET a = excluded.zz', [$t]);

        self::assertInstanceOf(InsertRows::class, $query);
        self::assertInstanceOf(Assignment::class, $query->upserts[0]->assignments[0]);
        self::assertInstanceOf(Assignment::class, $query->upserts[0]->assignments[1]);
        $excluded = $query->upserts[0]->assignments[0]->value->scalarInformation()->resolution;
        $bare = $query->upserts[0]->assignments[1]->value->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $excluded);
        self::assertInstanceOf(ResolvedColumn::class, $bare);
        self::assertSame($query->into->target, $excluded->relation);
        self::assertSame($t->declarations()[0]->columns[1], $excluded->declaration());
        self::assertSame($t->declarations()[0]->columns[2], $bare->declaration());
        self::assertSame(['id', 'a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []));
        self::assertSame([], $query->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $missing->diagnostics()[0]);
        self::assertSame('excluded', $missing->diagnostics()[0]->qualifier?->name->value);
    }

    public function testDeriveWithinAnswersNullWithoutReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = (new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('INSERT INTO t VALUES (1, 2, 3)', [$t]));
        $returning = (new \SqlSemantics\Construction\Occurrences())->copy($semantics->analyze('INSERT INTO t VALUES (1, 2, 3) RETURNING b', [$t]));
        $derivation = new Derivation($semantics->context([$t]));

        self::assertInstanceOf(InsertRows::class, $statement);
        self::assertInstanceOf(InsertRows::class, $returning);
        self::assertNull($statement->deriveWithin($derivation, $derivation->environment()));
        self::assertSame('b', $returning->deriveWithin($derivation, $derivation->environment())?->shape->slots[0]->name?->value);
        self::assertTrue($derivation->facts()->covers($statement->rows));
    }

    public function testDeriveStatementReportsRaiseOutsideATrigger(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO t VALUES (RAISE(IGNORE))');

        self::assertInstanceOf(Misuse::class, $query->diagnostics()[0]);
        self::assertSame(MisuseRule::RaiseOutsideTrigger, $query->diagnostics()[0]->rule);
    }

    public function testRenderWritesIntoRowsUpsertsAndReturning(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('insert into t (id, a) values (1, 2), (3, 4) on conflict (id) do update set a = excluded.a on conflict do nothing returning id');

        self::assertSame('INSERT INTO t (id, a) VALUES (1, 2), (3, 4) ON CONFLICT (id) DO UPDATE SET a = excluded.a ON CONFLICT DO NOTHING RETURNING id', $query->toString());
        self::assertInstanceOf(InsertRows::class, $query);
        self::assertCount(2, $query->rows->rows);
        self::assertCount(2, $query->upserts);
        self::assertCount(1, $query->returning);
    }

    public function testRejectsANonLastUpsertWithoutAConflictTarget(): void
    {
        $into = new InsertInto(new MutationTarget(new QualifiedName(new Name('t'))));
        $rows = new ValuesClause([new ValueRow([new IntegerLiteral('1')])]);
        $target = new ConflictTarget([new SortTerm(new IntegerLiteral('1'))]);

        $this->expectExceptionMessage('Only the last ON CONFLICT clause may omit the conflict target.');

        new InsertRows($into, $rows, [new Upsert(), new Upsert($target)]);
    }
}
