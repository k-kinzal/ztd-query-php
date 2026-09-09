<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\RelationNameRule;

#[CoversClass(RelationNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class RelationNameRuleTest extends TestCase
{
    public function testRewriteRemovesRelationStarsButPreservesExpressionIndirection(): void
    {
        $star = new TerminalOccurrence('.', 3, [0, 1, 2], ['qualified_name', 'indirection', 'indirection_el']);
        $suffix = new TerminalOccurrence('*', 4, [0, 1, 2], ['qualified_name', 'indirection', 'indirection_el']);
        $expression = new TerminalOccurrence('[', 9, [5, 6, 7, 8], ['qualified_name', 'expression', 'indirection', 'indirection_el']);
        $input = new TerminalSequence([$star, $suffix, $expression], [], [], [
            new ProductionOccurrence(2, 1, 'indirection_el', 1), new ProductionOccurrence(8, 7, 'indirection_el', 2),
        ]);
        self::assertSame([$expression], (new RelationNameRule())->rewrite($input)->terminals);
    }
    public function testRewriteLimitsRelationNamesToCatalogSchemaAndTable(): void
    {
        $scope = 'qualified_name';
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
        $rule = new RelationNameRule();
        $result = $rule->rewrite($input);
        self::assertSame([$base, $first, $firstName, $second, $secondName], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

}
