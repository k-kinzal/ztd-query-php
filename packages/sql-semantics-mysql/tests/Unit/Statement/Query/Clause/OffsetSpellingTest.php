<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling;

#[CoversClass(OffsetSpelling::class)]
#[Small]
final class OffsetSpellingTest extends TestCase
{
    public function testCasesNameTheTwoSpellings(): void
    {
        self::assertSame(['Comma', 'Keyword'], array_column(OffsetSpelling::cases(), 'name'));
    }
}
