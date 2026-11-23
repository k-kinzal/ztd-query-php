<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(TruncateLowering::class)]
#[Medium]
final class TruncateLoweringTest extends TestCase
{
    public function testLowerRetainsBothDescendantChoicesAndTheNativeEffectFlags(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.entries (id int)']);
        $result = $semantics->analyze('TRUNCATE ONLY entries, entries * CONTINUE IDENTITY CASCADE', $context);

        self::assertInstanceOf(TruncateTable::class, $result);
        self::assertSame([true, false], array_column($result->targets, 'only'));
        self::assertSame(['public', 'entries'], [$result->targets[0]->table->schema->value, $result->targets[0]->table->table->value]);
        self::assertFalse($result->restartIdentity);
        self::assertTrue($result->cascade);
    }
}
