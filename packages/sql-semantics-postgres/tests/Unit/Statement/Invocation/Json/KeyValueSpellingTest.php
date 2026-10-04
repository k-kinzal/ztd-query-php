<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\KeyValueSpelling;

#[CoversClass(KeyValueSpelling::class)]
#[Small]
final class KeyValueSpellingTest extends TestCase
{
    public function testCasesSpellTheJoins(): void
    {
        self::assertSame(['VALUE', ':'], array_map(static fn (KeyValueSpelling $spelling): string => $spelling->value, KeyValueSpelling::cases()));
    }
}
