<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\Column\IdentityOptionRule;

#[CoversClass(IdentityOptionRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class IdentityOptionRuleTest extends TestCase
{
    /**
     * @param list<string> $option
     * @param list<string> $expected
     */
    #[DataProvider('providerOptions')]
    public function testRewriteKeepsSequenceOptionsWithinTheirOwningContext(string $scope, array $option, array $expected): void
    {
        $trace = new DerivationTrace($scope);
        $trace->expand(0, new Production([new Terminal('SET'), new NonTerminal('SeqOptElem')]), 0);
        $trace->expand(1, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $option)), 0);
        $input = $trace->terminals();
        $rule = new IdentityOptionRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, list<string>, list<string>}>
     */
    public static function providerOptions(): iterable
    {
        foreach ([['AS', 'INT_P'], ['OWNED', 'BY', 'IDENT']] as $option) {
            yield ['alter_identity_column_option', $option, ['SET', 'NO', 'CYCLE']];
            yield ['SeqOptList', $option, ['SET', ...$option]];
        }
        yield ['alter_identity_column_option', ['RESTART'], ['RESTART']];
        yield ['alter_identity_column_option', ['RESTART', 'WITH', 'ICONST'], ['RESTART', 'WITH', 'ICONST']];
        yield ['alter_identity_column_option', ['NO', 'CYCLE'], ['SET', 'NO', 'CYCLE']];
        yield ['alter_identity_column_option', ['CACHE', 'ICONST'], ['SET', 'CACHE', 'ICONST']];
        yield ['alter_identity_column_option', [], ['SET']];
    }

    public function testRewriteLeavesTheDedicatedRestartAlternativeUntouched(): void
    {
        $trace = new DerivationTrace('alter_identity_column_option');
        $trace->expand(0, new Production([new Terminal('RESTART'), new Terminal('ICONST')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new IdentityOptionRule())->rewrite($input));
    }
}
