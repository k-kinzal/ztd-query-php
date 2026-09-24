<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfileStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProfileCategory::class)]
#[Medium]
final class ProfileCategoryTest extends TestCase
{
    public function testMeasuredOrdersDistinctCategoriesAsTheServerReportsThem(): void
    {
        self::assertSame([], ProfileCategory::measured([]));
        self::assertSame([], ProfileCategory::measured([ProfileCategory::Memory]));
        self::assertSame([ProfileCategory::Cpu, ProfileCategory::Swaps], ProfileCategory::measured([ProfileCategory::Swaps, ProfileCategory::Cpu, ProfileCategory::Swaps]));
        self::assertSame([ProfileCategory::Cpu, ProfileCategory::ContextSwitches, ProfileCategory::BlockIo, ProfileCategory::Ipc, ProfileCategory::PageFaults, ProfileCategory::Swaps, ProfileCategory::Source], ProfileCategory::measured([ProfileCategory::Ipc, ProfileCategory::All]));
    }

    public function testMultiWordCategoriesBindFromTheirKeywordSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE BLOCK IO, CONTEXT SWITCHES, PAGE FAULTS');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertSame([ProfileCategory::BlockIo, ProfileCategory::ContextSwitches, ProfileCategory::PageFaults], $statement->categories);
        self::assertSame('SHOW PROFILE BLOCK IO, CONTEXT SWITCHES, PAGE FAULTS', $statement->toString());
    }
}
