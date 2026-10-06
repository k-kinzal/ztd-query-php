<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(DefaultLiteral::class)]
#[Medium]
final class DefaultLiteralTest extends TestCase
{
    public function testDeriveConstraintDerivesTheLiteral(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DEFAULT 5)', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $default = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(DefaultLiteral::class, $default);
        self::assertInstanceOf(IntegerLiteral::class, $default->literal);
        self::assertEquals(new Known(Storage::Integer), $operation->facts->scalar($default->literal)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheWrittenSign(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $negative = $semantics->analyze('create table t (a default -1.5)');
        $positive = $semantics->analyze("create table t (a default +'x')");
        $statement = $negative->statement;
        $other = $positive->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(CreateTable::class, $other);
        self::assertInstanceOf(DefaultLiteral::class, $statement->columns[0]->constraints[0]);
        self::assertInstanceOf(DefaultLiteral::class, $other->columns[0]->constraints[0]);
        self::assertSame(NumberSign::Minus, $statement->columns[0]->constraints[0]->sign);
        self::assertSame(NumberSign::Plus, $other->columns[0]->constraints[0]->sign);
        self::assertSame('CREATE TABLE t (a DEFAULT - 1.5)', $negative->toString());
        self::assertSame("CREATE TABLE t (a DEFAULT + 'x')", $positive->toString());
    }

    public function testRenderWritesEveryKindOfLiteral(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a DEFAULT NULL, b DEFAULT 'x', c DEFAULT x'0A', d DEFAULT CURRENT_TIMESTAMP)");
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(DefaultLiteral::class, $statement->columns[3]->constraints[0]);
        self::assertInstanceOf(CurrentTime::class, $statement->columns[3]->constraints[0]->literal);
        self::assertSame("CREATE TABLE t (a DEFAULT NULL, b DEFAULT 'x', c DEFAULT x'0A', d DEFAULT CURRENT_TIMESTAMP)", $operation->toString());
    }
}
