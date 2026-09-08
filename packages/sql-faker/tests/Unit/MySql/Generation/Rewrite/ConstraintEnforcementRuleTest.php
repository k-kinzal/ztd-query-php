<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\ConstraintEnforcementRule;

#[CoversClass(ConstraintEnforcementRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ConstraintEnforcementRuleTest extends TestCase
{
    public function testRewriteRemovesOnlyMisplacedColumnEnforcement(): void
    {
        $misplaced = new TerminalOccurrence('ENFORCED_SYM', 2, [0, 1], ['column_attribute', 'constraint_enforcement']);
        $table = new TerminalOccurrence('ENFORCED_SYM', 4, [3], ['constraint_enforcement']);
        $check = new TerminalOccurrence(')', 6, [5], ['check_constraint']);
        $valid = new TerminalOccurrence('ENFORCED_SYM', 9, [7, 8], ['column_attribute', 'constraint_enforcement']);
        $input = new TerminalSequence([$misplaced, $table, $check, $valid], [], [], [
            new ProductionOccurrence(1, 0, 'constraint_enforcement', 0), new ProductionOccurrence(3, null, 'constraint_enforcement', 0),
            new ProductionOccurrence(8, 7, 'constraint_enforcement', 0),
        ]);
        self::assertSame([$table, $check, $valid], (new ConstraintEnforcementRule())->rewrite($input)->terminals);
    }
}
