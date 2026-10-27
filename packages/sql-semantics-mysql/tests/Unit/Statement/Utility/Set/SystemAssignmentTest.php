<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(SystemAssignment::class)]
#[Medium]
final class SystemAssignmentTest extends TestCase
{
    public function testDeriveItemDerivesTheVariableAndTheValue(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET @@GLOBAL.sql_mode = TRADITIONAL');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set);
        $item = $set->items[0];
        self::assertInstanceOf(SystemAssignment::class, $item);
        self::assertInstanceOf(Dependent::class, $item->variable->scalarInformation()->type);
        self::assertInstanceOf(BareName::class, $item->value);
        self::assertInstanceOf(Known::class, $item->value->scalarInformation()->type);
    }

    public function testDeriveItemReportsARowAsTheValue(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Operand should contain 1 column(s), not 2.');
        (new Semantics(Dialect::MySql))->analyze('SET @@SESSION.sort_buffer_size = (1, 2)');
    }

    public function testDeriveItemChecksTheValueBeforeTheTargetInModernReleases(): void
    {
        $diagnostics = (new Semantics(Dialect::MySql))->analyze('SET @@unknown_target = @@unknown_value')->diagnostics();

        self::assertInstanceOf(UnknownSystemVariable::class, $diagnostics[0]);
        self::assertSame('unknown_value', $diagnostics[0]->name);
        self::assertFalse($diagnostics[0]->assigned);
        self::assertInstanceOf(UnknownSystemVariable::class, $diagnostics[1]);
        self::assertSame('unknown_target', $diagnostics[1]->name);
        self::assertTrue($diagnostics[1]->assigned);
    }

    public function testRenderWritesTheKeywordValue(): void
    {
        self::assertSame('SET @@SESSION.x = ON', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('set @@local.x = on')->toString());
        self::assertSame('SET @@x = SYSTEM', (new Semantics(Dialect::MySql))->analyze('set @@x = system')->toString());
    }

    public function testRefusesAnUnresolvedWord(): void
    {
        $this->expectExceptionMessage('A bare name assigned to a system variable is its text.');
        new SystemAssignment(new SystemTarget(new Name('x')), new BareName(new Name('y'), null, false));
    }

    public function testDeriveItemLeavesTheAssignmentsOfAStoredProgramToItsRun(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame(["Variable 'version' is a read only variable"], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('SET @@version = 1')->diagnostics()));
        self::assertSame([], $semantics->analyze('CREATE PROCEDURE p() SET @@version = 1')->diagnostics());
    }

    public function testDeriveItemReportsAStructuredVariableThatIsNoKeyCacheVariable(): void
    {
        $unknown = (new Semantics(Dialect::MySql))->analyze('SET @@a.b = 1')->diagnostics();

        self::assertCount(1, $unknown);
        self::assertInstanceOf(UnknownSystemVariable::class, $unknown[0]);
        self::assertSame('a.b', $unknown[0]->name);
    }
}
