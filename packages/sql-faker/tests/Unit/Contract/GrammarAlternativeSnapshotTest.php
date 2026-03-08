<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\GrammarAlternativeSnapshot;
use SqlFaker\Contract\GrammarSymbolSnapshot;

#[CoversClass(GrammarAlternativeSnapshot::class)]
#[UsesClass(GrammarSymbolSnapshot::class)]
final class GrammarAlternativeSnapshotTest extends TestCase
{
    public function testReferencesReturnsOnlyNonTerminalSymbols(): void
    {
        $alternative = new GrammarAlternativeSnapshot([
            new GrammarSymbolSnapshot('SELECT', false),
            new GrammarSymbolSnapshot('expr', true),
            new GrammarSymbolSnapshot('FROM', false),
            new GrammarSymbolSnapshot('table_ref', true),
        ]);

        self::assertSame(['expr', 'table_ref'], $alternative->references());
    }

    public function testSequencePrefixesTerminalKinds(): void
    {
        $alternative = new GrammarAlternativeSnapshot([
            new GrammarSymbolSnapshot('SELECT', false),
            new GrammarSymbolSnapshot('expr', true),
        ]);

        self::assertSame(['t:SELECT', 'nt:expr'], $alternative->sequence());
    }
}
