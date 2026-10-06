<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount;

#[CoversClass(ViewColumnCount::class)]
#[Small]
final class ViewColumnCountTest extends TestCase
{
    public function testMessageNamesBothCounts(): void
    {
        self::assertSame("View's SELECT and view's field list have different column counts (2 and 1).", (new ViewColumnCount(2, 1))->message());
    }
}
