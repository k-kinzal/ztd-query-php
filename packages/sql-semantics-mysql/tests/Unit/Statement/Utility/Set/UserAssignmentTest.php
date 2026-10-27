<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(UserAssignment::class)]
#[Medium]
final class UserAssignmentTest extends TestCase
{
    public function testDeriveItemDerivesTheVariableAndTheValue(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET @a = 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set);
        $item = $set->items[0];
        self::assertInstanceOf(UserAssignment::class, $item);
        self::assertInstanceOf(Dependent::class, $item->variable->scalarInformation()->type);
        self::assertSame(Nullability::NotNull, $item->value->scalarInformation()->nullability);
    }

    public function testDeriveItemReportsARowAsTheValue(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Operand should contain 1 column(s), not 2.');
        (new Semantics(Dialect::MySql))->analyze('SET @a = (1, 2)');
    }

    public function testDeriveItemMakesThePreparedEntryVisibleToFollowingAssignments(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci');
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings($collation, userVariables: []);
        $set = $semantics->analyze('SET @x=1,@y=@x', $semantics->context([], session: $settings));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set);
        $second = $set->items[1];
        self::assertInstanceOf(UserAssignment::class, $second);
        $type = $second->value->scalarInformation()->type;

        self::assertEquals(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(67108860, $collation, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongBlob)), $type);
        self::assertSame([], $settings->userVariables);
    }

    public function testRenderWritesAnEqualsSign(): void
    {
        self::assertSame("SET @`a b` = 'x'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set @'a b' := 'x'")->toString());
    }
}
