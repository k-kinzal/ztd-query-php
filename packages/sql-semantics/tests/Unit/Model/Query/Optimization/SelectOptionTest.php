<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Optimization\SelectOption;

#[CoversClass(SelectOption::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class SelectOptionTest extends TestCase
{
    #[TestWith([SelectOption::HighPriority, 'mysql-8.4.7', true])]
    #[TestWith([SelectOption::CalcFoundRows, 'mysql-8.4.7', true])]
    #[TestWith([SelectOption::BufferResult, null, true])]
    #[TestWith([SelectOption::Cache, 'mysql-5.7.44', true])]
    #[TestWith([SelectOption::NoCache, 'mysql-5.6.51', true])]
    #[TestWith([SelectOption::NoCache, 'mysql-8.0.44', false])]
    #[TestWith([SelectOption::StraightJoin, 'mysql-5.7.44', false])]
    #[TestWith([SelectOption::SmallResult, 'mysql-8.4.7', false])]
    #[TestWith([SelectOption::BigResult, 'mysql-8.4.7', false])]
    public function testFirstBlockOnlyFollowsTheServerPlacementRules(SelectOption $option, ?string $release, bool $expected): void
    {
        self::assertSame($expected, $option->firstBlockOnly($release));
    }
}
