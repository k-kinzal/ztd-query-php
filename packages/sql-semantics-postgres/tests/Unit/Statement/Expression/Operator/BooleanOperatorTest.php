<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\BooleanOperator;

#[CoversClass(BooleanOperator::class)]
#[Small]
final class BooleanOperatorTest extends TestCase
{
    public function testCasesAreAndAndOr(): void
    {
        self::assertSame([BooleanOperator::And, BooleanOperator::Or], BooleanOperator::cases());
    }
}
