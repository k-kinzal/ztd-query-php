<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

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
use SqlFaker\PostgreSql\Generation\Rewrite\Name\AliasRule;

#[CoversClass(AliasRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class AliasRuleTest extends TestCase
{
    #[DataProvider('providerAliases')]
    public function testRewriteMakesAliasesExplicitAndRetainsOriginalSubtrees(string $name, bool $explicit): void
    {
        $trace = new DerivationTrace('relation_expr_opt_alias');
        $trace->expand(0, new Production([new Terminal('RELATION'), ...($explicit ? [new Terminal('AS')] : []), new NonTerminal('ColId')]), 0);
        $trace->expand($explicit ? 2 : 1, new Production([new Terminal($name)]), 0);
        $input = $trace->terminals();
        $result = (new AliasRule())->rewrite($input);
        self::assertSame(['RELATION', 'AS', $name], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[count($input->terminals) - 1], $result->terminals[2]);
        self::assertSame($result, (new AliasRule())->rewrite($result));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerAliases(): array
    {
        return [['SET', false], ['SET', true], ['IDENT', false], ['IDENT', true]];
    }

    #[DataProvider('providerUnaliasedRoots')]
    public function testRewritePreservesRelationsWithoutAnAliasAndUnrelatedNames(string $root): void
    {
        $trace = new DerivationTrace($root);
        $trace->expand(0, new Production([new Terminal('SET')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new AliasRule())->rewrite($input));
    }

    /**
     * @return list<array{string}>
     */
    public static function providerUnaliasedRoots(): array
    {
        return [['relation_expr_opt_alias'], ['ordinary']];
    }
}
