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
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NameAssignment;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NameAssignment::class)]
#[Medium]
final class NameAssignmentTest extends TestCase
{
    public function testDeriveItemLeavesAnUnscopedWordToTheProgram(): void
    {
        $set = (new Inspection())->diagnose('SET x = y, GLOBAL z = w');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(NameAssignment::class, $set->statement->items[0]);
        self::assertInstanceOf(NameAssignment::class, $set->statement->items[1]);
        self::assertInstanceOf(BareName::class, $set->statement->items[0]->value);
        self::assertInstanceOf(BareName::class, $set->statement->items[1]->value);
        self::assertInstanceOf(Dependent::class, $set->facts->scalar($set->statement->items[0]->value)->type);
        self::assertInstanceOf(Known::class, $set->facts->scalar($set->statement->items[1]->value)->type);
    }

    public function testDeriveItemReportsARowAsTheValue(): void
    {
        $operation = (new Inspection())->diagnose('SET SESSION x = (1, 2)');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRenderWritesTheScopeAndTheQualifier(): void
    {
        self::assertSame('SET GLOBAL k.key_buffer_size = 1', (new Inspection('mysql-5.7.44'))->diagnose('set global k.key_buffer_size = 1')->toString());
    }

    public function testRefusesAWordThatContradictsTheScope(): void
    {
        $this->expectExceptionMessage('A bare name value is known to be text exactly when a scope keyword names a system variable.');
        new NameAssignment(new Name('x'), new BareName(new Name('y')));
    }
}
