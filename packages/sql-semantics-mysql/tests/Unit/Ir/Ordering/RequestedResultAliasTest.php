<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;

#[CoversClass(RequestedResultAlias::class)]
#[Small]
final class RequestedResultAliasTest extends TestCase
{
    public function testRequestedValueRetainsLeadingCharacters(): void
    {
        $requested = "\x7f  q";
        $alias = new RequestedResultAlias($requested);

        self::assertSame($requested, $alias->requested);
        self::assertSame('q', $alias->value);
    }

    public function testHeadingClipsAtTheLastCompleteCharacter(): void
    {
        $prefix = str_repeat('漢', 85);

        self::assertSame($prefix, (new RequestedResultAlias($prefix . '語'))->value);
        self::assertSame($prefix . 'x', (new RequestedResultAlias($prefix . 'x語'))->value);
    }

    public function testKeyUsesTheEffectiveName(): void
    {
        self::assertSame('amount', (new RequestedResultAlias(' AMOUNT'))->key());
    }

    public function testAliasSqlQuotesTheOriginalRequestedName(): void
    {
        self::assertSame('` amount``2026`', (new RequestedResultAlias(' amount`2026'))->aliasSql());
    }
}
