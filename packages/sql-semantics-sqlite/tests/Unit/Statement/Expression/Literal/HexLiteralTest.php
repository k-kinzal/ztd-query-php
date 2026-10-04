<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(HexLiteral::class)]
#[Medium]
final class HexLiteralTest extends TestCase
{
    public function testDeriveScalarGivesInteger(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 0x1f, 0xFFFFFFFFFFFFFFFF, 0x0000000000000000001', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(HexLiteral::class, $statement->columns[0]->expression);
        self::assertSame('1F', $statement->columns[0]->expression->digits);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertEquals(new Known(Storage::Integer), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertEquals(new Known(Storage::Integer), $operation->field(1)->type);
        self::assertEquals(new Known(Storage::Integer), $operation->field(2)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsALiteralWiderThanSixtyFourBits(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 0x10000000000000000', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertEquals(new Misuse(MisuseRule::HexLiteralTooBig), $fact->type->cause);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([$fact->type->cause], $operation->facts->diagnostics);
        self::assertSame('hex literal too big', $operation->facts->diagnostics[0]->message());
    }

    public function testRenderWritesThePrefixAndUpperCaseDigits(): void
    {
        self::assertSame('SELECT 0x1F, 0xABC', (new Semantics(Dialect::Sqlite))->analyze('select 0X1f, 0xabc')->toString());
    }

    public function testRenderWritesANewlyBuiltLiteral(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new HexLiteral('00FF'))]));

        self::assertSame('SELECT 0x00FF', $operation->toString());
    }
}
