<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(NameAssignment::class)]
#[Medium]
final class NameAssignmentTest extends TestCase
{
    public function testDeriveItemLeavesAnUnscopedWordToTheProgram(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET x = y, GLOBAL z = w');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set);
        self::assertInstanceOf(NameAssignment::class, $set->items[0]);
        self::assertInstanceOf(NameAssignment::class, $set->items[1]);
        self::assertInstanceOf(BareName::class, $set->items[0]->value);
        self::assertInstanceOf(BareName::class, $set->items[1]->value);
        self::assertInstanceOf(Dependent::class, $set->items[0]->value->scalarInformation()->type);
        self::assertInstanceOf(Known::class, $set->items[1]->value->scalarInformation()->type);
    }

    public function testDeriveItemReportsARowAsTheValue(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Operand should contain 1 column(s), not 2.');
        (new Semantics(Dialect::MySql))->analyze('SET SESSION sort_buffer_size = (1, 2)');
    }

    public function testDeriveItemResolvesAnUnknownTargetBeforeItsValue(): void
    {
        $diagnostics = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SET unknown_target = @@unknown_value')->diagnostics();

        self::assertInstanceOf(UnknownSystemVariable::class, $diagnostics[0]);
        self::assertSame('unknown_target', $diagnostics[0]->name);
        self::assertTrue($diagnostics[0]->assigned);
        self::assertFalse($diagnostics[0]->structured);
        self::assertInstanceOf(UnknownSystemVariable::class, $diagnostics[1]);
        self::assertFalse($diagnostics[1]->assigned);
    }

    public function testRenderWritesTheScopeAndTheQualifier(): void
    {
        self::assertSame('SET GLOBAL k.key_buffer_size = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set global k.key_buffer_size = 1')->toString());
    }

    public function testRefusesAWordThatContradictsTheScope(): void
    {
        $this->expectExceptionMessage('A bare name value is known to be text exactly when a scope keyword names a system variable.');
        new NameAssignment(new Name('x'), new BareName(new Name('y')));
    }

    public function testDeriveItemReportsAQualifiedNameThatIsNoKeyCacheVariable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $unknown = $semantics->analyze('SET SESSION a.b = 1')->diagnostics();

        self::assertCount(1, $unknown);
        self::assertInstanceOf(UnknownSystemVariable::class, $unknown[0]);
        self::assertSame('a.b', $unknown[0]->name);
        self::assertTrue($unknown[0]->assigned);
        self::assertTrue($unknown[0]->structured);
        self::assertSame([], $semantics->analyze('SET GLOBAL hot.key_buffer_size = 0')->diagnostics());
    }
}
