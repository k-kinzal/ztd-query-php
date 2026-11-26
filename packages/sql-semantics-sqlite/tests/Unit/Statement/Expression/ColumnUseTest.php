<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\InspectedStatement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ColumnUse::class)]
#[Medium]
final class ColumnUseTest extends TestCase
{
    public function testDeriveScalarResolvesADeclaredColumnWithItsFacts(): void
    {
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->diagnose('SELECT a, t.b, main.t.a FROM t', [$create]);
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
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->diagnose('SELECT nope, t.nope FROM t', [$create]);
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
        $operation = (new Inspection())->diagnose('SELECT a, u.a FROM u');
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
        $semantics = new Inspection();
        $create = $semantics->diagnose('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->diagnose('SELECT b AS c FROM t ORDER BY c', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        $fact = $operation->facts->scalar($statement->orderBy[0]->expression);
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($operation->field(0), $fact->resolution->field);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheQualifierPartsAndTheName(): void
    {
        $operation = (new Inspection())->diagnose('select a AS c1, t.b AS c2, main.t.a AS c3, [c] AS c4, `d` AS c5 from t');

        self::assertSame('SELECT a AS c1, t.b AS c2, main.t.a AS c3, c AS c4, d AS c5 FROM t', $operation->toString());
    }

    public function testRenderQuotesANameThatNeedsIt(): void
    {
        $semantics = new Inspection();
        $operation = new InspectedStatement($semantics->context(), new Select([new ResultColumn(new ColumnUse(new Name('select'), new QualifiedName(new Name('my table'), new Name('main'))))]));

        self::assertSame('SELECT main.`my table`.`select`', $operation->toString());
    }
}
