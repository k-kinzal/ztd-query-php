<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DoubleQuotedWord::class)]
#[Medium]
final class DoubleQuotedWordTest extends TestCase
{
    public function testDeriveScalarResolvesADeclaredColumn(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT "a", "b" FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(DoubleQuotedWord::class, $statement->columns[0]->expression);
        self::assertSame('a', $statement->columns[0]->expression->word->value);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame($create->declarations()[0]->columns[0], $fact->resolution->slot->column);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame($create->declarations()[0]->columns[1], $operation->field(1)->column());
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReadsTheWordAsTextWhenNoColumnCanResolve(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $withTable = $semantics->analyze('SELECT "zzz", "a b" FROM t', [$create]);
        $withoutTable = $semantics->analyze('SELECT "zzz"', []);
        $statement = $withTable->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        $fact = $withTable->facts->scalar($statement->columns[1]->expression);
        self::assertEquals(new Known(Storage::Text), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertEquals(new Known(Storage::Text), $withTable->field(0)->type);
        self::assertEquals(new Known(Storage::Text), $withoutTable->field(0)->type);
        self::assertSame(Nullability::NotNull, $withoutTable->field(0)->nullability);
        self::assertSame([], $withTable->facts->diagnostics);
    }

    public function testDeriveScalarDependsOnAnUndeclaredRelation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT "zzz" FROM u');
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(ConditionalColumn::class, $fact->resolution);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertInstanceOf(UndeclaredRelation::class, $fact->type->missing[0]);
        self::assertSame(Nullability::Dependent, $fact->nullability);
    }

    public function testDeriveScalarIsTextWithoutAnyRelationInAnOpenContext(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT "zzz"');

        self::assertEquals(new Known(Storage::Text), $operation->field(0)->type);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testDeriveScalarResolvesAResultAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT a AS x FROM t ORDER BY "x"', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        $fact = $operation->facts->scalar($statement->orderBy[0]->expression);
        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($operation->field(0), $fact->resolution->field);
    }

    public function testRenderKeepsTheDoubleQuotes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');

        self::assertSame('SELECT "a", "b", "zzz" FROM t', $semantics->analyze('select "a", "b", "zzz" from t', [$create])->toString());
    }

    public function testRenderDoublesAnEmbeddedDoubleQuote(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new DoubleQuotedWord(new Name('say "hi"')))]));

        self::assertSame('SELECT "say ""hi"""', $operation->toString());
        self::assertEquals(new Known(Storage::Text), $operation->field(0)->type);
    }
}
