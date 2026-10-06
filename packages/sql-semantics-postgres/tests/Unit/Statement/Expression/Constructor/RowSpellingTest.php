<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling;

#[CoversClass(RowSpelling::class)]
#[Small]
final class RowSpellingTest extends TestCase
{
    public function testCasesAreTheTwoSpellings(): void
    {
        self::assertSame([RowSpelling::Explicit, RowSpelling::Implicit], RowSpelling::cases());
    }
}
