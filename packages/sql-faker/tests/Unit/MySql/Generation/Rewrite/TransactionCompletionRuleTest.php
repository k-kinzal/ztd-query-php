<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\TransactionCompletionRule;

#[CoversClass(TransactionCompletionRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class TransactionCompletionRuleTest extends TestCase
{
    /**
     * @param list<string> $chain
     * @param list<string> $release
     * @param list<string> $expected
     */
    #[DataProvider('providerCompletions')]
    public function testRewritePreservesValidCompletionsAndSeparatesChainFromRelease(string $root, array $chain, array $release, array $expected): void
    {
        $trace = new DerivationTrace($root);
        $trace->expand(0, new Production([new NonTerminal('opt_chain'), new NonTerminal('opt_release')]), 0);
        $trace->expand(0, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $chain)), 0);
        $trace->expand(count($chain), new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $release)), 0);
        $input = $trace->terminals();
        $rule = new TransactionCompletionRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>, list<string>}>
     */
    public static function providerCompletions(): iterable
    {
        foreach (['commit', 'rollback'] as $root) {
            yield $root . ' conflicting' => [$root, ['AND_SYM', 'CHAIN_SYM'], ['RELEASE_SYM'], ['AND_SYM', 'CHAIN_SYM', 'NO_SYM', 'RELEASE_SYM']];
            yield $root . ' no chain' => [$root, ['AND_SYM', 'NO_SYM', 'CHAIN_SYM'], ['RELEASE_SYM'], ['AND_SYM', 'NO_SYM', 'CHAIN_SYM', 'RELEASE_SYM']];
            yield $root . ' no release' => [$root, ['AND_SYM', 'CHAIN_SYM'], ['NO_SYM', 'RELEASE_SYM'], ['AND_SYM', 'CHAIN_SYM', 'NO_SYM', 'RELEASE_SYM']];
            yield $root . ' default chain' => [$root, [], ['RELEASE_SYM'], ['RELEASE_SYM']];
            yield $root . ' default release' => [$root, ['AND_SYM', 'CHAIN_SYM'], [], ['AND_SYM', 'CHAIN_SYM']];
        }
        yield 'unrelated scope' => ['other', ['AND_SYM', 'CHAIN_SYM'], ['RELEASE_SYM'], ['AND_SYM', 'CHAIN_SYM', 'RELEASE_SYM']];
    }

    public function testRewritePreservesRollbackToSavepoint(): void
    {
        $trace = new DerivationTrace('rollback');
        $trace->expand(0, new Production([new Terminal('ROLLBACK_SYM'), new Terminal('TO_SYM'), new Terminal('IDENT')]), 1);
        $input = $trace->terminals();
        self::assertSame($input, (new TransactionCompletionRule())->rewrite($input));
    }
}
