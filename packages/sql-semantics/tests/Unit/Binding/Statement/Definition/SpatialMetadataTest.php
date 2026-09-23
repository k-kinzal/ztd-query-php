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
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\SpatialMetadata::class)]
#[Medium]
final class SpatialMetadataTest extends TestCase
{
    #[TestWith([''])]
    #[TestWith(["NAME 'Greek'"])]
    #[TestWith(["DEFINITION 'text'"])]
    #[TestWith(["NAME 'Greek' NAME 'Other' DEFINITION 'text'"])]
    #[TestWith(["NAME 'Greek' DEFINITION 'text' DESCRIPTION 'a' DESCRIPTION 'b'"])]
    public function testReadDiagnosesMissingOrRepeatedAttributes(string $attributes): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('exactly one NAME and DEFINITION');
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE SPATIAL REFERENCE SYSTEM 4120 ' . $attributes);
    }

    public function testReadRetainsMetadataWithoutEvaluatingDefinitionText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE SPATIAL REFERENCE SYSTEM IF NOT EXISTS 4120 NAME 'Greek' DEFINITION 'opaque-to-the-SQL-evaluator coordinate definition'");
        self::assertInstanceOf(CreateSpatialReferenceSystemStatement::class, $statement);
        self::assertSame(CreationPolicy::IfNotExists, $statement->policy);
        self::assertSame("'opaque-to-the-SQL-evaluator coordinate definition'", $statement->definition->definition->text);
        self::assertNull($statement->definition->organization);
    }

}
