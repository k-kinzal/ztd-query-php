<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\DropSpatialReferenceSystemStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropSpatialReferenceSystemStatement::class)]
#[Medium]
final class DropSpatialReferenceSystemStatementTest extends TestCase
{
    public function testWithSridReplacesTheTargetWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM 4120');
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        $changed = $statement->withSrid(4294967295);
        self::assertSame(4120, $statement->srid);
        self::assertSame(4294967295, $changed->srid);
        self::assertStringContainsString('4294967295', $changed->toString());
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith([4294967296])]
    public function testWithSridRejectsIdentifiersOutsideTheOperationDomain(int $srid): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM 4120');
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSrid($srid);
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM 4120');
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->srid, $copy->srid);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM 4120');
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithIfExistsReplacesTheMissingDefinitionPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM 4120');
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4120', $changed->toString());
    }

}
