<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\IndexCleanup;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumOptions;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(VacuumOptions::class)]
final class VacuumOptionsTest extends TestCase
{
    public function testDefaultsFollowTheServer(): void
    {
        $options = new VacuumOptions();
        self::assertTrue($options->processMain);
        self::assertTrue($options->processToast);
        self::assertNull($options->truncate);
        self::assertNull($options->indexCleanup);
        self::assertNull($options->parallel);
    }

    public function testAcceptsAFullVacuumWithParallelismDisabled(): void
    {
        $options = new VacuumOptions(full: true, parallel: 0, indexCleanup: IndexCleanup::Off);
        self::assertSame(0, $options->parallel);
    }

    public function testRejectsAWorkerCountOutsideTheRange(): void
    {
        $this->expectException(InvalidStructure::class);
        new VacuumOptions(parallel: 1025);
    }

    public function testRejectsABufferLimitForAFullVacuum(): void
    {
        $this->expectException(InvalidStructure::class);
        new VacuumOptions(full: true, bufferUsageLimit: new BufferUsageLimit('1MB'));
    }

    public function testRejectsAFullVacuumWithoutTheToastTable(): void
    {
        $this->expectException(InvalidStructure::class);
        new VacuumOptions(full: true, processToast: false);
    }

    public function testRejectsDatabaseStatisticsWithOtherWork(): void
    {
        $this->expectException(InvalidStructure::class);
        new VacuumOptions(onlyDatabaseStats: true, skipLocked: true);
    }
}
