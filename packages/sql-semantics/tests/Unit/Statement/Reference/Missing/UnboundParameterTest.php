<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference\Missing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;

#[CoversClass(UnboundParameter::class)]
#[Small]
final class UnboundParameterTest extends TestCase
{
    public function testDescribeNamesTheMarker(): void
    {
        $missing = new UnboundParameter(':name');

        self::assertSame('the value bound to parameter :name', $missing->describe());
        self::assertSame(':name', $missing->marker);
    }
}
