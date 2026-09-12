<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\AlterEventRule;

#[CoversClass(AlterEventRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class AlterEventRuleTest extends TestCase
{
    public function testRewriteAddsStatusOnlyWhenEveryAlterationWasOmitted(): void
    {
        $name = new TerminalOccurrence('IDENT', 3, [0, 1], ['alter_event_stmt', 'sp_name']);
        $input = new TerminalSequence([$name], [$name], [], [new ProductionOccurrence(0, null, 'alter_event_stmt', 0), new ProductionOccurrence(1, 0, 'sp_name', 0), new ProductionOccurrence(2, 0, 'opt_ev_status', 0)]);
        $rule = new AlterEventRule();
        $result = $rule->rewrite($input);
        self::assertSame(['IDENT', 'ENABLE_SYM'], $result->names());
        self::assertSame([0, 2], $result->terminals[1]->ancestors);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    #[DataProvider('providerAlterations')]
    public function testRewriteKeepsExistingAlterationsAndUnrelatedStatements(string $scope, string $clause): void
    {
        $name = new TerminalOccurrence('IDENT', 3, [0, 1], [$scope, 'sp_name']);
        $alteration = new TerminalOccurrence('EXISTING', 4, [0, 2], [$scope, $clause]);
        $input = new TerminalSequence([$name, $alteration], [], [], [new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'sp_name', 0), new ProductionOccurrence(2, 0, $clause, 0)]);
        self::assertSame($input, (new AlterEventRule())->rewrite($input));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerAlterations(): array
    {
        return [['alter_event_stmt', 'ev_alter_on_schedule_completion'], ['alter_event_stmt', 'opt_ev_rename_to'], ['alter_event_stmt', 'opt_ev_status'], ['alter_event_stmt', 'opt_ev_comment'], ['alter_event_stmt', 'opt_ev_sql_stmt'], ['create_event_stmt', 'opt_ev_status']];
    }
}
