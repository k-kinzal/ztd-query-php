<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Definition\Statistics\StatisticsKind;

#[CoversClass(StatisticsKind::class)]
#[Small]
final class StatisticsKindTest extends TestCase
{
    public function testSpellsEachKindAsTheServerNamesIt(): void
    {
        self::assertSame(['ndistinct', 'dependencies', 'mcv'], array_column(StatisticsKind::cases(), 'value'));
    }
}
