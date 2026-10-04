<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;

#[CoversClass(JsonNullHandling::class)]
#[Small]
final class JsonNullHandlingTest extends TestCase
{
    public function testCasesSpellTheClauses(): void
    {
        self::assertSame(['NULL', 'ABSENT'], array_map(static fn (JsonNullHandling $nulls): string => $nulls->value, JsonNullHandling::cases()));
    }
}
