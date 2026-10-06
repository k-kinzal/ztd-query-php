<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Conditional\MinMaxKind;

#[CoversClass(MinMaxKind::class)]
#[Small]
final class MinMaxKindTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['GREATEST', 'LEAST'], array_map(static fn (MinMaxKind $kind): string => $kind->value, MinMaxKind::cases()));
    }
}
