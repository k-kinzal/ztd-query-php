<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropRoutinesStatement::class)]
#[Medium]
final class DropRoutinesStatementTest extends TestCase
{
    public function testWithTargetsReplacesTheRequestWithoutMutatingItsSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROUTINE f()');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropRoutinesStatement::class, $statement);
        $changed = $statement->withTargets([new Routine\RoutineByName(new QualifiedName(['app', 'g']))]);
        self::assertSame(['f'], $statement->targets[0]->name->parts);
        self::assertSame('DROP ROUTINE "app"."g"', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithIfExistsChangesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROUTINE f()');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropRoutinesStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertTrue($changed->ifExists);
        self::assertStringContainsString('IF EXISTS', $changed->toString());
    }

    public function testWithBehaviorPreservesTheTargetIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROUTINE f()');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropRoutinesStatement::class, $statement);
        $changed = $statement->withBehavior(\SqlSemantics\Model\Definition\DropBehavior::Cascade);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Default, $statement->behavior);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Cascade, $changed->behavior);
        self::assertStringEndsWith('CASCADE', $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROUTINE f()');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropRoutinesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->targets, $copy->targets);
        self::assertSame($statement->behavior, $copy->behavior);
        self::assertSame($statement->ifExists, $copy->ifExists);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROUTINE f()');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\DropRoutinesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin($origin);
    }

}
