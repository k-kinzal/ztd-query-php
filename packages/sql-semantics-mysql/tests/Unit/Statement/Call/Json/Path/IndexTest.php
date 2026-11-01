<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json\Path;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index;

#[CoversClass(Index::class)]
#[Small]
final class IndexTest extends TestCase
{
    public function testReadPreservesTheOperandMeaning(): void
    {
        $path = Path::read('$[last-3]');
        self::assertInstanceOf(Path::class, $path);
        $index = $path->legs[0]->from;
        self::assertInstanceOf(Index::class, $index);
        self::assertSame([true, 3], [$index->last, $index->offset]);
    }
}
