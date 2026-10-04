<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CaseExpression::class)]
#[Medium]
final class CaseExpressionTest extends TestCase
{
    public function testDeriveScalarChoosesAmongTheResultTypesAndIsNotNullWithElse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze("SELECT CASE a WHEN 1 THEN 'x' ELSE 2 END FROM t", [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(CaseExpression::class, $statement->columns[0]->expression);
        $case = $statement->columns[0]->expression;
        self::assertInstanceOf(ColumnUse::class, $case->base);
        self::assertInstanceOf(IntegerLiteral::class, $case->branches[0]->when);
        self::assertInstanceOf(TextLiteral::class, $case->branches[0]->then);
        self::assertInstanceOf(IntegerLiteral::class, $case->otherwise);
        self::assertEquals(new Choice([Storage::Integer, Storage::Text]), $operation->facts->scalar($case)->type);
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($case)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarIsNullableWithoutElse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT CASE WHEN a > 1 THEN 1 END FROM t', [$create]);

        self::assertEquals(new Known(Storage::Integer), $operation->field(0)->type);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
    }

    public function testDeriveScalarIsNullableWhenAResultCanBeNull(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $withNullResult = $semantics->analyze('SELECT CASE WHEN a THEN 1 ELSE NULL END FROM t', [$create]);
        $withNullableColumn = $semantics->analyze('SELECT CASE WHEN 1 THEN b ELSE b END FROM t', [$create]);

        self::assertEquals(new Known(Storage::Integer), $withNullResult->field(0)->type);
        self::assertSame(Nullability::Nullable, $withNullResult->field(0)->nullability);
        self::assertEquals(new Choice([Storage::Text, Storage::Blob]), $withNullableColumn->field(0)->type);
        self::assertSame(Nullability::Nullable, $withNullableColumn->field(0)->nullability);
    }

    public function testDeriveScalarMergesEqualResultTypesIntoOne(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE 1 WHEN 1 THEN 1 WHEN 2 THEN 2 ELSE 3 END', []);

        self::assertEquals(new Known(Storage::Integer), $operation->field(0)->type);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testDeriveScalarDependsOnAParameterResult(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE WHEN 1 THEN ? END', []);

        self::assertEquals(new Dependent([new UnboundParameter('?')]), $operation->field(0)->type);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
    }

    public function testDeriveScalarReportsARowValueCondition(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE WHEN (1, 2) THEN 1 END', []);

        self::assertEquals([new Misuse(MisuseRule::TooManyValueColumns)], $operation->facts->diagnostics);
        self::assertSame('row value misused', $operation->facts->diagnostics[0]->message());
        self::assertEquals(new Known(Storage::Integer), $operation->field(0)->type);
    }

    public function testDeriveScalarReportsARowValueResult(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE WHEN 1 THEN (1, 2) END', []);

        self::assertEquals([new Misuse(MisuseRule::TooManyValueColumns)], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsARowValueComparedWithASingleBase(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE 1 WHEN (1, 2) THEN 1 END', []);

        self::assertEquals([new Misuse(MisuseRule::TooManyValueColumns)], $operation->facts->diagnostics);
    }

    public function testDeriveScalarAcceptsRowValuesOfTheSameWidthAroundABase(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT CASE (1, 2) WHEN (1, 2) THEN 1 END', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertEquals(new Known(Storage::Integer), $operation->field(0)->type);
    }

    public function testRenderWritesEveryPartInOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("select case a when 1 then 'x' when 2 then 'y' else 'z' end, case when a then 1 end from t");

        self::assertSame("SELECT CASE a WHEN 1 THEN 'x' WHEN 2 THEN 'y' ELSE 'z' END, CASE WHEN a THEN 1 END FROM t", $operation->toString());
    }
}
