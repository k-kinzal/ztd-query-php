<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Scalar\Conditional\JsonItemKind;

#[CoversClass(JsonItemKind::class)]
final class JsonItemKindTest extends TestCase
{
    public function testCasesAreSpelledAsTheirKeywords(): void
    {
        self::assertSame(['VALUE', 'ARRAY', 'OBJECT', 'SCALAR'], array_map(static fn (JsonItemKind $type): string => $type->value, JsonItemKind::cases()));
    }
}
