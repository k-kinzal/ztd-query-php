<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;

#[CoversClass(LogicalOperator::class)]
#[Small]
final class LogicalOperatorTest extends TestCase
{
    public function testCasesAreAvailableForTheDialect(): void
    {
        self::assertSame([LogicalOperator::And, LogicalOperator::Or, LogicalOperator::Not], LogicalOperator::cases());
    }
}
