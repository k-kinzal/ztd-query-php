<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OptionRules::class)]
#[Medium]
final class OptionRulesTest extends TestCase
{
    public function testWritableAcceptsWordsInTheOrderOfTheGrammar(): void
    {
        self::assertTrue((new OptionRules())->writable([new UtilityOption(new Name('full')), new UtilityOption(OptionKeyword::Analyse)], OptionSyntax::Words, ['full', 'freeze', 'verbose', 'analyze']));
    }

    public function testWritableRefusesWhatTheWordSyntaxCannotHold(): void
    {
        $rules = new OptionRules();
        self::assertSame(
            [false, false, false, false, false],
            [
                $rules->writable([new UtilityOption(new Name('verbose')), new UtilityOption(new Name('full'))], OptionSyntax::Words, ['full', 'verbose']),
                $rules->writable([new UtilityOption(new Name('full')), new UtilityOption(new Name('full'))], OptionSyntax::Words, ['full', 'verbose']),
                $rules->writable([new UtilityOption(new Name('full'), Toggle::True)], OptionSyntax::Words, ['full']),
                $rules->writable([new UtilityOption(new Name('analyze'))], OptionSyntax::Words, ['analyze']),
                $rules->writable([], OptionSyntax::Parenthesized, ['full']),
            ],
        );
    }

    public function testWriteWritesEachSyntax(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['VACUUM FULL FREEZE VERBOSE ANALYSE', 'VACUUM (verbose, parallel 2) t', 'VACUUM t'],
            [$semantics->analyze('VACUUM FULL FREEZE VERBOSE ANALYSE')->toString(), $semantics->analyze('VACUUM (VERBOSE, PARALLEL 2) t')->toString(), $semantics->analyze('VACUUM t')->toString()],
        );
    }

    public function testFindAnswersTheLastOccurrence(): void
    {
        $last = new UtilityOption(new Name('verbose'), Toggle::False);
        self::assertSame([$last, null], [(new OptionRules())->find([new UtilityOption(new Name('verbose')), $last], 'verbose'), (new OptionRules())->find([$last], 'full')]);
    }

    public function testKnownAddsTheOptionsOf17(): void
    {
        self::assertSame(
            [false, true, ['verbose']],
            [in_array('memory', (new OptionRules())->known('EXPLAIN', GrammarRelease::PostgreSql166), true), in_array('memory', (new OptionRules())->known('EXPLAIN', GrammarRelease::PostgreSql172), true), (new OptionRules())->known('CLUSTER', GrammarRelease::PostgreSql172)],
        );
    }

    public function testDeriveReportsUnknownOptionsAndUnreadableValues(): void
    {
        self::assertSame(
            ['unrecognized ANALYZE option "full"', 'verbose requires a Boolean value', 'buffer_usage_limit requires a parameter'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze("ANALYZE (FULL, VERBOSE 'yes', SKIP_LOCKED 0, BUFFER_USAGE_LIMIT) t")->facts->diagnostics),
        );
    }

    public function testDeriveComparesNamesAfterFolding(): void
    {
        self::assertSame(
            ['unrecognized CLUSTER option "Verbose"'],
            array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('CLUSTER (VERBOSE, "Verbose") t')->facts->diagnostics),
        );
    }
}
