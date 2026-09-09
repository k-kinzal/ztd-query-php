<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\SubqueryContextRule;

#[CoversClass(SubqueryContextRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class SubqueryContextRuleTest extends TestCase
{
    #[DataProvider('providerScopes')]
    public function testRewriteChangesOnlyTheForbiddenSubqueryOperand(string $statement, bool $replace): void
    {
        $left = new TerminalOccurrence('IDENT', 3, [0, 1], [$statement, 'bool_pri']);
        $subquery = new TerminalOccurrence('SUBQUERY', 4, [0, 1, 2], [$statement, 'bool_pri', 'subquery']);
        $other = new TerminalOccurrence('IDENT', 5, [0], [$statement]);
        $input = new TerminalSequence([$left, $subquery, $other], [$left, $subquery, $other], [], [new ProductionOccurrence(0, null, $statement, 0), new ProductionOccurrence(1, 0, 'bool_pri', 0), new ProductionOccurrence(2, 1, 'subquery', 0), new ProductionOccurrence(6, 0, 'subquery', 0)]);
        $rule = new SubqueryContextRule();
        $result = $rule->rewrite($input);
        self::assertSame($replace ? ['NUM', 'IDENT'] : ['IDENT', 'SUBQUERY', 'IDENT'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{string, bool}>
     */
    public static function providerScopes(): array
    {
        return [['handler_stmt', true], ['purge', true], ['install_stmt', true], ['part_type_def', true], ['opt_sub_part', true], ['select_stmt', false], ['create_table_stmt', false]];
    }
}
