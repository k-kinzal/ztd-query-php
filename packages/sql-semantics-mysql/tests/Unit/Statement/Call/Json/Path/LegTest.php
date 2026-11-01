<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json\Path;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg;

#[CoversClass(Leg::class)]
#[Small]
final class LegTest extends TestCase
{
    public function testReadPreservesTheOperandMeaning(): void
    {
        $path = Path::read('$[1 to last-2]');
        self::assertInstanceOf(Path::class, $path);
        $leg = $path->legs[0];
        self::assertNotNull($leg->from);
        self::assertNotNull($leg->to);
        self::assertSame([false, 1, true, 2], [$leg->from->last, $leg->from->offset, $leg->to->last, $leg->to->offset]);
    }
}
