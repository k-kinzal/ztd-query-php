<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Text\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Text\Json\Failure;

#[CoversClass(Failure::class)]
#[Small]
final class FailureTest extends TestCase
{
    public function testFailureRetainsTheRuleAndDecodedByteBoundary(): void
    {
        $failure = new Failure('Invalid value.', 3);
        self::assertSame('Invalid value.', $failure->getMessage());
        self::assertSame(3, $failure->position);
        self::assertFalse($failure->deep);
    }
}
