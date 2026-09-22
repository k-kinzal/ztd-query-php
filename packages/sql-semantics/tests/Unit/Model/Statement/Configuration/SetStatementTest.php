<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedUserVariable;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetStatement::class)]
final class SetStatementTest extends TestCase
{
    public function testWithVariableValuePreservesAssignmentOrderAndTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a=1, @b=2');
        self::assertInstanceOf(SetStatement::class, $statement);
        $assignment = $statement->settings[0];
        self::assertInstanceOf(AssignedUserVariable::class, $assignment);
        $changed = $statement->withVariableValue($assignment, Expression::literal(3, Dialect::MySql));
        self::assertSame('SET @`a` = 1, @`b` = 2', $statement->toString());
        self::assertSame('SET @`a` = 3, @`b` = 2', $changed->toString());
        self::assertSame([['a'], ['b']], array_column($changed->settings, 'name'));
    }

    public function testWithVariableValueRejectsAnAssignmentFromAnotherStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET @a=1');
        self::assertInstanceOf(SetStatement::class, $statement);
        $other = $binder->bind('SET @a=1');
        self::assertInstanceOf(SetStatement::class, $other);


        self::assertInstanceOf(AssignedUserVariable::class, $other->settings[0]);
        $this->expectException(InvalidStructure::class);
        $statement->withVariableValue($other->settings[0], Expression::literal(3, Dialect::MySql));
    }
}
