<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillArgument;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillMode;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\SpillThreshold;

#[CoversClass(SpillArgument::class)]
#[Small]
final class SpillArgumentTest extends TestCase
{
    public function testReadKeepsDistinctSwitchEffectsForEqualIntegerPrefixes(): void
    {
        $decoder = new SpillArgument();
        $unsigned = $decoder->read('0x100');
        $signed = $decoder->read('+256');

        self::assertInstanceOf(SpillThreshold::class, $unsigned);
        self::assertInstanceOf(SpillThreshold::class, $signed);
        self::assertSame(256, $unsigned->amount->value);
        self::assertSame(256, $signed->amount->value);
        self::assertSame(SpillMode::Disabled, $unsigned->mode);
        self::assertSame(SpillMode::Enabled, $signed->mode);
        self::assertSame(SpillMode::Enabled, $decoder->read('RESET'));
        self::assertSame(SpillMode::Disabled, $decoder->read('0'));
    }
}
