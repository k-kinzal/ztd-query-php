<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;

#[CoversClass(Direction::class)]
#[Small]
final class DirectionTest extends TestCase
{
    public function testCasesSpellAscAndDesc(): void
    {
        self::assertSame(['ASC', 'DESC'], array_column(Direction::cases(), 'value'));
    }
}
