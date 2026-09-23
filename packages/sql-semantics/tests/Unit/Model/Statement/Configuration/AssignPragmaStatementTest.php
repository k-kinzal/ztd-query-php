<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Pragma\NumericArgument;
use SqlSemantics\Model\Configuration\Pragma\Sign;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AssignPragmaStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class AssignPragmaStatementTest extends TestCase
{
    public function testWithValuePreservesTheScalarArgumentGrammar(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $original = $binder->bind('PRAGMA main.cache_size=-2000');
        self::assertInstanceOf(AssignPragmaStatement::class, $original);
        self::assertInstanceOf(NumericArgument::class, $original->value);
        self::assertSame(Sign::Negative, $original->value->sign);
        self::assertSame('2000', $original->value->literal->text);
        $literal = Expression::literal(4000, Dialect::Sqlite);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $literal);
        $replacement = new NumericArgument($literal, Sign::Negative);
        $changed = $original->withValue($replacement);
        self::assertSame('PRAGMA "main"."cache_size" = - 2000', $original->toString());
        self::assertSame('PRAGMA "main"."cache_size" = - 4000', $changed->toString());
        self::assertInstanceOf(AssignPragmaStatement::class, $binder->bind($changed->toString()));
    }
}
