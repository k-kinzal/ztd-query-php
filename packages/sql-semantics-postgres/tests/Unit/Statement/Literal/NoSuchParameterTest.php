<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NoSuchParameter;

#[CoversClass(NoSuchParameter::class)]
#[Small]
final class NoSuchParameterTest extends TestCase
{
    public function testMessageNamesTheMarker(): void
    {
        self::assertSame('There is no parameter $0.', (new NoSuchParameter('$0'))->message());
    }
}
