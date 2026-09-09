<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\WithinGroupRule;

#[CoversClass(WithinGroupRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class WithinGroupRuleTest extends TestCase
{
    #[DataProvider('providerClauses')]
    public function testRewriteRetainsWithinGroupAndNestedFunctions(bool $within): void
    {
        $productions = [new ProductionOccurrence(0, null, 'func_expr', 0), new ProductionOccurrence(1, 0, 'func_application', 1), new ProductionOccurrence(2, 1, 'opt_sort_clause', 0), new ProductionOccurrence(3, 0, 'within_group_clause', 0), new ProductionOccurrence(4, 1, 'func_application', 2), new ProductionOccurrence(9, null, 'func_expr', 1)];
        $tokens = [
            new TerminalOccurrence('F', 10, [0, 1], ['func_expr', 'func_application']),
            new TerminalOccurrence('DISTINCT', 11, [0, 1], ['func_expr', 'func_application']),
            new TerminalOccurrence('VARIADIC', 12, [0, 1], ['func_expr', 'func_application']),
            new TerminalOccurrence('DISTINCT', 13, [0, 1, 4], ['func_expr', 'func_application', 'func_application']),
            new TerminalOccurrence('ORDER', 14, [0, 1, 2], ['func_expr', 'func_application', 'opt_sort_clause']),
        ];
        $clause = new TerminalOccurrence('WITHIN', 15, [0, 3], ['func_expr', 'within_group_clause']);
        $tokens = [...$tokens, ...($within ? [$clause] : [])];
        $input = new TerminalSequence($tokens, $tokens, [], $productions);
        $rule = new WithinGroupRule();
        $result = $rule->rewrite($input);
        self::assertSame($within ? [$tokens[0], $tokens[3], $clause] : $tokens, $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result->terminals, $rule->rewrite($result)->terminals);
    }

    /**
     * @return list<array{bool}>
     */
    public static function providerClauses(): array
    {
        return [[true], [false]];
    }

    public function testRewriteKeepsFunctionsWithoutWithinGroupMetadata(): void
    {
        $input = TerminalSequence::fromNames(['DISTINCT', 'VARIADIC', 'ORDER']);
        self::assertSame($input, (new WithinGroupRule())->rewrite($input));
    }
}
