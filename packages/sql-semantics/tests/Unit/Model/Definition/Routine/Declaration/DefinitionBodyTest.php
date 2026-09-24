<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\DefinitionBody;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DefinitionBody::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class DefinitionBodyTest extends TestCase
{
    public function testRetainsTheDefinitionString(): void
    {
        $text = Expression::literal('SELECT 1', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $text);
        self::assertSame("'SELECT 1'", (new DefinitionBody($text))->definition->text);
    }

    public function testRejectsANumber(): void
    {
        $number = Expression::literal(1, Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new DefinitionBody($number);
    }
}
