<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\GeneratedColumnRule;

#[CoversClass(GeneratedColumnRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class GeneratedColumnRuleTest extends TestCase
{
    /**
     * @param list<string> $when
     * @param list<string> $expected
     */
    #[DataProvider('providerGenerationModes')]
    public function testRewriteRequiresAlwaysForExpressionsAndPreservesIdentityModes(array $when, string $valueRule, array $expected): void
    {
        $trace = new DerivationTrace('ColConstraintElem');
        $trace->expand(0, new Production([new Terminal('GENERATED'), new NonTerminal('generated_when'), new Terminal('AS'), new NonTerminal($valueRule)]), 0);
        $trace->expand(1, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $when)), 0);
        $trace->expand(2 + count($when), new Production([new Terminal('VALUE')]), 0);
        $input = $trace->terminals();
        $rule = new GeneratedColumnRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->terminals[count($input->terminals) - 1], $result->terminals[count($result->terminals) - 1]);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{list<string>, string, list<string>}>
     */
    public static function providerGenerationModes(): iterable
    {
        yield 'expression by default' => [['BY', 'DEFAULT'], 'a_expr', ['GENERATED', 'ALWAYS', 'AS', 'VALUE']];
        yield 'expression always' => [['ALWAYS'], 'a_expr', ['GENERATED', 'ALWAYS', 'AS', 'VALUE']];
        yield 'identity by default' => [['BY', 'DEFAULT'], 'OptParenthesizedSeqOptList', ['GENERATED', 'BY', 'DEFAULT', 'AS', 'VALUE']];
        yield 'identity always' => [['ALWAYS'], 'OptParenthesizedSeqOptList', ['GENERATED', 'ALWAYS', 'AS', 'VALUE']];
        yield 'removed timing' => [[], 'a_expr', ['GENERATED', 'AS', 'VALUE']];
    }

    public function testRewritePreservesOtherColumnConstraints(): void
    {
        $trace = new DerivationTrace('ColConstraintElem');
        $trace->expand(0, new Production([new Terminal('CHECK'), new NonTerminal('a_expr')]), 0);
        $trace->expand(1, new Production([new Terminal('VALUE')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new GeneratedColumnRule())->rewrite($input));
    }
}
