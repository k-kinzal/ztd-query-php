<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Maintenance\ReindexOptions;

#[CoversClass(ReindexOptions::class)]
final class ReindexOptionsTest extends TestCase
{
    public function testDefaultsToAPlainRebuildWithoutATablespace(): void
    {
        $options = new ReindexOptions();
        self::assertFalse($options->concurrently);
        self::assertFalse($options->verbose);
        self::assertNull($options->tablespace);
    }

    public function testRetainsEveryRequestedPolicy(): void
    {
        $options = new ReindexOptions(concurrently: true, verbose: true, tablespace: 'fast');
        self::assertTrue($options->concurrently);
        self::assertTrue($options->verbose);
        self::assertSame('fast', $options->tablespace);
    }
}
