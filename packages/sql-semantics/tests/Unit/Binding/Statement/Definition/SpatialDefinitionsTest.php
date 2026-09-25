<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\CreationPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\CreateSpatialReferenceSystemStatement;
use SqlSemantics\Model\Statement\Definition\MySql\DropSpatialReferenceSystemStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\SpatialDefinitions::class)]
#[Medium]
final class SpatialDefinitionsTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindPreservesCreationMetadataAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $sql = "CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4120 DESCRIPTION 'description' ORGANIZATION 'EPSG' IDENTIFIED BY 0x1018 DEFINITION 'coordinate-system text' NAME 'Greek'";
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        self::assertSame(4120, $statement->srid);
        self::assertSame(CreationPolicy::Replace, $statement->policy);
        self::assertSame("'Greek'", $statement->definition->name->text);
        self::assertSame("'coordinate-system text'", $statement->definition->definition->text);
        self::assertNotNull($statement->definition->organization);
        self::assertSame(4120, $statement->definition->organization->identifier);
        self::assertSame("'EPSG'", $statement->definition->organization->name->text);
        self::assertSame("'description'", $statement->definition->description?->text);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindPreservesRemovalPoliciesAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 0xffffffff');
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        self::assertSame(4294967295, $statement->srid);
        self::assertTrue($statement->ifExists);
        self::assertSame('DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4294967295', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['0004120', 4120])]
    #[TestWith(["X'1018'", 4120])]
    #[TestWith(['0x000000001018', 4120])]
    public function testIdentifierPreservesNumericIdentityAcrossIntegerSpellings(string $input, int $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM ' . $input);
        self::assertInstanceOf(DropSpatialReferenceSystemStatement::class, $statement);
        self::assertSame($expected, $statement->srid);
    }

    #[TestWith(['0'])]
    #[TestWith(['4294967296'])]
    #[TestWith(['0x100000000'])]
    #[TestWith(['1.2'])]
    #[TestWith(['1e2'])]
    public function testIdentifierDiagnosesValuesOutsideTheIdentifierDomain(string $input): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('unsigned 32-bit');
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP SPATIAL REFERENCE SYSTEM ' . $input);
    }

    #[TestWith(['create or replace spatial reference system 4326 name \'x\' definition \'y\'', CreateSpatialReferenceSystemStatement::class, 'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4326 NAME \'x\' DEFINITION \'y\''])]
    #[TestWith(['CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 0X10F4 NAME \'x\' DEFINITION \'y\'', CreateSpatialReferenceSystemStatement::class, 'CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4340 NAME \'x\' DEFINITION \'y\''])]
    #[TestWith(['CREATE SPATIAL REFERENCE SYSTEM 004326 NAME \'x\' DEFINITION \'y\'', CreateSpatialReferenceSystemStatement::class, 'CREATE SPATIAL REFERENCE SYSTEM 4326 NAME \'x\' DEFINITION \'y\''])]
    #[TestWith(['CREATE SPATIAL REFERENCE SYSTEM 4326 NAME \'x\' DEFINITION \'y\'', CreateSpatialReferenceSystemStatement::class, 'CREATE SPATIAL REFERENCE SYSTEM 4326 NAME \'x\' DEFINITION \'y\''])]
    public function testBindReadsEveryCreationPolicyAndIdentifierSpelling(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
