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
use SqlFaker\PostgreSql\Generation\Rewrite\Name\ColumnNameRule;

#[CoversClass(ColumnNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ColumnNameRuleTest extends TestCase
{
    /**
     * @param list<TerminalOccurrence> $terminals
     * @param list<ProductionOccurrence> $productions
     * @param list<string> $expected
     */
    #[DataProvider('providerChains')]
    public function testRewriteKeepsTheFinalColumnAndIndependentIndirections(array $terminals, array $productions, array $expected): void
    {
        $input = new TerminalSequence($terminals, $terminals, [], $productions);
        $rule = new ColumnNameRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{list<TerminalOccurrence>, list<ProductionOccurrence>, list<string>}>
     */
    public static function providerChains(): iterable
    {
        foreach (['columnref', 'a_expr'] as $context) {
            foreach ([2, 3, 4, 5] as $count) {
                foreach ([false, true] as $subscript) {
                    $terminals = [new TerminalOccurrence('IDENT', 100, [0], [$context])];
                    $productions = [];
                    $expected = ['IDENT'];
                    for ($index = 1; $index <= $count; ++$index) {
                        $rules = [$context, 'indirection', 'indirection_el'];
                        $ancestors = [0, 10, $index];
                        $names = $index === 2 && $subscript ? ['[', 'ICONST', ']'] : ['.', $index === $count ? '*' : 'NAME' . $index];
                        $productions[] = new ProductionOccurrence($index, 10, 'indirection_el', 0);
                        foreach ($names as $offset => $name) {
                            $terminals[] = new TerminalOccurrence($name, $index * 10 + $offset, $ancestors, $rules);
                        }
                        if ($context !== 'columnref' || $subscript || $index > $count - 3) {
                            $expected = [...$expected, ...$names];
                        }
                    }
                    yield [$terminals, $productions, $expected];
                }
            }
        }
    }

    public function testRewritePreservesAnEmptyIndirection(): void
    {
        $input = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'indirection_el', 0)]);
        self::assertSame($input, (new ColumnNameRule())->rewrite($input));
    }
}
