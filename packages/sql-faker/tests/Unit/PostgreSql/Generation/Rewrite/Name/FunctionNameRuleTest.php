<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('providerFunctionScopes')]
    public function testRewriteLimitsFunctionNamesToCatalogSchemaAndFunction(string $scope): void
    {
        $base = new TerminalOccurrence('IDENT', 10, [0], [$scope]);
        $first = new TerminalOccurrence('.', 11, [0, 1, 2], [$scope, 'indirection', 'indirection_el']);
        $firstName = new TerminalOccurrence('IDENT', 12, [0, 1, 2], [$scope, 'indirection', 'indirection_el']);
        $second = new TerminalOccurrence('.', 13, [0, 1, 3], [$scope, 'indirection', 'indirection_el']);
        $secondName = new TerminalOccurrence('IDENT', 14, [0, 1, 3], [$scope, 'indirection', 'indirection_el']);
        $third = new TerminalOccurrence('.', 15, [0, 1, 4], [$scope, 'indirection', 'indirection_el']);
        $thirdName = new TerminalOccurrence('IDENT', 16, [0, 1, 4], [$scope, 'indirection', 'indirection_el']);
        $terminals = [$base, $first, $firstName, $second, $secondName, $third, $thirdName];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'indirection', 0),
            new ProductionOccurrence(2, 1, 'indirection_el', 0), new ProductionOccurrence(3, 1, 'indirection_el', 0),
            new ProductionOccurrence(4, 1, 'indirection_el', 0),
        ]);
        $rule = new FunctionNameRule();
        $result = $rule->rewrite($input);
        self::assertSame([$base, $first, $firstName, $second, $secondName], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerFunctionScopes(): iterable
    {
        yield 'call' => ['func_name'];
        yield 'signature' => ['function_with_argtypes'];
    }
}
