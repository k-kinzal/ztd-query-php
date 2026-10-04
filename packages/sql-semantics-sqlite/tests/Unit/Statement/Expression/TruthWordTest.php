<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TruthWord::class)]
#[Medium]
final class TruthWordTest extends TestCase
{
    public function testDeriveScalarReadsTheConstantWhenNoColumnCanResolve(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $withTable = $semantics->analyze('SELECT true, FALSE FROM t', [$create]);
        $withoutTable = $semantics->analyze('SELECT true', []);
        $statement = $withTable->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(TruthWord::class, $statement->columns[0]->expression);
        self::assertInstanceOf(TruthWord::class, $statement->columns[1]->expression);
        self::assertTrue($statement->columns[0]->expression->value);
        self::assertFalse($statement->columns[1]->expression->value);
        $fact = $withTable->facts->scalar($statement->columns[1]->expression);
        self::assertEquals(new Known(Storage::Integer), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertEquals(new Known(Storage::Integer), $withoutTable->field(0)->type);
        self::assertSame(Nullability::NotNull, $withoutTable->field(0)->nullability);
        self::assertSame([], $withTable->facts->diagnostics);
    }

    public function testDeriveScalarResolvesAColumnNamedTrueOrFalse(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t2 (true INTEGER NOT NULL, "false" TEXT)');
        $operation = $semantics->analyze('SELECT true, false FROM t2', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame($create->declarations()[0]->columns[0], $fact->resolution->slot->column);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame($create->declarations()[0]->columns[1], $operation->field(1)->column());
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
    }

    public function testDeriveScalarDependsOnAnUndeclaredRelation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT true FROM u');
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(ConditionalColumn::class, $fact->resolution);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertInstanceOf(UndeclaredRelation::class, $fact->type->missing[0]);
        self::assertSame(Nullability::Dependent, $fact->nullability);
    }

    public function testRenderWritesTheBareWordInUpperCase(): void
    {
        self::assertSame('SELECT TRUE, FALSE', (new Semantics(Dialect::Sqlite))->analyze('select true, false')->toString());
    }

    public function testRenderWritesANewlyBuiltWord(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new TruthWord(false)), new ResultColumn(new TruthWord(true))]));

        self::assertSame('SELECT FALSE, TRUE', $operation->toString());
        self::assertEquals(new Known(Storage::Integer), $operation->field(0)->type);
    }
}
