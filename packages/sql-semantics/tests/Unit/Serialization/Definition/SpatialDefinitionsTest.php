<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Spatial\SpatialDefinition;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\CreateSpatialReferenceSystemStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\SpatialDefinitions::class)]
#[Medium]
final class SpatialDefinitionsTest extends TestCase
{
    public function testWriteReturnsNullForOtherDefinitionFamilies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP DATABASE app');
        self::assertNull(\SqlSemantics\Serialization\Definition\SpatialDefinitions::write($statement));
    }

    public function testWriteKeepsMetadataInsideLiteralBoundaries(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE SPATIAL REFERENCE SYSTEM 4120 NAME 'Greek' DEFINITION 'coordinate-system text'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        $name = Expression::literal("Greek'; DROP DATABASE app", Dialect::MySql);
        self::assertInstanceOf(Literal::class, $name);
        $changed = $statement->withDefinition(new SpatialDefinition($name, $statement->definition->definition));
        $roundTrip = $binder->bind($changed->toString());
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $roundTrip);
        self::assertSame($name->text, $roundTrip->definition->name->text);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteSpellsEverySpatialReferenceSystemForm')]
    public function testWriteSpellsEverySpatialReferenceSystemForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, \SqlSemantics\Serialization\Definition\SpatialDefinitions::write($statement)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteSpellsEverySpatialReferenceSystemForm(): iterable
    {
        return [
            'DROP SPATIAL REFERENCE SYSTEM 4000 (MySql)' => [Dialect::MySql, null, [], 'DROP SPATIAL REFERENCE SYSTEM 4000', 'DROP SPATIAL REFERENCE SYSTEM 4000'],
            'DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4000 (MySql)' => [Dialect::MySql, null, [], 'DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4000', 'DROP SPATIAL REFERENCE SYSTEM IF EXISTS 4000'],
            'CREATE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\' (MySql)' => [Dialect::MySql, null, [], 'CREATE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\'', 'CREATE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\''],
            'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\' ORGANIZATION \'o\' IDENTIFIED ... 3' => [Dialect::MySql, null, [], 'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\' ORGANIZATION \'o\' IDENTIFIED BY 7 DESCRIPTION \'x\'', 'CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\' ORGANIZATION \'o\' IDENTIFIED BY 7 DESCRIPTION \'x\''],
            'CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4000 NAME \'n\' DEFINITION \'d\' DESCRIPTION \'x\' (MySql)' => [Dialect::MySql, null, [], 'CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4000 NAME \'n\' DEFINITION \'d\' DESCRIPTION \'x\'', 'CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4000 NAME \'n\' DEFINITION \'d\' DESCRIPTION \'x\''],
            'CREATE SPATIAL REFERENCE SYSTEM 4000 ORGANIZATION \'o\' IDENTIFIED BY 7 NAME \'n\' DEFINITION \'d\' (MySql)' => [Dialect::MySql, null, [], 'CREATE SPATIAL REFERENCE SYSTEM 4000 ORGANIZATION \'o\' IDENTIFIED BY 7 NAME \'n\' DEFINITION \'d\'', 'CREATE SPATIAL REFERENCE SYSTEM 4000 NAME \'n\' DEFINITION \'d\' ORGANIZATION \'o\' IDENTIFIED BY 7'],
        ];
    }
}
