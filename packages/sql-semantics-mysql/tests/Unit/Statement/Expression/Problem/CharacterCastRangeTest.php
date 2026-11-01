<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange;

#[CoversClass(CharacterCastRange::class)]
#[Small]
final class CharacterCastRangeTest extends TestCase
{
    public function testMessageNeedsNeitherSourceTextNorRuntimeValues(): void
    {
        self::assertSame("char(n) value is out of range in 'cast_as_char'", (new CharacterCastRange())->message());
    }
}
