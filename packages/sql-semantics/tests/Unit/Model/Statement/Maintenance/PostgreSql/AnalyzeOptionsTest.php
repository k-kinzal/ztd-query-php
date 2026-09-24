<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeOptions;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit;

#[CoversClass(AnalyzeOptions::class)]
final class AnalyzeOptionsTest extends TestCase
{
    public function testKeepsTheRequestedOptions(): void
    {
        $options = new AnalyzeOptions(true, true, new BufferUsageLimit('0'));
        self::assertTrue($options->verbose);
        self::assertTrue($options->skipLocked);
        self::assertSame(0, $options->bufferUsageLimit?->kilobytes);
        self::assertNull((new AnalyzeOptions())->bufferUsageLimit);
    }
}
