<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\ValueRule;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ValueRule::class)]
#[Medium]
final class ValueRuleTest extends TestCase
{
    public function testValueLowersTheKeywords(): void
    {
        self::assertSame('SET a = ON, b = ALL, c = BINARY', (new Inspection('mysql-5.6.51'))->diagnose('set a = on, b = all, c = binary')->toString());
        self::assertSame('SET a = ROW, b = SYSTEM', (new Inspection())->diagnose('set a = row, b = system')->toString());
    }

    public function testWordLowersABareName(): void
    {
        $set = (new Inspection())->diagnose('SET @@a = b, @@c = (d)');
        self::assertInstanceOf(SetVariables::class, $set->statement);
        self::assertInstanceOf(SystemAssignment::class, $set->statement->items[1]);
        self::assertNotInstanceOf(BareName::class, $set->statement->items[1]->value);
    }
}
