<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\LinkedBody;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(LinkedBody::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class LinkedBodyTest extends TestCase
{
    public function testRetainsTheFileAndSymbol(): void
    {
        $file = Expression::literal('lib', Dialect::PostgreSql);
        $symbol = Expression::literal('sym', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $file);
        self::assertInstanceOf(Literal::class, $symbol);
        $body = new LinkedBody($file, $symbol);
        self::assertSame(["'lib'", "'sym'"], [$body->file->text, $body->symbol->text]);
    }

    public function testRejectsASymbolOfAnotherDatabaseLanguage(): void
    {
        $file = Expression::literal('lib', Dialect::PostgreSql);
        $symbol = Expression::literal('sym', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $file);
        self::assertInstanceOf(Literal::class, $symbol);
        $this->expectException(InvalidStructure::class);
        new LinkedBody($file, $symbol);
    }
}
