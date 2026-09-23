<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\CreationPolicy;
use SqlSemantics\Model\Definition\Spatial\SpatialDefinition;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\CreateSpatialReferenceSystemStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateSpatialReferenceSystemStatement::class)]
#[Medium]
final class CreateSpatialReferenceSystemStatementTest extends TestCase
{
    public function testWithSridReplacesTheTargetWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
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
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withSrid($srid);
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->srid, $copy->srid);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithDefinitionRequiresOneCompleteMetadataValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $name = Expression::literal('Replacement', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        $definition = new SpatialDefinition($name, $statement->definition->definition);
        $changed = $statement->withDefinition($definition);
        self::assertSame("'Greek'", $statement->definition->name->text);
        self::assertSame("'Replacement'", $changed->definition->name->text);
        self::assertSame($statement->definition->definition->text, $changed->definition->definition->text);
    }

    public function testWithPolicyPreservesMutuallyExclusiveCreationBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $ignored = $statement->withPolicy(CreationPolicy::IfNotExists);
        $replaced = $ignored->withPolicy(CreationPolicy::Replace);
        self::assertSame(CreationPolicy::RequireNew, $statement->policy);
        self::assertStringStartsWith('CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS', $ignored->toString());
        self::assertStringStartsWith('CREATE OR REPLACE SPATIAL REFERENCE SYSTEM', $replaced->toString());
        self::assertStringNotContainsString('IF NOT EXISTS', $replaced->toString());
    }

}
