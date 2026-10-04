<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationObstacle;

#[CoversClass(AlterationObstacle::class)]
#[Small]
final class AlterationObstacleTest extends TestCase
{
    public function testCasesDescribeEachObstacle(): void
    {
        self::assertSame(['PrimaryKeyColumn', 'UniqueColumn', 'StoredColumn', 'NotNullWithoutDefault', 'DefaultNotConstant', 'LastColumn'], array_column(AlterationObstacle::cases(), 'name'));
        self::assertSame('A UNIQUE column cannot be added.', AlterationObstacle::UniqueColumn->value);
    }
}
