<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SystemAssignment::class)]
#[Medium]
final class SystemAssignmentTest extends TestCase
{
    public function testDeriveItemDerivesTheVariableAndTheValue(): void
    {
        $set = (new Inspection())->diagnose('SET @@GLOBAL.sql_mode = TRADITIONAL');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        $item = $set->statement->items[0];
        self::assertInstanceOf(SystemAssignment::class, $item);
        self::assertInstanceOf(Dependent::class, $set->facts->scalar($item->variable)->type);
        self::assertInstanceOf(BareName::class, $item->value);
        self::assertInstanceOf(Known::class, $set->facts->scalar($item->value)->type);
    }

    public function testDeriveItemReportsARowAsTheValue(): void
    {
        $operation = (new Inspection())->diagnose('SET @@SESSION.x = (1, 2)');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRenderWritesTheKeywordValue(): void
    {
        self::assertSame('SET @@SESSION.x = ON', (new Inspection('mysql-5.6.51'))->diagnose('set @@local.x = on')->toString());
        self::assertSame('SET @@x = SYSTEM', (new Inspection())->diagnose('set @@x = system')->toString());
    }

    public function testRefusesAnUnresolvedWord(): void
    {
        $this->expectExceptionMessage('A bare name assigned to a system variable is its text.');
        new SystemAssignment(new SystemVariable(new Name('x')), new BareName(new Name('y'), null, false));
    }
}
