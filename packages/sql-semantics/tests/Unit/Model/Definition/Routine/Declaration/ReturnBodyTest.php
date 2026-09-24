<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\ReturnBody;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ReturnBody::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\Medium]
final class ReturnBodyTest extends TestCase
{
    public function testRetainsTheReturnedValue(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame($value, (new ReturnBody($value))->value);
    }

    public function testRejectsAValueOfAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        new ReturnBody(Expression::literal(1, Dialect::Sqlite));
    }
}
