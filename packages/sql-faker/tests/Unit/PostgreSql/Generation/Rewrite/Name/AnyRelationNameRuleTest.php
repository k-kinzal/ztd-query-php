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
use SqlFaker\PostgreSql\Generation\Rewrite\Name\AnyRelationNameRule;

#[CoversClass(AnyRelationNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class AnyRelationNameRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerNames')]
    public function testRewriteLimitsOnlyNamesConvertedToRelations(TerminalSequence $input, array $expected): void
    {
        $rule = new AnyRelationNameRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{TerminalSequence, list<string>}>
     */
    public static function providerNames(): iterable
    {
        foreach (['AlterCompositeTypeStmt', 'DefineStmt', 'RenameStmt', 'DropStmt', 'CreateDomainStmt'] as $scope) {
            $names = [new TerminalOccurrence('IDENT', 100, [0, 1], [$scope, 'any_name'])];
            $productions = [new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'any_name', 0)];
            foreach (range(2, 4) as $id) {
                $names[] = new TerminalOccurrence('.', 100 + count($names), [0, 1], [$scope, 'any_name']);
                $names[] = new TerminalOccurrence('IDENT', 100 + count($names), [0, 1, $id], [$scope, 'any_name', 'attr_name']);
                $productions[] = new ProductionOccurrence($id, 1, 'attr_name', 0);
            }
            $suffix = [];
            if ($scope === 'DefineStmt') {
                $productions[] = new ProductionOccurrence(9, 0, 'OptTableFuncElementList', 0);
            }
            if ($scope === 'RenameStmt') {
                $suffix = ['RENAME', 'ATTRIBUTE'];
                $names[] = new TerminalOccurrence('RENAME', 110, [0], [$scope]);
                $names[] = new TerminalOccurrence('ATTRIBUTE', 111, [0], [$scope]);
            }
            $expected = ['IDENT', '.', 'IDENT', '.', 'IDENT'];
            if (in_array($scope, ['DropStmt', 'CreateDomainStmt'], true)) {
                array_push($expected, '.', 'IDENT');
            }
            yield $scope => [new TerminalSequence($names, $names, [], $productions), [...$expected, ...$suffix]];
        }
    }
}
