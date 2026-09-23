<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Modifier\TextParameter;

#[CoversClass(TextParameter::class)]
#[Medium]
final class TextParameterTest extends TestCase
{
    #[TestWith([Dialect::MySql, '12'])]
    #[TestWith([Dialect::PostgreSql, 12])]
    public function testRejectsAnIncompatibleLiteral(Dialect $dialect, string|int $input): void
    {
        $literal = Expression::literal($input, $dialect);
        self::assertInstanceOf(Literal::class, $literal);
        $this->expectException(InvalidStructure::class);
        new TextParameter($literal);
    }

}
