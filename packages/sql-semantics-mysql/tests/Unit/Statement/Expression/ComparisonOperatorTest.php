<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;

#[CoversClass(ComparisonOperator::class)]
#[Small]
final class ComparisonOperatorTest extends TestCase
{
    public function testCasesSpellEveryComparisonOperator(): void
    {
        self::assertSame(['=', '<=>', '<>', '<', '<=', '>', '>='], array_column(ComparisonOperator::cases(), 'value'));
    }
}
