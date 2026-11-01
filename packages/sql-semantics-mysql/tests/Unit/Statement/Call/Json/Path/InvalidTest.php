<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json\Path;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Invalid;

#[CoversClass(Invalid::class)]
#[Small]
final class InvalidTest extends TestCase
{
    public function testReadPreservesTheOperandMeaning(): void
    {
        $path = Path::read('$[');
        self::assertInstanceOf(Invalid::class, $path);
        self::assertSame(2, $path->position);
    }
}
