<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnUse::class)]
#[Medium]
final class ColumnUseTest extends TestCase
{
    public function testDeriveScalarResolvesADeclaredColumnWithItsFacts(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT a, t.b, main.t.a FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(ColumnUse::class, $statement->columns[1]->expression);
        $use = $statement->columns[1]->expression;
        self::assertSame('b', $use->name->value);
        self::assertSame('t', $use->qualifier?->name->value);
        self::assertNull($use->qualifier->schema);
        $fact = $operation->facts->scalar($use);
        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame($create->declarations()[0]->columns[1], $fact->resolution->slot->column);
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame($create->declarations()[0]->columns[0], $operation->field(2)->column());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsAMissingColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT nope, t.nope FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        $fact = $operation->facts->scalar($statement->columns[1]->expression);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(MissingColumn::class, $fact->resolution);
        self::assertSame($fact->resolution, $fact->type->cause);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertSame(['Column nope does not exist.', 'Column t.nope does not exist.'], array_map(static fn (object $diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testDeriveScalarDependsOnAnUndeclaredRelation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, u.a FROM u');
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(ConditionalColumn::class, $fact->resolution);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertInstanceOf(UndeclaredRelation::class, $fact->type->missing[0]);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertSame(Nullability::Dependent, $operation->field(1)->nullability);
    }

    public function testDeriveScalarResolvesAResultAliasInTheOrdering(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT b AS c FROM t ORDER BY c', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        $fact = $operation->facts->scalar($statement->orderBy[0]->expression);
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($operation->field(0), $fact->resolution->field);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheQualifierPartsAndTheName(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select a AS c1, t.b AS c2, main.t.a AS c3, [c] AS c4, `d` AS c5 from t');

        self::assertSame('SELECT a AS c1, t.b AS c2, main.t.a AS c3, c AS c4, d AS c5 FROM t', $operation->toString());
    }

    public function testRenderQuotesANameThatNeedsIt(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new ColumnUse(new Name('select'), new QualifiedName(new Name('my table'), new Name('main'))))]));

        self::assertSame('SELECT main.`my table`.`select`', $operation->toString());
    }
}
