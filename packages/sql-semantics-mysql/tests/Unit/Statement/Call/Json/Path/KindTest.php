<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json\Path;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind;

#[CoversClass(Kind::class)]
#[Small]
final class KindTest extends TestCase
{
    public function testReadPreservesTheOperandMeaning(): void
    {
        $path = Path::read('$.a.*[1][*][0 to last]**.b');
        self::assertInstanceOf(Path::class, $path);
        self::assertSame(Kind::cases(), array_slice(array_map(static fn ($leg): Kind => $leg->kind, $path->legs), 0, 6));
    }
}
