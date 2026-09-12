<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Column\ForeignKeyActionRule;

#[CoversClass(ForeignKeyActionRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ForeignKeyActionRuleTest extends TestCase
{
    #[DataProvider('providerActions')]
    public function testRewriteOnlyUpdateColumnListsAreRemoved(string $scope, bool $columns): void
    {
        $action = new TerminalOccurrence('NULL_P', 10, [0, 1], [$scope, 'key_action']);
        $column = new TerminalOccurrence('IDENT', 11, [0, 1, 2], [$scope, 'key_action', 'opt_column_list']);
        $terminals = $columns ? [$action, $column] : [$action];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'key_action', 0),
            new ProductionOccurrence(2, 1, 'opt_column_list', 0),
        ]);
        $rule = new ForeignKeyActionRule();
        $result = $rule->rewrite($input);
        self::assertSame($scope === 'key_update' ? [$action] : $terminals, $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerActions(): iterable
    {
        yield ['key_update', true];
        yield ['key_delete', true];
        yield ['key_update', false];
    }
}
