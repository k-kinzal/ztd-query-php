<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\ConstraintAttributesRule;

#[CoversClass(ConstraintAttributesRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ConstraintAttributesRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerAttributes')]
    public function testRewritePreservesCompatibleOptionsAndDropsOnlyConflicts(TerminalSequence $input, array $expected): void
    {
        $rule = new ConstraintAttributesRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result->terminals, $rule->rewrite($result)->terminals);
    }

    /**
     * @return iterable<string, array{TerminalSequence, list<string>}>
     */
    public static function providerAttributes(): iterable
    {
        $cases = [
            'empty' => [[], []],
            'compatible and redundant' => [['DEFERRABLE', 'DEFERRABLE', 'INITIALLY DEFERRED', 'NOT VALID', 'NO INHERIT'], ['DEFERRABLE', 'DEFERRABLE', 'INITIALLY', 'DEFERRED', 'NOT', 'VALID', 'NO', 'INHERIT']],
            'deferrability conflict' => [['NOT DEFERRABLE', 'DEFERRABLE'], ['NOT', 'DEFERRABLE']],
            'reverse deferrability conflict' => [['DEFERRABLE', 'NOT DEFERRABLE'], ['DEFERRABLE']],
            'initial state conflict' => [['INITIALLY IMMEDIATE', 'INITIALLY DEFERRED'], ['INITIALLY', 'IMMEDIATE']],
            'reverse initial state conflict' => [['INITIALLY DEFERRED', 'INITIALLY IMMEDIATE'], ['INITIALLY', 'DEFERRED']],
            'deferred requires deferrable' => [['NOT DEFERRABLE', 'INITIALLY DEFERRED'], ['NOT', 'DEFERRABLE']],
            'reverse deferred requires deferrable' => [['INITIALLY DEFERRED', 'NOT DEFERRABLE'], ['INITIALLY', 'DEFERRED']],
        ];
        foreach ($cases as $name => [$options, $expected]) {
            $terminals = [];
            $productions = [new ProductionOccurrence(0, null, 'ConstraintAttributeSpec', 0)];
            foreach ($options as $index => $option) {
                $id = 10 + $index;
                $productions[] = new ProductionOccurrence($id, 0, 'ConstraintAttributeElem', 0);
                foreach (explode(' ', $option) as $word) {
                    $terminals[] = new TerminalOccurrence($word, 100 + count($terminals), [0, $id], ['ConstraintAttributeSpec', 'ConstraintAttributeElem']);
                }
            }
            yield $name => [new TerminalSequence($terminals, $terminals, [], $productions), $expected];
        }
    }

    public function testRewriteKeepsIndependentConstraintScopesSeparate(): void
    {
        $a = new TerminalOccurrence('NOT', 10, [0, 1], ['ConstraintAttributeSpec', 'ConstraintAttributeElem']);
        $b = new TerminalOccurrence('DEFERRABLE', 11, [0, 1], ['ConstraintAttributeSpec', 'ConstraintAttributeElem']);
        $c = new TerminalOccurrence('DEFERRABLE', 12, [2, 3], ['ConstraintAttributeSpec', 'ConstraintAttributeElem']);
        $outside = new TerminalOccurrence('DEFERRABLE', 13, [4], ['ConstraintAttributeElem']);
        $input = new TerminalSequence([$a, $b, $c, $outside], [], [], [new ProductionOccurrence(1, 0, 'ConstraintAttributeElem', 0), new ProductionOccurrence(3, 2, 'ConstraintAttributeElem', 1), new ProductionOccurrence(4, null, 'ConstraintAttributeElem', 1), new ProductionOccurrence(5, null, 'ConstraintAttributeElem', 0)]);
        self::assertSame($input, (new ConstraintAttributesRule())->rewrite($input));
    }
}
