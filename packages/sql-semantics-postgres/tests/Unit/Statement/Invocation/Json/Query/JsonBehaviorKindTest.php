<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;

#[CoversClass(JsonBehaviorKind::class)]
#[Small]
final class JsonBehaviorKindTest extends TestCase
{
    public function testCasesSpellTheBehaviors(): void
    {
        self::assertSame('EMPTY ARRAY', JsonBehaviorKind::EmptyArray->value);
        self::assertCount(9, JsonBehaviorKind::cases());
    }
}
