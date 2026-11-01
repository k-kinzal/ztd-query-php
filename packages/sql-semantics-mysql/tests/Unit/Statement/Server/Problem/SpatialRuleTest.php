<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;

#[CoversClass(SpatialRule::class)]
#[Small]
final class SpatialRuleTest extends TestCase
{
    public function testCasesHoldTheServerErrors(): void
    {
        self::assertSame('ER_SRS_ATTRIBUTE_STRING_TOO_LONG', SpatialRule::TooLong->value);
        self::assertSame('ER_WRONG_VALUE', SpatialRule::LineFeed->value);
        self::assertCount(9, SpatialRule::cases());
    }
}
