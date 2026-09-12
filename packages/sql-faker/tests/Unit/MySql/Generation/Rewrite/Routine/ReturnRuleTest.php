<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Routine\ReturnRule;

#[CoversClass(ReturnRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ReturnRuleTest extends TestCase
{
    /**
     * @param list<string> $scopes
     */
    #[DataProvider('providerScopes')]
    public function testRewriteKeepsReturnsInFunctionsAndEvaluatesExpressionsInOtherRoutines(array $scopes, bool $changed): void
    {
        $rules = [...$scopes, 'sp_proc_stmt_return'];
        $ancestors = array_keys($rules);
        $return = new TerminalOccurrence('RETURN_SYM', 100, $ancestors, $rules);
        $expression = new TerminalOccurrence('NUM', 101, [...$ancestors, 20], [...$rules, 'expr']);
        $input = new TerminalSequence([$return, $expression], [$return, $expression], [], [new ProductionOccurrence(count($scopes), null, 'sp_proc_stmt_return', 0)]);
        $rule = new ReturnRule();
        $result = $rule->rewrite($input);
        self::assertSame([$changed ? 'DO_SYM' : 'RETURN_SYM', 'NUM'], $result->names());
        self::assertSame($expression, $result->terminals[1]);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($return->id, $result->terminals[0]->id);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{list<string>, bool}>
     */
    public static function providerScopes(): array
    {
        return [[['sp_tail'], true], [['trigger_tail'], true], [['ev_sql_stmt'], true], [['sf_tail'], false], [[], false], [['sf_tail', 'ev_sql_stmt'], true], [['sp_tail', 'sf_tail'], false], [['ordinary'], false]];
    }

    public function testRewritePreservesAnEmptyReturnOccurrence(): void
    {
        $input = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'sp_proc_stmt_return', 0)]);
        self::assertSame($input, (new ReturnRule())->rewrite($input));
    }
}
