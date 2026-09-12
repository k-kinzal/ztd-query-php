<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\SubstringRule;

#[CoversClass(SubstringRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class SubstringRuleTest extends TestCase
{
    #[DataProvider('providerArguments')]
    public function testRewriteKeepsSubstringSeparatorsOutsideCompoundArguments(TerminalSequence $input, string $expected): void
    {
        $rule = new SubstringRule();
        $result = $rule->rewrite($input);
        self::assertSame(explode(' ', $expected), $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals, array_values(array_filter($result->terminals, static fn (TerminalOccurrence $token): bool => $token->id >= 0)));
        self::assertCount(count($result->terminals), array_unique(array_column($result->terminals, 'id')));
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{TerminalSequence, string}>
     */
    public static function providerArguments(): iterable
    {
        foreach (['substr_list', 'func_arg_list'] as $scope) {
            $tokens = [];
            foreach ([1 => 'DEFAULT IS NOT DISTINCT FROM DEFAULT', 0 => 'SIMILAR', 2 => 'DEFAULT LIKE DEFAULT', 4 => 'ESCAPE', 3 => 'DEFAULT'] as $id => $text) {
                foreach (explode(' ', $text) as $name) {
                    $tokens[] = new TerminalOccurrence($name, count($tokens), $id === 0 || $id === 4 ? [0] : [0, $id], $id === 0 || $id === 4 ? [$scope] : [$scope, 'a_expr']);
                }
            }
            $productions = [new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'a_expr', 0), new ProductionOccurrence(2, 0, 'a_expr', 0), new ProductionOccurrence(3, 0, 'a_expr', 0)];
            yield [new TerminalSequence($tokens, $tokens, productions: $productions), $scope === 'substr_list' ? '( DEFAULT IS NOT DISTINCT FROM DEFAULT ) SIMILAR ( DEFAULT LIKE DEFAULT ) ESCAPE DEFAULT' : 'DEFAULT IS NOT DISTINCT FROM DEFAULT SIMILAR DEFAULT LIKE DEFAULT ESCAPE DEFAULT'];
        }
    }

    public function testRewritePreservesAnEmptyArgument(): void
    {
        $input = new TerminalSequence([], productions: [new ProductionOccurrence(0, null, 'substr_list', 0), new ProductionOccurrence(1, 0, 'a_expr', 0)]);
        self::assertSame($input, (new SubstringRule())->rewrite($input));
    }
}
