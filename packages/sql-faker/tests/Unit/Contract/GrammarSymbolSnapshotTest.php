<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GrammarSymbolSnapshot;

#[CoversClass(GrammarSymbolSnapshot::class)]
final class GrammarSymbolSnapshotTest extends TestCase
{
    public function testConstructsReadonlyGrammarSymbolSnapshot(): void
    {
        $symbol = new GrammarSymbolSnapshot('expr', true);

        self::assertSame('expr', $symbol->value);
        self::assertTrue($symbol->isNonTerminal);
    }
}
