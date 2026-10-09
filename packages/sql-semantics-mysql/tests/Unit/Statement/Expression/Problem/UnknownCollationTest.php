<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;

#[CoversClass(UnknownCollation::class)]
#[Small]
final class UnknownCollationTest extends TestCase
{
    public function testMessageNamesWhatTheServerLacks(): void
    {
        self::assertSame("Unknown collation: 'klingon_ci'", (new UnknownCollation('klingon_ci'))->message());
    }

    public function testMessageQuotesAtMost64BytesOfAMultibyteName(): void
    {
        self::assertSame("Unknown collation: '" . str_repeat('é', 32) . "'", (new UnknownCollation(str_repeat('é', 70)))->message());
    }

    public function testMessageQuotesTheFirst64BytesOfTheName(): void
    {
        self::assertSame("Unknown collation: '" . str_repeat('x', 64) . "'", (new UnknownCollation(str_repeat('x', 70)))->message());
    }
}
