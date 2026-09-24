<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\IndexCleanup;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumOptions;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(VacuumOptions::class)]
#[\PHPUnit\Framework\Attributes\Medium]
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

    public function testDefaultsLeaveEveryFlagOff(): void
    {
        $options = new VacuumOptions();
        self::assertSame([false, false, false, false, false, false, false, false], [$options->verbose, $options->skipLocked, $options->analyze, $options->freeze, $options->full, $options->disablePageSkipping, $options->skipDatabaseStats, $options->onlyDatabaseStats]);
    }

    public function testAcceptsTheWorkerCountLimitAndAFullVacuumWithoutParallelism(): void
    {
        self::assertSame(1024, (new VacuumOptions(parallel: 1024))->parallel);
        self::assertNull((new VacuumOptions(full: true))->parallel);
    }

    public function testAcceptsOnlyDatabaseStatisticsAlone(): void
    {
        self::assertTrue((new VacuumOptions(onlyDatabaseStats: true))->onlyDatabaseStats);
    }

    public function testRejectsAParallelFullVacuum(): void
    {
        $this->expectException(InvalidStructure::class);
        new VacuumOptions(full: true, parallel: 1);
    }

    public function testRejectsAFullVacuumThatDisablesPageSkipping(): void
    {
        $this->expectException(InvalidStructure::class);
        new VacuumOptions(full: true, disablePageSkipping: true);
    }

    #[TestWith([true, false, false, false, false])]
    #[TestWith([false, true, false, false, false])]
    #[TestWith([false, false, true, false, false])]
    #[TestWith([false, false, false, true, false])]
    #[TestWith([false, false, false, false, true])]
    public function testRejectsDatabaseStatisticsWithEachOtherOption(bool $analyze, bool $freeze, bool $full, bool $disablePageSkipping, bool $skipDatabaseStats): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('ONLY_DATABASE_STATS cannot be combined with other VACUUM options.');
        new VacuumOptions(analyze: $analyze, freeze: $freeze, full: $full, disablePageSkipping: $disablePageSkipping, skipDatabaseStats: $skipDatabaseStats, onlyDatabaseStats: true);
    }
}
