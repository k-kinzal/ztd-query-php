<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;

#[CoversClass(SequenceLogging::class)]
final class SequenceLoggingTest extends TestCase
{
    public function testUsesTheTwoNativeModes(): void
    {
        self::assertSame(['LOGGED', 'UNLOGGED'], array_column(SequenceLogging::cases(), 'value'));
    }
}
