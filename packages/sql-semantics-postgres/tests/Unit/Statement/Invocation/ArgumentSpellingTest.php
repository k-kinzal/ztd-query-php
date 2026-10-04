<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\ArgumentSpelling;

#[CoversClass(ArgumentSpelling::class)]
#[Small]
final class ArgumentSpellingTest extends TestCase
{
    public function testCasesSpellTheJoiningSymbols(): void
    {
        self::assertSame(['=>', ':='], array_map(static fn (ArgumentSpelling $spelling): string => $spelling->value, ArgumentSpelling::cases()));
    }
}
