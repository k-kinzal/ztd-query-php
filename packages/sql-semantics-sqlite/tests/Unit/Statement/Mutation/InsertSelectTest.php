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
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(InsertSelect::class)]
#[Medium]
final class InsertSelectTest extends TestCase
{
    public function testDeriveStatementComparesTheQueryWidthWithTheColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $u = $semantics->analyze('CREATE TABLE u (a INTEGER NOT NULL, c TEXT)');
        $narrow = $semantics->analyze('INSERT INTO t SELECT a, c FROM u', [$t, $u]);
        $wide = $semantics->analyze('INSERT INTO t (a) SELECT * FROM u', [$t, $u]);
        $exact = $semantics->analyze('INSERT INTO t (a, b) SELECT a, c FROM u', [$t, $u]);
        $open = $semantics->analyze('INSERT INTO t SELECT * FROM u');

        self::assertInstanceOf(ArityMismatch::class, $narrow->facts->diagnostics[0]);
        self::assertSame(ArityRule::InsertedValues, $narrow->facts->diagnostics[0]->rule);
        self::assertSame('2 values for 3 columns.', $narrow->facts->diagnostics[0]->message());
        self::assertInstanceOf(ArityMismatch::class, $wide->facts->diagnostics[0]);
        self::assertSame('2 values for 1 columns.', $wide->facts->diagnostics[0]->message());
        self::assertSame([], $exact->facts->diagnostics);
        self::assertSame([], $open->facts->diagnostics);
    }

    public function testDeriveWithinBindsTheWithClauseForTheQueryAndReturnsTheTargetRow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('WITH w AS (SELECT 1 AS q) INSERT INTO t (a) SELECT q FROM w RETURNING id, b', [$t]);
        $fields = $query->fields();

        self::assertInstanceOf(InsertSelect::class, $query->statement);
        self::assertInstanceOf(Select::class, $query->statement->query);
        self::assertCount(1, $query->facts->query($query->statement->query)->shape->slots);
        self::assertNotNull($fields);
        self::assertSame(['id', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame($t->declarations()[0]->columns[2], $query->field('b')->column());
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveWithinAnswersNullWithoutReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('INSERT INTO t (a) SELECT 1', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));

        self::assertInstanceOf(InsertSelect::class, $statement);
        self::assertNull($statement->deriveWithin($derivation, $derivation->environment()));
        self::assertTrue($derivation->facts()->covers($statement->query));
    }

    public function testRenderWritesIntoQueryUpsertsAndReturning(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('insert into t (id) select 1 union select 2 on conflict (id) do nothing returning *');

        self::assertSame('INSERT INTO t (id) SELECT 1 UNION SELECT 2 ON CONFLICT (id) DO NOTHING RETURNING *', $query->toString());
        self::assertInstanceOf(InsertSelect::class, $query->statement);
        self::assertCount(1, $query->statement->upserts);
    }

    public function testRejectsABareValuesClause(): void
    {
        $this->expectExceptionMessage('An INSERT of a bare VALUES clause is an insert of written rows.');

        new InsertSelect(new InsertInto(new MutationTarget(new QualifiedName(new Name('t')))), new ValuesClause([new ValueRow([new IntegerLiteral('1')])]));
    }

    public function testRejectsANonLastUpsertWithoutAConflictTarget(): void
    {
        $into = new InsertInto(new MutationTarget(new QualifiedName(new Name('t'))));
        $query = new Select([new ResultColumn(new IntegerLiteral('1'))]);
        $target = new ConflictTarget([new SortTerm(new IntegerLiteral('1'))]);

        $this->expectExceptionMessage('Only the last ON CONFLICT clause may omit the conflict target.');

        new InsertSelect($into, $query, [new Upsert(), new Upsert($target)]);
    }
}
