<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\AssignedUserVariable;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AssignedUserVariable::class)]
final class AssignedUserVariableTest extends TestCase
{
    public function testHasOneValueAndAnExplicitWritableLocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a=123');
        self::assertInstanceOf(SetStatement::class, $statement);
        $assignment = $statement->settings[0];
        self::assertInstanceOf(AssignedUserVariable::class, $assignment);
        self::assertSame('a', $assignment->target->spelling());
        self::assertSame('123', $assignment->value->spelling());
        self::assertFalse(property_exists($assignment, 'values'));
    }

    public function testRejectsAnExpressionFromAnotherSqlDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a=123');
        self::assertInstanceOf(SetStatement::class, $statement);
        $assignment = $statement->settings[0];
        self::assertInstanceOf(AssignedUserVariable::class, $assignment);
        $this->expectException(InvalidStructure::class);
        new AssignedUserVariable($assignment->target, \SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql), $statement->source);
    }
}
