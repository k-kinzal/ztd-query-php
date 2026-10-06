<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationObstacle;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\AlterationRefused;

#[CoversClass(AlterationRefused::class)]
#[Small]
final class AlterationRefusedTest extends TestCase
{
    public function testMessageDescribesTheObstacle(): void
    {
        self::assertSame('The only column of a table cannot be dropped.', (new AlterationRefused(AlterationObstacle::LastColumn))->message());
    }
}
