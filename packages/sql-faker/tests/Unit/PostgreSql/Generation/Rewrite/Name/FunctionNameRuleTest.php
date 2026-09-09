<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\FunctionNameRule;

#[CoversClass(FunctionNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class FunctionNameRuleTest extends TestCase
{
    public function testRewriteKeepsFunctionQualificationAndColumnIndirection(): void
    {
        $function = new TerminalOccurrence('[', 4, [0, 1, 2, 3], ['root', 'func_name', 'indirection', 'indirection_el']);
        $column = new TerminalOccurrence('[', 9, [0, 6, 7, 8], ['root', 'columnref', 'indirection', 'indirection_el']);
        $input = new TerminalSequence([$function, $column], [$function, $column], [], [
            new ProductionOccurrence(3, 2, 'indirection_el', 2), new ProductionOccurrence(8, 7, 'indirection_el', 2),
        ]);
        $result = (new FunctionNameRule())->rewrite($input);
        self::assertSame(['.', 'IDENT', '['], $result->names());
        self::assertSame($column, $result->terminals[2]);
        self::assertSame($function->ancestors, $result->terminals[0]->ancestors);
        self::assertSame($result, (new FunctionNameRule())->rewrite($result));
        self::assertSame($input->original, $result->original);
    }

    public function testIsFunctionNameRejectsArgumentExpressionsAndRecognizesRecursiveIndirection(): void
    {
        $rule = new FunctionNameRule();
        self::assertTrue($rule->isFunctionName(['func_name', 'indirection', 'indirection', 'indirection_el']));
        self::assertTrue($rule->isFunctionName(['function_with_argtypes', 'indirection', 'indirection_el']));
        self::assertFalse($rule->isFunctionName(['function_with_argtypes', 'func_args', 'columnref', 'indirection', 'indirection_el']));
        self::assertFalse($rule->isFunctionName([]));
    }
}
