<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\SqliteUnaryOperator;

#[CoversClass(SqliteUnaryOperator::class)]
#[Small]
final class SqliteUnaryOperatorTest extends TestCase
{
    public function testTheValuePreservingPrefixIsNotArithmeticNegation(): void
    {
        self::assertNotSame(SqliteUnaryOperator::Plus, SqliteUnaryOperator::Negate);
    }
}
