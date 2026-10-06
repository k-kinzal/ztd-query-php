<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(Delete::class)]
#[Medium]
final class DeleteTest extends TestCase
{
    public function testDeriveStatementResolvesTheTargetAmongDeclaredTablesOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $table = $semantics->analyze('DELETE FROM t', [$t]);
        $common = $semantics->analyze('WITH w AS (SELECT 1 AS q) DELETE FROM w', [$t]);

        self::assertInstanceOf(Delete::class, $table->statement);
        self::assertInstanceOf(DeclaredTable::class, $table->facts->relation($table->statement->target)->table);
        self::assertNull($table->shape());
        self::assertNull($table->inputRelation());
        self::assertSame([], $table->facts->diagnostics);
        self::assertInstanceOf(Delete::class, $common->statement);
        self::assertInstanceOf(MissingTable::class, $common->facts->relation($common->statement->target)->table);
        self::assertInstanceOf(MissingTable::class, $common->facts->diagnostics[0]);
    }

    public function testDeriveWithinSeesTheTargetAndTheCommonTablesInTheWhereClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('WITH w AS (SELECT 1 AS q) DELETE FROM t WHERE a IN (SELECT q FROM w) AND b = 1', [$t]);

        self::assertInstanceOf(Delete::class, $query->statement);
        self::assertInstanceOf(Binary::class, $query->statement->where);
        self::assertInstanceOf(Binary::class, $query->statement->where->right);
        $resolution = $query->facts->scalar($query->statement->where->right->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($query->statement->target, $resolution->relation);
        self::assertSame($t->declarations()[0]->columns[2], $resolution->declaration());
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveStatementMakesReturningTheOutputOfTheTargetRow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $star = $semantics->analyze('DELETE FROM t WHERE a = 1 RETURNING *', [$t]);
        $named = $semantics->analyze('WITH w AS (SELECT 1 AS q) DELETE FROM t RETURNING b, q', [$t]);
        $fields = $star->fields();

        self::assertNotNull($fields);
        self::assertSame(['id', 'a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $fields->items));
        self::assertSame($t->declarations()[0]->columns[2], $star->field('b')->column());
        self::assertSame($t->declarations()[0]->columns[2], $named->field('b')->column());
        self::assertInstanceOf(MissingColumn::class, $named->field('q')->resolution);
    }

    public function testDeriveWithinAnswersNullWithoutReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $t = $semantics->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('DELETE FROM t WHERE a = 1', [$t])->statement;
        $returning = $semantics->analyze('DELETE FROM t RETURNING a', [$t])->statement;
        $derivation = new Derivation($semantics->context([$t]));

        self::assertInstanceOf(Delete::class, $statement);
        self::assertInstanceOf(Delete::class, $returning);
        self::assertNull($statement->deriveWithin($derivation, $derivation->environment()));
        self::assertCount(1, $returning->deriveWithin($derivation, $derivation->environment())?->shape->slots ?? []);
        self::assertNull($derivation->facts()->output);
    }

    public function testDeriveStatementReportsRaiseOutsideATrigger(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('DELETE FROM t WHERE RAISE(IGNORE)');

        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::RaiseOutsideTrigger, $query->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesWithTargetWhereAndReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $built = new Operation($semantics->context(), new Delete(new MutationTarget(new QualifiedName(new Name('t'))), null, [new Star()]));

        self::assertSame('DELETE FROM t RETURNING *', $built->toString());
        self::assertSame('WITH w AS (SELECT 1 AS q) DELETE FROM main.t AS x NOT INDEXED WHERE a IN (SELECT q FROM w) RETURNING a, 1', $semantics->analyze('with w as (select 1 as q) delete from main.t as x not indexed where a in (select q from w) returning a, 1')->toString());
    }
}
