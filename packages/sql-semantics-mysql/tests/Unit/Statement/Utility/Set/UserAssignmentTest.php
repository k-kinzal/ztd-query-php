<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UserAssignment::class)]
#[Medium]
final class UserAssignmentTest extends TestCase
{
    public function testDeriveItemDerivesTheVariableAndTheValue(): void
    {
        $set = (new Inspection())->diagnose('SET @a = 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        $item = $set->statement->items[0];
        self::assertInstanceOf(UserAssignment::class, $item);
        self::assertInstanceOf(Dependent::class, $set->facts->scalar($item->variable)->type);
        self::assertSame(Nullability::NotNull, $set->facts->scalar($item->value)->nullability);
    }

    public function testDeriveItemReportsARowAsTheValue(): void
    {
        $operation = (new Inspection())->diagnose('SET @a = (1, 2)');

        self::assertSame(['Operand should contain 1 column(s), not 2.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $operation->facts->diagnostics));
    }

    public function testRenderWritesAnEqualsSign(): void
    {
        self::assertSame("SET @`a b` = 'x'", (new Inspection('mysql-5.6.51'))->diagnose("set @'a b' := 'x'")->toString());
    }
}
